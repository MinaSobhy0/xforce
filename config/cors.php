<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | M-19: previously no config/cors.php existed, so Laravel's default allowed
    | every origin ('*'). Cross-origin browser access is now restricted to the
    | x-linic.com domains (and any extra origins listed in CORS_ALLOWED_ORIGINS).
    | The mobile app uses native HTTP (not subject to CORS) and the web panels
    | are same-origin, so this does not affect legitimate clients. Credentials
    | stay disabled — the API uses bearer tokens, not cookies.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Explicit origins via env (comma-separated), e.g. CORS_ALLOWED_ORIGINS=https://foo.com,https://bar.com
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
    ))),

    // x-linic.com and any subdomain (https or http).
    'allowed_origins_patterns' => [
        '#^https?://([a-z0-9-]+\.)?x-linic\.com$#i',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
