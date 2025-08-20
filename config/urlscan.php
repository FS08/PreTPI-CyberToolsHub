<?php

return [
    // Base API URL
    'base'   => env('URLSCAN_BASE', 'https://urlscan.io/api'),

    // Your API key (optional for search, recommended for submit/private scans)
    'key'    => env('URLSCAN_API_KEY'),

    // Default public setting when submitting a URL (true = public)
    'public' => env('URLSCAN_PUBLIC', true),
];