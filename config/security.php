<?php

return [

    /*
    |--------------------------------------------------------------------------
    | IP Whitelist Configuration
    |--------------------------------------------------------------------------
    |
    | Configure IP whitelisting for different areas of the application.
    | Use CIDR notation (e.g., 192.168.1.0/24) or wildcards (192.168.1.*)
    |
    */

    'ip_whitelist' => [
        // Enable or disable IP whitelist globally
        'enabled' => env('IP_WHITELIST_ENABLED', false),

        // Global whitelist - these IPs are always allowed everywhere
        'global' => array_filter(explode(',', env('IP_WHITELIST_GLOBAL', ''))),

        // Admin panel whitelist
        'admin' => array_filter(explode(',', env('IP_WHITELIST_ADMIN', ''))),

        // API whitelist
        'api' => array_filter(explode(',', env('IP_WHITELIST_API', '*'))),

        // SuperAdmin platform whitelist
        'superadmin' => array_filter(explode(',', env('IP_WHITELIST_SUPERADMIN', ''))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication Enforcement
    |--------------------------------------------------------------------------
    |
    | Configure 2FA enforcement settings.
    |
    */

    '2fa' => [
        // Enable or disable 2FA enforcement
        'enforce' => env('2FA_ENFORCE', false),

        // Roles that must have 2FA enabled
        'enforce_for_roles' => [
            'super_admin',
            'admin',
            'owner',
            'manager',
        ],

        // Grace period in days before enforcement kicks in
        // Set to 0 to enforce immediately
        'grace_period_days' => env('2FA_GRACE_PERIOD_DAYS', 0),

        // Route name for 2FA setup page
        'setup_route' => 'filament.admin.auth.profile',
    ],

    /*
    |--------------------------------------------------------------------------
    | API Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure API rate limiting settings.
    |
    */

    'api_rate_limit' => [
        // Enable or disable API rate limiting
        'enabled' => env('API_RATE_LIMIT_ENABLED', true),

        // Default limits per subscription tier
        'tiers' => [
            'free' => [
                'requests' => 60,
                'minutes' => 1,
            ],
            'basic' => [
                'requests' => 120,
                'minutes' => 1,
            ],
            'professional' => [
                'requests' => 300,
                'minutes' => 1,
            ],
            'enterprise' => [
                'requests' => 1000,
                'minutes' => 1,
            ],
        ],

        // Special limits for sensitive endpoints
        'special_limits' => [
            'login' => ['requests' => 5, 'minutes' => 1],
            'register' => ['requests' => 3, 'minutes' => 1],
            'password_reset' => ['requests' => 3, 'minutes' => 5],
            'export' => ['requests' => 5, 'minutes' => 10],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Configure audit logging settings.
    |
    */

    'audit' => [
        // Enable or disable audit logging
        'enabled' => env('AUDIT_LOG_ENABLED', true),

        // Events to always log
        'always_log' => [
            'login',
            'logout',
            'failed_login',
            'password_change',
            'permission_change',
            'data_export',
            'data_delete',
            'settings_change',
            'payment',
            'refund',
        ],

        // HTTP methods to audit
        'methods' => ['POST', 'PUT', 'PATCH', 'DELETE'],

        // Routes to exclude from auditing
        'excluded_routes' => [
            'livewire/*',
            'broadcasting/*',
            '_debugbar/*',
            'horizon/*',
            'telescope/*',
            'health',
            'pulse/*',
        ],

        // Sensitive fields to mask / never write to logs.
        //
        // This is the single source of truth for both the HTTP AuditLogger
        // middleware (substring masking of request input) and the model
        // activity-log trait (HasActivity, exact-column exclusion). It must
        // therefore list the real database column names for PHI/PII and
        // secrets so they are never written to the activity log in plaintext.
        'sensitive_fields' => [
            // Auth / credential fields
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
            // Payment-card data
            'credit_card',
            'card_number',
            'cvv',
            // Identifiers / secrets
            'ssn',
            'national_id',
            'secret',
            'token',
            'api_key',
            // Contact PHI/PII (exact column names — HasActivity excludes by
            // exact match, so list every PHI column, not just a stem)
            'phone',
            'mobile',
            'phone_country_code',
            'secondary_phone',
            'emergency_contact_phone',
            'date_of_birth',
            'address',
        ],

        // Maximum days to retain audit logs (0 = forever)
        'retention_days' => env('AUDIT_LOG_RETENTION_DAYS', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Security
    |--------------------------------------------------------------------------
    |
    | Configure session security settings.
    |
    */

    'session' => [
        // Force re-authentication after this many minutes of inactivity
        'reauthenticate_after_minutes' => env('SESSION_REAUTH_MINUTES', 30),

        // Maximum concurrent sessions per user (0 = unlimited)
        'max_concurrent_sessions' => env('MAX_CONCURRENT_SESSIONS', 3),

        // Invalidate other sessions on password change
        'invalidate_on_password_change' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Policy
    |--------------------------------------------------------------------------
    |
    | Configure password security requirements.
    |
    */

    'password' => [
        // Minimum password length
        'min_length' => env('PASSWORD_MIN_LENGTH', 8),

        // Require mixed case
        'require_mixed_case' => true,

        // Require at least one number
        'require_numbers' => true,

        // Require at least one special character
        'require_special_chars' => true,

        // Prevent password reuse (number of previous passwords to check)
        'prevent_reuse' => env('PASSWORD_PREVENT_REUSE', 5),

        // Password expiry in days (0 = never expires)
        'expires_days' => env('PASSWORD_EXPIRES_DAYS', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Login Security
    |--------------------------------------------------------------------------
    |
    | Configure login security settings.
    |
    */

    'login' => [
        // Maximum failed login attempts before lockout
        'max_attempts' => env('LOGIN_MAX_ATTEMPTS', 5),

        // Lockout duration in minutes
        'lockout_minutes' => env('LOGIN_LOCKOUT_MINUTES', 15),

        // Enable CAPTCHA after this many failed attempts
        'captcha_after_attempts' => env('LOGIN_CAPTCHA_AFTER', 3),

        // Notify user of suspicious login (different IP/device)
        'notify_suspicious' => true,

        // Block login from known malicious IPs
        'block_malicious_ips' => true,
    ],

];
