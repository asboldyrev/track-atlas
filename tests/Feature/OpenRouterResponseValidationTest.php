<?php

namespace Tests\Feature;

use App\Models\MetadataSuggestion;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenRouterResponseValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['openrouter.key' => 'fake-secret', 'openrouter.model' => 'openai/gpt-4o-mini']);
    }

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

    /**
     * Fakes a 200 response whose message content is the given raw string,
     * so malformed or out-of-contract payloads can be exercised.
     */
    private function fakeRawContent(string $content): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'model' => 'openai/gpt-4o-mini',
                'choices' => [['message' => ['content' => $content]]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'cost' => 0.0],
            ]),
        ]);
    }

    public function test_broken_json_content_is_rejected_and_nothing_is_persisted(): void
    {
        $track = $this->makeTrack();
        $this->fakeRawContent('{"tracks": [ broken');

        $this->postJson('/ai/classify', ['tracks' => [$track->id]])
            ->assertStatus(422)
            ->assertJsonPath('message', 'OpenRouter returned invalid JSON.');

        $this->assertDatabaseCount('metadata_suggestions', 0);
        $this->assertDatabaseCount('ai_usage_logs', 0);
        Http::assertSentCount(1);
    }

    public function test_empty_label_lists_are_accepted_as_an_empty_result(): void
    {
        $track = $this->makeTrack();
        $this->fakeRawContent(json_encode([
            'tracks' => [['id' => $track->id, 'genres' => [], 'moods' => []]],
        ], JSON_THROW_ON_ERROR));

        $this->postJson('/ai/classify', ['tracks' => [$track->id]])
            ->assertOk()
            ->assertJsonPath('processed', 1);

        $suggestion = MetadataSuggestion::firstOrFail();
        $this->assertSame([], $suggestion->genres);
        $this->assertSame([], $suggestion->moods);
        $this->assertSame('pending', $suggestion->status);
    }

    public function test_more_than_five_labels_is_rejected(): void
    {
        $track = $this->makeTrack();
        $this->fakeRawContent(json_encode([
            'tracks' => [[
                'id' => $track->id,
                'genres' => ['A', 'B', 'C', 'D', 'E', 'F'],
                'moods' => ['Dark'],
            ]],
        ], JSON_THROW_ON_ERROR));

        $this->postJson('/ai/classify', ['tracks' => [$track->id]])
            ->assertStatus(422);

        $this->assertDatabaseCount('metadata_suggestions', 0);
        $this->assertDatabaseCount('ai_usage_logs', 0);
    }

    public function test_label_longer_than_eighty_characters_is_rejected(): void
    {
        $track = $this->makeTrack();
        $this->fakeRawContent(json_encode([
            'tracks' => [[
                'id' => $track->id,
                'genres' => [str_repeat('g', 81)],
                'moods' => [],
            ]],
        ], JSON_THROW_ON_ERROR));

        $this->postJson('/ai/classify', ['tracks' => [$track->id]])
            ->assertStatus(422);

        $this->assertDatabaseCount('metadata_suggestions', 0);
    }

    public function test_duplicate_labels_are_deduplicated_case_insensitively(): void
    {
        $track = $this->makeTrack();
        $this->fakeRawContent(json_encode([
            'tracks' => [[
                'id' => $track->id,
                'genres' => ['Darkwave', 'darkwave', 'Darkwave'],
                'moods' => ['Dark', 'dark'],
            ]],
        ], JSON_THROW_ON_ERROR));

        $this->postJson('/ai/classify', ['tracks' => [$track->id]])
            ->assertOk()
            ->assertJsonPath('processed', 1);

        $suggestion = MetadataSuggestion::firstOrFail();
        $this->assertSame(['Darkwave'], $suggestion->genres);
        $this->assertSame(['Dark'], $suggestion->moods);
    }
}
