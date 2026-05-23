<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | M-19: previously no config/cors.php existed, so Laravel's default allowed
    | every origin ('*'). Cross-origin browser access is now restricted to the
    | platform domain (and any extra origins listed in CORS_ALLOWED_ORIGINS).
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

    // The platform domain and any subdomain (https or http). Override the
    // base domain with CORS_ALLOWED_DOMAIN if it ever differs from APP_URL.
    'allowed_origins_patterns' => [
        '#^https?://([a-z0-9-]+\.)?'.preg_quote(
            env('CORS_ALLOWED_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'),
            '#'
        ).'$#i',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
