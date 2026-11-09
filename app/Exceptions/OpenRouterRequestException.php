<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A transport-level failure of an OpenRouter call (HTTP error status or a
 * broken connection/timeout). Validation problems in an otherwise successful
 * reply stay plain RuntimeExceptions: they are never retried and never logged
 * as failed paid calls.
 *
 * The exception never carries the API key or the request payload; only a short
 * error code and the upstream status are kept for the ai_usage_logs journal.
 */
class OpenRouterRequestException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'openrouter_error',
        public readonly ?int $httpStatus = null,
        public readonly bool $retryable = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromStatus(int $status): self
    {
        return new self(
            message: 'OpenRouter HTTP '.$status,
            errorCode: 'http_'.$status,
            httpStatus: $status,
            // 429 (rate limit) and 5xx are transient; other 4xx are not.
            retryable: $status === 429 || $status >= 500,
        );
    }

    public static function fromConnectionFailure(Throwable $previous): self
    {
        $timedOut = str_contains(strtolower($previous->getMessage()), 'timed out')
            || str_contains(strtolower($previous->getMessage()), 'timeout');

        return new self(
            message: 'OpenRouter connection failed: '.self::shortReason($previous->getMessage()),
            errorCode: $timedOut ? 'timeout' : 'connection_error',
            httpStatus: null,
            retryable: true,
            previous: $previous,
        );
    }

    /**
     * Truncates and scrubs an upstream reason so it can be stored in the usage
     * journal: an API key is replaced and the message is capped.
     */
    public static function shortReason(string $reason): string
    {
        $reason = preg_replace('/sk-[A-Za-z0-9_\-]+/', 'sk-***', $reason) ?? '';
        $reason = trim(preg_replace('/\s+/', ' ', $reason) ?? '');

        return mb_substr($reason, 0, 180);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->errorCode,
            'http_status' => $this->httpStatus,
            'retryable' => $this->retryable,
            'message' => $this->getMessage(),
        ];
    }
}
