<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Integration credentials
    |--------------------------------------------------------------------------
    |
    | The public token is sent on every request; the hash token is the signing
    | secret and must stay server-side. Load both from the environment.
    |
    */

    'public_token' => env('PAYLINK_PUBLIC_TOKEN'),

    'hash_token' => env('PAYLINK_HASH_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Transport
    |--------------------------------------------------------------------------
    */

    'base_url' => env('PAYLINK_BASE_URL', 'https://pay.getpayin.com'),

    'timeout_ms' => (int) env('PAYLINK_TIMEOUT_MS', 30000),

    'max_retries' => (int) env('PAYLINK_MAX_RETRIES', 2),

    /*
    | Route requests through Laravel's HTTP client (the Http facade) instead of
    | the bundled cURL transport, so they inherit pooling and Http::fake().
    */
    'use_laravel_http' => (bool) env('PAYLINK_USE_LARAVEL_HTTP', true),

    /*
    |--------------------------------------------------------------------------
    | Log channel
    |--------------------------------------------------------------------------
    |
    | The log channel retries and rejected webhooks are written to. Define a
    | dedicated `paylink` channel in config/logging.php, or point this at any
    | existing channel.
    |
    */

    'log_channel' => env('PAYLINK_LOG_CHANNEL', 'stack'),
];
