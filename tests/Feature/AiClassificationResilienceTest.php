<?php

namespace Tests\Feature;

use App\Exceptions\OpenRouterRequestException;
use App\Jobs\ClassifyTracksJob;
use App\Models\AiUsageLog;
use App\Models\MetadataSuggestion;
use App\Models\Track;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * B6: resilience of the paid OpenRouter classification.
 *
 * Every test fakes the HTTP layer; no request leaves the process.
 */
class AiClassificationResilienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'openrouter.key' => 'fake-secret',
            'openrouter.model' => 'openai/gpt-4o-mini',
            'openrouter.tries' => 3,
            'openrouter.backoff' => [0, 0],
            'openrouter.throttle_per_minute' => 0,
            'openrouter.throttle_max_wait' => 0,
        ]);
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
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function okBody(array $items): array
    {
        return [
            'model' => 'openai/gpt-4o-mini',
            'choices' => [[
                'message' => ['content' => json_encode(['tracks' => $items], JSON_THROW_ON_ERROR)],
            ]],
            'usage' => ['prompt_tokens' => 125, 'completion_tokens' => 35, 'cost' => 0.000048],
        ];
    }

    public function test_rate_limited_call_is_retried_and_the_batch_survives(): void
    {
        $track = $this->makeTrack();

        Http::fake([
            'openrouter.ai/*' => Http::sequence()
                ->pushStatus(429)
                ->push($this->okBody([
                    ['id' => $track->id, 'genres' => ['Synthwave'], 'moods' => ['Dark']],
                ]), 200),
        ]);

        $this->postJson('/ai/classify', ['tracks' => [$track->id]])
            ->assertOk()
            ->assertJsonPath('processed', 1)
            ->assertJsonPath('usage.prompt_tokens', 125);

        Http::assertSentCount(2);

        $this->assertSame(1, MetadataSuggestion::count());
        $this->assertSame(1, AiUsageLog::where('outcome', 'failed')->count());
        $this->assertSame(1, AiUsageLog::where('outcome', 'completed')->count());

        $failure = AiUsageLog::where('outcome', 'failed')->firstOrFail();
        $this->assertSame('http_429', $failure->error_code);
        $this->assertSame('OpenRouter HTTP 429', $failure->error_message);
        $this->assertSame([$track->id], $failure->track_ids);
        $this->assertNull($failure->prompt_tokens);
    }

    public function test_client_error_is_not_retried_and_is_journalled_once(): void
    {
        $track = $this->makeTrack();

        Http::fake(['openrouter.ai/*' => Http::response(['error' => ['message' => 'bad model']], 400)]);

        $this->postJson('/ai/classify', ['tracks' => [$track->id]])
            ->assertStatus(502)
            ->assertJsonPath('code', 'http_400');

        Http::assertSentCount(1);
        $this->assertSame(1, AiUsageLog::where('error_code', 'http_400')->count());
        $this->assertDatabaseCount('metadata_suggestions', 0);
    }

    public function test_server_error_is_retried_until_the_attempt_budget_is_spent(): void
    {
        $track = $this->makeTrack();

        Http::fake([
            'openrouter.ai/*' => Http::sequence()
                ->pushStatus(503)
                ->pushStatus(503)
                ->pushStatus(503),
        ]);

        $this->postJson('/ai/classify', ['tracks' => [$track->id]])
            ->assertStatus(502)
            ->assertJsonPath('code', 'http_503');

        Http::assertSentCount(3);
        $this->assertSame(3, AiUsageLog::where('outcome', 'failed')->count());
        $this->assertSame(0, AiUsageLog::where('outcome', 'completed')->count());
        $this->assertDatabaseCount('metadata_suggestions', 0);
    }

    public function test_timeout_is_retried_with_backoff_and_reported_as_a_timeout(): void
    {
        $track = $this->makeTrack();
        $attempts = 0;

        Http::fake(function () use (&$attempts) {
            $attempts++;

            throw new ConnectionException('cURL error 28: Operation timed out after 90000 milliseconds');
        });

        $this->postJson('/ai/classify', ['tracks' => [$track->id]])
            ->assertStatus(502)
            ->assertJsonPath('code', 'timeout');

        // A fake that raises a connection error is not recorded by the client,
        // so the attempt counter proves the retries.
        $this->assertSame(3, $attempts);
        $this->assertSame(3, AiUsageLog::where('error_code', 'timeout')->count());
        $this->assertDatabaseCount('metadata_suggestions', 0);
    }

    public function test_broken_json_is_rejected_without_a_retry_or_a_failed_journal_entry(): void
    {
        $track = $this->makeTrack();

        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => '{"tracks": [ broken']]],
            ], 200),
        ]);

        $this->postJson('/ai/classify', ['tracks' => [$track->id]])
            ->assertStatus(422)
            ->assertJsonPath('message', 'OpenRouter returned invalid JSON.');

        Http::assertSentCount(1);
        $this->assertDatabaseCount('ai_usage_logs', 0);
        $this->assertDatabaseCount('metadata_suggestions', 0);
    }

    public function test_failed_call_journal_never_contains_the_api_key_or_the_payload(): void
    {
        config(['openrouter.key' => 'sk-or-v1-supersecret-value']);
        $track = $this->makeTrack();

        Http::fake(['openrouter.ai/*' => Http::response(['error' => ['message' => 'rate limited']], 429)]);

        $this->postJson('/ai/classify', ['tracks' => [$track->id]])->assertStatus(502);

        $failure = AiUsageLog::where('outcome', 'failed')->firstOrFail();
        $this->assertSame('http_429', $failure->error_code);
        $this->assertSame('OpenRouter HTTP 429', $failure->error_message);

        $journal = json_encode(AiUsageLog::all()->toArray(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('supersecret', $journal);
        $this->assertStringNotContainsString('messages', $journal);
        $this->assertStringNotContainsString('response_format', $journal);
    }

    public function test_upstream_reason_is_scrubbed_and_capped_before_it_is_stored(): void
    {
        $reason = OpenRouterRequestException::shortReason(
            'connection failed while using sk-or-v1-0123456789abcdef key and '.str_repeat('x', 400)
        );

        $this->assertStringNotContainsString('0123456789abcdef', $reason);
        $this->assertStringContainsString('sk-***', $reason);
        $this->assertLessThanOrEqual(180, mb_strlen($reason));
    }

    public function test_throttle_gate_waits_for_a_slot_instead_of_dropping_the_batch(): void
    {
        config(['openrouter.throttle_per_minute' => 1, 'openrouter.throttle_max_wait' => 0]);
        RateLimiter::clear('openrouter:classify');
        RateLimiter::hit('openrouter:classify', 60);

        $track = $this->makeTrack();
        Http::fake([
            'openrouter.ai/*' => Http::response($this->okBody([
                ['id' => $track->id, 'genres' => ['Pop'], 'moods' => ['Energetic']],
            ]), 200),
        ]);

        $this->postJson('/ai/classify', ['tracks' => [$track->id]])
            ->assertOk()
            ->assertJsonPath('processed', 1);

        Http::assertSentCount(1);
        $this->assertSame(1, MetadataSuggestion::count());
        $this->assertSame(1, AiUsageLog::where('outcome', 'completed')->count());
    }

    public function test_retry_policy_is_declared_on_the_job_and_comes_from_config(): void
    {
        config(['openrouter.tries' => 4, 'openrouter.backoff' => [1, 2, 3]]);

        $job = new ClassifyTracksJob(['7', 8]);

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertSame(4, $job->tries);
        $this->assertSame([1, 2, 3], $job->backoff());
        $this->assertSame([7, 8], $job->trackIds);
    }
}
