<?php

return [
    'name' => 'MobileApi',
    'version' => 'v2',

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'default' => [
            'max_attempts' => 60,
            'decay_minutes' => 1,
        ],
        'auth' => [
            'max_attempts' => 5,
            'decay_minutes' => 1,
        ],
        'otp' => [
            'max_attempts' => 3,
            'decay_minutes' => 5,
        ],
        // SECURITY: Stricter rate limiting for tenant discovery
        // to prevent enumeration attacks
        'discovery' => [
            'max_attempts' => 10,
            'decay_minutes' => 1,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Configuration
    |--------------------------------------------------------------------------
    */
    'tokens' => [
        'staff' => [
            'name' => 'staff-mobile-token',
            'abilities' => ['staff:*'],
            'expiration_days' => 30,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */
    'pagination' => [
        'default_per_page' => 15,
        'max_per_page' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | SDUI Configuration
    |--------------------------------------------------------------------------
    */
    'sdui' => [
        'version' => '1.0.0',
        'cache_ttl' => 300, // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Geofence Configuration
    |--------------------------------------------------------------------------
    */
    'geofence' => [
        'default_radius_meters' => 100,
        'max_radius_meters' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | App Code Configuration
    |--------------------------------------------------------------------------
    */
    'app_codes' => [
        'default_length' => 8,
        'default_expiry_days' => null, // null = never expires
        'max_uses' => null, // null = unlimited
    ],

    /*
    |--------------------------------------------------------------------------
    | Deep Links
    |--------------------------------------------------------------------------
    */
    'deep_links' => [
        'scheme' => env('MOBILE_APP_SCHEME', 'xlinic'),
        'web_base_url' => env('MOBILE_APP_WEB_URL', 'https://xforcehr.com/app'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Configuration
    |--------------------------------------------------------------------------
    |
    | Firebase Cloud Messaging settings for push notifications.
    | The credentials file should be downloaded from Firebase Console:
    | Project Settings -> Service Accounts -> Generate New Private Key
    |
    */
    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS', storage_path('firebase-credentials.json')),
        'project_id' => env('FIREBASE_PROJECT_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Push Notification Settings
    |--------------------------------------------------------------------------
    */
    'push_notifications' => [
        'enabled' => env('PUSH_NOTIFICATIONS_ENABLED', true),
        'queue' => env('PUSH_NOTIFICATIONS_QUEUE', 'notifications'),
    ],
];
