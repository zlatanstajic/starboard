<?php

declare(strict_types=1);

return [
    'execution_enabled' => (bool) env('YOUTUBE_FETCH_ENABLED', false),
    'ui_enabled' => (bool) env('YOUTUBE_FETCH_UI_ENABLED', false),
    'transport' => env('YOUTUBE_FETCH_TRANSPORT', 'laravel-http'),
    'connect_timeout' => (int) env('YOUTUBE_FETCH_CONNECT_TIMEOUT', 5),
    'request_timeout' => (int) env('YOUTUBE_FETCH_REQUEST_TIMEOUT', 15),
    'job_timeout' => (int) env('YOUTUBE_FETCH_JOB_TIMEOUT', 45),
    'max_redirects' => (int) env('YOUTUBE_FETCH_MAX_REDIRECTS', 2),
    'max_response_bytes' => (int) env('YOUTUBE_FETCH_MAX_RESPONSE_BYTES', 2097152),
    'retry' => [
        'attempts' => (int) env('YOUTUBE_FETCH_RETRY_ATTEMPTS', 3),
        'base_seconds' => (int) env('YOUTUBE_FETCH_RETRY_BASE_SECONDS', 15),
        'factor' => (int) env('YOUTUBE_FETCH_RETRY_FACTOR', 2),
        'ceiling_seconds' => (int) env('YOUTUBE_FETCH_RETRY_CEILING_SECONDS', 900),
        'max_retry_after' => (int) env('YOUTUBE_FETCH_MAX_RETRY_AFTER', 900),
    ],
    'batch_limit' => (int) env('YOUTUBE_FETCH_BATCH_LIMIT', 50),
    'stagger_seconds' => (int) env('YOUTUBE_FETCH_STAGGER_SECONDS', 5),
    'daily_request_limit' => (int) env('YOUTUBE_FETCH_DAILY_REQUEST_LIMIT', 500),
    'blocked_cooldown_seconds' => (int) env('YOUTUBE_FETCH_BLOCKED_COOLDOWN_SECONDS', 3600),
    'queue' => env('YOUTUBE_FETCH_QUEUE', 'youtube'),
    'logging_channel' => env('YOUTUBE_FETCH_LOG_CHANNEL', 'stack'),
];
