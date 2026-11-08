<?php

namespace Tests\Feature;

use App\Models\MetadataSuggestion;
use App\Models\Tag;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MusicBrainzEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_matched_recording_creates_suggestions_without_changing_manual_tags(): void
    {
        $track = Track::create([
            'path' => 'nightcall.mp3', 'title' => 'Nightcall', 'artist' => 'Kavinsky',
            'size' => 100, 'file_mtime' => 1,
        ]);
        $manual = Tag::create(['type' => 'mood', 'name' => 'Dark', 'slug' => 'dark']);
        $track->tags()->attach($manual->id, ['source' => 'manual']);

        Http::fake([
            'musicbrainz.org/ws/2/recording?*' => Http::response([
                'recordings' => [[
                    'id' => '11111111-1111-4111-8111-111111111111',
                    'title' => 'Nightcall',
                    'score' => 100,
                    'artist-credit' => [['name' => 'Kavinsky']],
                ]],
            ]),
            'musicbrainz.org/ws/2/recording/*' => Http::response([
                'genres' => [
                    ['name' => 'synthwave', 'count' => 4],
                    ['name' => 'electronic', 'count' => 2],
                ],
            ]),
        ]);

        $this->artisan('music:enrich', ['--track' => (string) $track->id])->assertExitCode(0);

        $suggestion = MetadataSuggestion::firstOrFail();
        $this->assertSame(['synthwave', 'electronic'], $suggestion->genres);
        $this->assertSame(100, $suggestion->match_score);
        $this->assertSame('pending', $suggestion->status);
        $this->assertCount(1, $track->fresh()->tags);
        $this->assertSame('manual', $track->fresh()->tags->first()->pivot->source);

        Http::assertSentCount(2);
        $this->artisan('music:enrich', ['--track' => (string) $track->id])->assertExitCode(0);
        Http::assertSentCount(2); // Default run does not re-query saved suggestions.
    }

    public function test_unmatched_recording_is_not_assigned_genres(): void
    {
        $track = Track::create([
            'path' => 'song.mp3', 'title' => 'Exact Song', 'artist' => 'Original Artist',
            'size' => 100, 'file_mtime' => 1,
        ]);
        Http::fake(['musicbrainz.org/ws/2/*' => Http::response([
            'recordings' => [[
                'id' => '22222222-2222-4222-8222-222222222222',
                'title' => 'Different Song',
                'score' => 100,
                'artist-credit' => [['name' => 'Original Artist']],
            ]],
        ])]);

        $this->artisan('music:enrich', ['--track' => (string) $track->id])->assertExitCode(0);
        $this->assertSame([], MetadataSuggestion::firstOrFail()->genres);
        $this->assertSame('unmatched', MetadataSuggestion::firstOrFail()->status);
        $this->assertDatabaseCount('tags', 0);
        Http::assertSentCount(1);
    }
    public function test_video_title_decoration_is_removed_only_for_lookup(): void
    {
        $track = Track::create([
            'path' => 'video.mp3',
            'title' => 'Dua Lipa - Break My Heart (Official Video)',
            'artist' => 'Dua Lipa', 'size' => 100, 'file_mtime' => 1,
        ]);

        Http::fake([
            'musicbrainz.org/ws/2/recording?*' => Http::response([
                'recordings' => [[
                    'id' => '33333333-3333-4333-8333-333333333333',
                    'title' => 'Break My Heart',
                    'score' => 100,
                    'artist-credit' => [['name' => 'Dua Lipa']],
                ]],
            ]),
            'musicbrainz.org/ws/2/recording/*' => Http::response(['genres' => []]),
        ]);

        $this->artisan('music:enrich', ['--track' => (string) $track->id])->assertExitCode(0);
        $this->assertSame('matched_no_genres', MetadataSuggestion::firstOrFail()->status);
        $this->assertNotNull(MetadataSuggestion::firstOrFail()->recording_mbid);
        $this->assertSame('Dua Lipa - Break My Heart (Official Video)', $track->fresh()->title);
        Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'recording:"Break My Heart"'));
    }

    public function test_musicbrainz_transient_503_is_retried(): void
    {
        $track = Track::create([
            'path' => 'song.mp3', 'title' => 'Song', 'artist' => 'Artist',
            'size' => 1, 'file_mtime' => 1,
        ]);

        Http::fake([
            'musicbrainz.org/ws/2/recording?*' => Http::sequence()
                ->push([], 503)
                ->push(['recordings' => [[
                    'id' => '44444444-4444-4444-8444-444444444444',
                    'title' => 'Song', 'score' => 99,
                    'artist-credit' => [['name' => 'Artist']],
                ]]], 200),
            'musicbrainz.org/ws/2/recording/*' => Http::response([
                'genres' => [['name' => 'rock', 'count' => 2]],
            ]),
        ]);

        $this->artisan('music:enrich', ['--track' => (string) $track->id])->assertExitCode(0);
        $this->assertSame(['rock'], MetadataSuggestion::firstOrFail()->genres);
        Http::assertSentCount(3);
    }

    public function test_video_artist_prefix_can_identify_recording_when_channel_artist_is_wrong(): void
    {
        $track = Track::create([
            'path' => 'eminem.mp3',
            'title' => 'Eminem - Mockingbird [Official Music Video]',
            'artist' => 'EminemMusic',
            'size' => 100, 'file_mtime' => 1,
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/recording?')) {
                $query = urldecode($request->url());
                if (str_contains($query, 'artist:"Eminem"')
                    && str_contains($query, 'recording:"Mockingbird"')) {
                    return Http::response(['recordings' => [[
                        'id' => '55555555-5555-4555-8555-555555555555',
                        'title' => 'Mockingbird',
                        'score' => 100,
                        'artist-credit' => [['name' => 'Eminem']],
                    ]]]);
                }

                return Http::response(['recordings' => []]);
            }

            return Http::response(['genres' => []]);
        });

        $this->artisan('music:enrich', ['--track' => (string) $track->id])->assertExitCode(0);

        $result = MetadataSuggestion::firstOrFail();
        $this->assertSame('matched_no_genres', $result->status);
        $this->assertSame('55555555-5555-4555-8555-555555555555', $result->recording_mbid);
        $this->assertSame('EminemMusic', $track->fresh()->artist);
        $this->assertSame('Eminem - Mockingbird [Official Music Video]', $track->fresh()->title);
        Http::assertSentCount(3);
    }

}
