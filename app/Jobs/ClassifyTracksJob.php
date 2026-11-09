<?php

namespace App\Jobs;

use App\Exceptions\OpenRouterRequestException;
use App\Models\AiUsageLog;
use App\Models\MetadataSuggestion;
use App\Models\Track;
use App\Services\OpenRouterClassifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

/**
 * One paid classification batch: sends the selected tracks to OpenRouter,
 * stores the pending suggestions and journals the token usage.
 *
 * Resilience policy:
 * - `$tries`/`backoff()` bound the number of attempts of one batch and the
 *   pause between them; only 429, 5xx and connection timeouts are retried.
 * - every failed call is written to `ai_usage_logs` with `outcome=failed`,
 *   the upstream error code and a short reason (never the API key or the
 *   request payload).
 * - outgoing calls pass a client-side rate limit gate. The gate waits for a
 *   free slot instead of releasing the batch, because the HTTP endpoint
 *   dispatches this job synchronously and the sync driver cannot re-queue work.
 */
class ClassifyTracksJob implements ShouldQueue
{
    use Queueable;

    public int $tries;

    /** @var array<int, int> */
    public array $trackIds;

    /**
     * @param  array<int, int|string>  $trackIds
     */
    public function __construct(array $trackIds)
    {
        $this->trackIds = array_values(array_map('intval', $trackIds));
        $this->tries = max(1, (int) config('openrouter.tries', 3));
    }

    /**
     * Pauses (in seconds) between the attempts of one batch.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return array_map('intval', (array) config('openrouter.backoff', [5, 20]));
    }

    /**
     * Runs the batch and returns the classifier reply (usage + proposals), so an
     * inline dispatch can answer the HTTP request without a second query.
     *
     * @return array<string, mixed>
     */
    public function handle(OpenRouterClassifier $classifier): array
    {
        $tracks = Track::query()
            ->whereIn('id', $this->trackIds)
            ->where('is_missing', false)
            ->with('tags')
            ->get();

        if ($tracks->count() !== count($this->trackIds)) {
            throw new RuntimeException('Выбранные треки отсутствуют.');
        }

        $attempts = max(1, $this->tries);

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $this->awaitThrottleSlot();

            try {
                $result = $classifier->classify($tracks);
                $this->persist($tracks, $result);

                return $result;
            } catch (OpenRouterRequestException $e) {
                $this->logFailedCall($e);

                if (! $e->retryable || $attempt === $attempts) {
                    throw $e;
                }

                $this->waitBeforeRetry($attempt);
            }
        }

        throw new RuntimeException('OpenRouter classification attempts exhausted.');
    }

    /**
     * Waits for a free client-side rate limit slot. Without the wait the sync
     * dispatch would silently lose the batch (the sync queue ignores releases).
     */
    private function awaitThrottleSlot(): void
    {
        $perMinute = (int) config('openrouter.throttle_per_minute', 0);
        if ($perMinute <= 0) {
            return;
        }

        $key = 'openrouter:classify';
        $deadline = time() + max(0, (int) config('openrouter.throttle_max_wait', 60));

        while (RateLimiter::tooManyAttempts($key, $perMinute)) {
            $wait = min((int) RateLimiter::availableIn($key), 5);
            if ($wait <= 0 || time() >= $deadline) {
                break;
            }
            sleep($wait);
        }

        RateLimiter::hit($key, 60);
    }

    private function waitBeforeRetry(int $attempt): void
    {
        $delays = $this->backoff();
        $delay = $delays[$attempt - 1] ?? ($delays === [] ? 0 : end($delays));

        if ($delay > 0) {
            sleep((int) $delay);
        }
    }

    /**
     * Writes a failed paid call to the journal. Only the code and a short
     * reason are stored: no API key, no request payload, no response body.
     */
    private function logFailedCall(OpenRouterRequestException $e): void
    {
        AiUsageLog::create([
            'provider' => 'openrouter',
            'model' => (string) (config('openrouter.model') ?: 'unknown'),
            'track_ids' => $this->trackIds,
            'prompt_tokens' => null,
            'completion_tokens' => null,
            'cost_usd' => null,
            'outcome' => 'failed',
            'error_code' => $e->errorCode,
            'error_message' => OpenRouterRequestException::shortReason($e->getMessage()),
        ]);
    }

    /**
     * @param  Collection<int, Track>  $tracks
     * @param  array<string, mixed>  $result
     */
    private function persist($tracks, array $result): void
    {
        DB::transaction(function () use ($tracks, $result) {
            foreach ($tracks as $track) {
                $proposal = $result['tracks'][$track->id];
                MetadataSuggestion::updateOrCreate(
                    ['track_id' => $track->id, 'provider' => 'openrouter'],
                    [
                        'model' => $result['model'],
                        'genres' => $proposal['genres'],
                        'moods' => $proposal['moods'],
                        'status' => 'pending',
                        'error' => null,
                    ],
                );
            }

            AiUsageLog::create([
                'provider' => 'openrouter',
                'model' => $result['model'],
                'track_ids' => $tracks->pluck('id')->all(),
                'prompt_tokens' => $result['usage']['prompt_tokens'],
                'completion_tokens' => $result['usage']['completion_tokens'],
                'cost_usd' => $result['usage']['cost_usd'],
                'outcome' => 'completed',
                'error_code' => null,
                'error_message' => null,
            ]);
        });
    }
}
