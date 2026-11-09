<?php

return [
    'key' => env('OPENROUTER_API_KEY'),
    'model' => env('OPENROUTER_MODEL', 'openai/gpt-4o-mini'),
    'max_tracks' => 10,

    /*
     | How many times one classification batch is sent, and how many seconds to
     | wait between the attempts. Only 429 and 5xx replies and connection
     | timeouts are retried; a malformed reply is never re-sent (it would be
     | paid for again without a chance to succeed).
     */
    'tries' => (int) env('OPENROUTER_TRIES', 3),
    'backoff' => [5, 20],

    /*
     | Client-side throttle: at most this many OpenRouter calls per minute per
     | process. 0 disables the gate. The job waits for a free slot instead of
     | dropping the batch, because the HTTP endpoint dispatches it synchronously.
     */
    'throttle_per_minute' => (int) env('OPENROUTER_THROTTLE_PER_MINUTE', 20),
    'throttle_max_wait' => (int) env('OPENROUTER_THROTTLE_MAX_WAIT', 60),
];
