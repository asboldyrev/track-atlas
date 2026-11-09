<?php

namespace Tests\Feature;

use App\Models\MetadataSuggestion;
use App\Models\Tag;
use App\Models\Track;
use App\Services\OpenRouterClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenRouterClassificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeTrack(string $title = 'Nightcall'): Track
    {
        return Track::create([
            'path' => $title.'.mp3',
            'title' => $title,
            'artist' => 'Kavinsky',
            'size' => 100,
            'file_mtime' => 1,
        ]);
    }

    private function fakeClassification(array $items): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'model' => 'openai/gpt-4o-mini',
                'choices' => [[
                    'message' => [
                        'content' => json_encode(['tracks' => $items], JSON_THROW_ON_ERROR),
                    ],
                ]],
                'usage' => [
                    'prompt_tokens' => 125,
                    'completion_tokens' => 35,
                    'cost' => 0.000048,
                ],
            ]),
        ]);
    }

    public function test_missing_key_never_sends_http_request(): void
    {
        config(['openrouter.key' => null]);
        Http::fake();
        $track = $this->makeTrack();

        $this->postJson('/ai/classify', ['tracks' => [$track->id]])
            ->assertUnprocessable()->assertJsonPath('message', 'OPENROUTER_API_KEY is not configured.');

        Http::assertNothingSent();
        $this->assertDatabaseCount('metadata_suggestions', 0);
    }

    public function test_classification_stores_pending_suggestion_and_usage_without_modifying_tags(): void
    {
        config(['openrouter.key' => 'fake-secret', 'openrouter.model' => 'openai/gpt-4o-mini']);
        $track = $this->makeTrack();
        $existing = Tag::create(['type' => 'mood', 'name' => 'Retro', 'slug' => 'retro']);
        $track->tags()->attach($existing->id, ['source' => 'manual']);

        $this->fakeClassification([
            ['id' => $track->id, 'genres' => ['Synthwave'], 'moods' => ['Dark', 'Nostalgic']],
        ]);

        $this->postJson('/ai/classify', ['tracks' => [$track->id]])
            ->assertOk()
            ->assertJsonPath('processed', 1)
            ->assertJsonPath('usage.prompt_tokens', 125)
            ->assertJsonPath('usage.cost_usd', 0.000048);

        $proposal = MetadataSuggestion::firstOrFail();
        $this->assertSame('pending', $proposal->status);
        $this->assertSame(['Synthwave'], $proposal->genres);
        $this->assertSame(['Dark', 'Nostalgic'], $proposal->moods);
        $this->assertSame('openrouter', $proposal->provider);
        $this->assertSame('manual', $track->fresh()->tags->first()->pivot->source);
        $this->assertCount(1, $track->fresh()->tags);
        $this->assertDatabaseCount('ai_usage_logs', 1);

        $this->postJson('/ai/classify', ['tracks' => [$track->id]])
            ->assertOk()->assertJsonPath('processed', 0);

        Http::assertSentCount(1);
    }

    public function test_accept_adds_new_genres_and_moods_without_overwriting_manual_tags(): void
    {
        $track = $this->makeTrack();
        $manual = Tag::create(['type' => 'mood', 'name' => 'Dark', 'slug' => 'dark']);
        $track->tags()->attach($manual->id, ['source' => 'manual']);
        $proposal = MetadataSuggestion::create([
            'track_id' => $track->id,
            'provider' => 'openrouter',
            'model' => 'openai/gpt-4o-mini',
            'genres' => ['Synth-pop'],
            'moods' => ['Dark', 'Dreamy'],
            'status' => 'pending',
        ]);

        $this->putJson('/ai/suggestions/'.$proposal->id, ['decision' => 'accept'])
            ->assertOk()->assertJsonPath('ok', true);

        $this->assertSame('accepted', $proposal->fresh()->status);
        $this->assertSame(3, $track->fresh()->tags()->count());
        $this->assertDatabaseHas('tags', ['type' => 'genre', 'slug' => 'synthpop']);
        $this->assertSame('openrouter', $track->fresh()->tags->first(fn ($tag) => $tag->type === 'genre')->pivot->source);
        $this->assertSame('manual', $track->fresh()->tags()->where('tags.id', $manual->id)->first()->pivot->source);

        $this->putJson('/ai/suggestions/'.$proposal->id, ['decision' => 'accept'])
            ->assertStatus(409);
        $this->assertSame(3, $track->fresh()->tags()->count());
    }

    public function test_reject_changes_no_track_tags(): void
    {
        $track = $this->makeTrack();
        $proposal = MetadataSuggestion::create([
            'track_id' => $track->id,
            'provider' => 'openrouter',
            'model' => 'openai/gpt-4o-mini',
            'genres' => ['Rock'],
            'moods' => ['Energetic'],
            'status' => 'pending',
        ]);

        $this->putJson('/ai/suggestions/'.$proposal->id, ['decision' => 'reject'])->assertOk();
        $this->assertSame('rejected', $proposal->fresh()->status);
        $this->assertDatabaseCount('tags', 0);
    }

    public function test_unexpected_response_id_is_rejected_without_persisting_suggestions(): void
    {
        config(['openrouter.key' => 'fake-secret']);
        $track = $this->makeTrack();
        $this->fakeClassification([
            ['id' => 999999, 'genres' => ['Rock'], 'moods' => ['Energetic']],
        ]);

        $this->postJson('/ai/classify', ['tracks' => [$track->id]])->assertUnprocessable();
        $this->assertDatabaseCount('metadata_suggestions', 0);
        $this->assertDatabaseCount('ai_usage_logs', 0);
    }

    public function test_retry_empty_suggestion_reclassifies_without_changing_active_tags(): void
    {
        config(['openrouter.key' => 'fake-secret']);
        $track = $this->makeTrack();
        $empty = MetadataSuggestion::create([
            'track_id' => $track->id,
            'provider' => 'openrouter',
            'model' => 'openai/gpt-4o-mini',
            'genres' => [],
            'moods' => [],
            'status' => 'pending',
        ]);
        $this->fakeClassification([
            ['id' => $track->id, 'genres' => ['Art Pop'], 'moods' => ['Melancholic']],
        ]);

        $this->postJson('/ai/classify', ['tracks' => [$track->id], 'retry_empty' => true])
            ->assertOk()->assertJsonPath('processed', 1);

        $this->assertSame('pending', $empty->fresh()->status);
        $this->assertSame(['Art Pop'], $empty->fresh()->genres);
        $this->assertSame(['Melancholic'], $empty->fresh()->moods);
        $this->assertCount(0, $track->fresh()->tags);
        Http::assertSentCount(1);
    }

    public function test_retry_empty_does_not_charge_again_for_nonempty_or_decided_suggestions(): void
    {
        config(['openrouter.key' => 'fake-secret']);
        $one = $this->makeTrack('First');
        $two = $this->makeTrack('Second');
        MetadataSuggestion::create([
            'track_id' => $one->id, 'provider' => 'openrouter',
            'genres' => ['Rock'], 'moods' => [], 'status' => 'pending',
        ]);
        MetadataSuggestion::create([
            'track_id' => $two->id, 'provider' => 'openrouter',
            'genres' => [], 'moods' => [], 'status' => 'rejected',
        ]);

        Http::fake();
        $this->postJson('/ai/classify', [
            'tracks' => [$one->id, $two->id], 'retry_empty' => true,
        ])->assertOk()->assertJsonPath('processed', 0);
        Http::assertNothingSent();
    }

    public function test_explicit_false_retry_flag_is_valid_for_new_classification(): void
    {
        config(['openrouter.key' => 'fake-secret']);
        $track = $this->makeTrack('Fresh');

        $this->fakeClassification([
            ['id' => $track->id, 'genres' => ['Pop'], 'moods' => ['Energetic']],
        ]);

        $this->postJson('/ai/classify', [
            'tracks' => [$track->id],
            'retry_empty' => false,
        ])->assertOk()->assertJsonPath('processed', 1);

        Http::assertSentCount(1);
    }

    public function test_single_api_request_cannot_include_more_than_ten_tracks(): void
    {
        $tracks = [];
        for ($index = 1; $index <= 11; $index++) {
            $tracks[] = $this->makeTrack('Sample '.$index)->id;
        }

        Http::fake();
        $this->postJson('/ai/classify', [
            'tracks' => $tracks,
            'retry_empty' => false,
        ])->assertUnprocessable();

        Http::assertNothingSent();
    }

    public function test_malformed_audio_metadata_is_safely_encoded_before_openrouter_request(): void
    {
        config(['openrouter.key' => 'fake-secret', 'openrouter.model' => 'openai/gpt-4o-mini']);

        // The corrupted values emulate legacy/incorrectly decoded audio tags.
        $track = new Track([
            'title' => "Bad title \xC3\x28",
            'artist' => "Bad artist \xC3\x28",
            'album' => "Bad album \xC3\x28",
            'path' => 'invalid-metadata.mp3',
            'size' => 100,
            'file_mtime' => 1,
        ]);
        $track->id = 1234;
        $genre = new Tag(['type' => 'genre', 'name' => "Bad genre \xC3\x28", 'slug' => 'bad']);
        $track->setRelation('tags', collect([$genre]));

        $this->fakeClassification([
            ['id' => $track->id, 'genres' => ['Electronic'], 'moods' => ['Calm']],
        ]);

        $result = app(OpenRouterClassifier::class)->classify(collect([$track]));

        $this->assertSame(['Electronic'], $result['tracks'][$track->id]['genres']);
        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $payload = $request->data();
            $input = json_decode($payload['messages'][1]['content'], true, 512, JSON_THROW_ON_ERROR);
            $item = $input['tracks'][0];

            $this->assertTrue(mb_check_encoding($payload['messages'][1]['content'], 'UTF-8'));
            $this->assertStringContainsString('�', $item['title']);
            $this->assertStringContainsString('�', $item['artist']);
            $this->assertStringContainsString('�', $item['album']);
            $this->assertStringContainsString('�', $item['existing_genres'][0]);

            return true;
        });
    }
}
