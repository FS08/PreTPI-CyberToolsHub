<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base API URL
    |--------------------------------------------------------------------------
    |
    | Default urlscan.io API endpoint.
    |
    */
    'base' => env('URLSCAN_BASE', 'https://urlscan.io/api'),

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    | Needed for submissions (especially private/unlisted scans).
    | Optional for search queries.
    |
    */
    'key' => env('URLSCAN_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Visibility for submissions
    |--------------------------------------------------------------------------
    |
    | 'public'   = visible to everyone
    | 'unlisted' = only visible via direct link (still stored on urlscan)
    | 'private'  = requires API key, not shared
    |
    */
    'visibility' => env('URLSCAN_VISIBILITY', 'unlisted'),

    /*
    |--------------------------------------------------------------------------
    | Feature toggle
    |--------------------------------------------------------------------------
    |
    | Allow disabling urlscan integration easily.
    |
    */
    'enabled' => env('URLSCAN_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    |
    | Max URLs per scan (to avoid abuse/mistakes).
    | Sleep time in milliseconds between API calls (rate limiting).
    |
    */
    'max_per_scan'   => env('URLSCAN_MAX_PER_SCAN', 5),
    'rate_sleep_ms'  => env('URLSCAN_RATE_SLEEP_MS', 300),  // 0.3s

    // NEW: do we wait for a finished result synchronously? (not recommended for web requests)
    'wait_result'           => env('URLSCAN_WAIT_RESULT', false),
    'poll_interval_seconds' => env('URLSCAN_POLL_INTERVAL', 2),
    'poll_timeout_seconds'  => env('URLSCAN_POLL_TIMEOUT', 20),

];