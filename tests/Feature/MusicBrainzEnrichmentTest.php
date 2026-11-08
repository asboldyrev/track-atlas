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
        $this->assertDatabaseCount('tags', 0);
        Http::assertSentCount(1);
    }
}
