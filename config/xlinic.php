<?php

return [

    /*
    |--------------------------------------------------------------------------
    | XLinic Framework Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration options for the XLinic framework
    | kernel and module system.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Application Metadata
    |--------------------------------------------------------------------------
    */
    'app' => [
        'name' => env('APP_NAME', 'XLinic'),
        'version' => '1.0.0',
        'locale' => env('APP_LOCALE', 'en'),
        'fallback_locale' => env('APP_FALLBACK_LOCALE', 'ar'),
        'timezone' => env('APP_TIMEZONE', 'Africa/Cairo'),
        'currency' => env('APP_CURRENCY', 'EGP'),
        'tax_rate' => env('APP_TAX_RATE', 14.00), // Egyptian VAT
    ],

    /*
    |--------------------------------------------------------------------------
    | Module System Configuration
    |--------------------------------------------------------------------------
    */
    'modules' => [
        'path' => base_path('modules'),
        'manifest_file' => 'module.json',
        'auto_discovery' => true,
        'cache_manifests' => env('XLINIC_CACHE_MODULES', true),
        'cache_key' => 'xlinic:modules',
        'cache_ttl' => 3600, // 1 hour
        'dependency_resolution' => 'topological', // Kahn's algorithm
        'required_modules' => ['core', 'auth'], // Always active
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy Configuration
    |--------------------------------------------------------------------------
    */
    'tenancy' => [
        'enabled' => true,
        'isolation_method' => 'schema', // schema, database, or connection
        'tenant_model' => \Modules\Core\Models\Tenant::class,
        'schema_prefix' => 'tenant_',
        'connection_name' => 'tenant',
        'cache_prefix' => 'tenant_',
        'storage_prefix' => 'tenant_',
        'queue_prefix' => 'tenant_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    */
    'security' => [
        'record_policies_enabled' => true,
        'field_access_enabled' => true,
        'audit_log_enabled' => true,
        'permission_cache_ttl' => 3600,
        'role_cache_ttl' => 3600,
        'default_permissions' => [],
        'super_admin_role' => 'super_admin',
        'tenant_admin_role' => 'admin',
    ],

    /*
    |--------------------------------------------------------------------------
    | View Extensions Configuration
    |--------------------------------------------------------------------------
    */
    'views' => [
        'extensions_enabled' => true,
        'extension_cache_ttl' => 1800, // 30 minutes
        'form_extension_positions' => [
            'tabs', 'sidebar', 'after_main', 'before_main'
        ],
        'supported_resources' => [
            'form', 'table', 'widget', 'dashboard'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sequence Generation
    |--------------------------------------------------------------------------
    */
    'sequences' => [
        'enabled' => true,
        'default_padding' => 6,
        'default_prefix' => '',
        'reset_on_year' => true,
        'cache_enabled' => true,
        'cache_ttl' => 86400, // 24 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | Quota System
    |--------------------------------------------------------------------------
    */
    'quotas' => [
        'enabled' => true,
        'soft_limit_threshold' => 80, // Percentage
        'hard_limit_enforcement' => true,
        'usage_tracking_enabled' => true,
        'storage_tracking_enabled' => true,
        'overage_billing_enabled' => false,
        'notification_thresholds' => [75, 90, 100],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporting System
    |--------------------------------------------------------------------------
    */
    'reports' => [
        'enabled' => true,
        'cache_enabled' => true,
        'cache_ttl' => 900, // 15 minutes
        'export_formats' => ['pdf', 'excel', 'csv'],
        'max_records' => 10000,
        'async_generation' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation Configuration
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        'cache_enabled' => true,
        'cache_ttl' => 1800, // 30 minutes
        'default_groups' => [
            'dashboard' => ['label' => 'Dashboard', 'icon' => 'heroicon-o-home', 'order' => 1],
            'crm' => ['label' => 'CRM', 'icon' => 'heroicon-o-users', 'order' => 10],
            'operations' => ['label' => 'Operations', 'icon' => 'heroicon-o-calendar', 'order' => 20],
            'sales' => ['label' => 'Sales', 'icon' => 'heroicon-o-currency-dollar', 'order' => 30],
            'financial' => ['label' => 'Financial', 'icon' => 'heroicon-o-calculator', 'order' => 40],
            'inventory' => ['label' => 'Inventory', 'icon' => 'heroicon-o-cube', 'order' => 50],
            'marketing' => ['label' => 'Marketing', 'icon' => 'heroicon-o-megaphone', 'order' => 60],
            'hr' => ['label' => 'HR', 'icon' => 'heroicon-o-user-group', 'order' => 70],
            'reports' => ['label' => 'Reports', 'icon' => 'heroicon-o-chart-bar', 'order' => 80],
            'settings' => ['label' => 'Settings', 'icon' => 'heroicon-o-cog-6-tooth', 'order' => 90],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    */
    'database' => [
        'uuid_version' => 7, // Use UUID v7 for ordered UUIDs
        'soft_deletes_enabled' => true,
        'audit_timestamps' => true,
        'connection_timeout' => 30,
        'query_timeout' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Egyptian Market Defaults
    |--------------------------------------------------------------------------
    */
    'egypt' => [
        'currency' => 'EGP',
        'tax_rate' => 14.00, // VAT percentage
        'tax_inclusive' => true,
        'locale' => 'ar_EG',
        'timezone' => 'Africa/Cairo',
        'date_format' => 'Y-m-d',
        'time_format' => 'H:i',
        'datetime_format' => 'Y-m-d H:i:s',
        'number_format' => [
            'decimals' => 2,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
        ],
        'phone_country_code' => '+20',
        'address_format' => 'street, district, city, governorate',
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    */
    'features' => [
        'activity_log' => true,
        'audit_trail' => true,
        'two_factor_auth' => true,
        'portal_access' => true,
        'api_access' => true,
        'webhooks' => true,
        'real_time_updates' => true,
        'file_uploads' => true,
        'email_notifications' => true,
        'sms_notifications' => true,
        'whatsapp_notifications' => true,
        'social_media_integration' => true,
        'payment_gateway_integration' => true,
        'backup_automation' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'default_ttl' => 3600, // 1 hour
        'permission_ttl' => 3600,
        'navigation_ttl' => 1800,
        'module_ttl' => 3600,
        'settings_ttl' => 1800,
        'quota_ttl' => 300, // 5 minutes
        'report_ttl' => 900, // 15 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    */
    'api' => [
        'enabled' => true,
        'version' => 'v1',
        'rate_limiting' => true,
        'throttle_requests' => 60, // per minute
        'pagination_default' => 25,
        'pagination_max' => 100,
        'response_format' => 'json',
        'include_meta' => true,
        'cors_enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'module_events' => true,
        'tenant_events' => true,
        'permission_events' => true,
        'quota_events' => true,
        'api_requests' => env('LOG_API_REQUESTS', false),
        'slow_queries' => env('LOG_SLOW_QUERIES', true),
        'failed_jobs' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Configuration
    |--------------------------------------------------------------------------
    */
    'development' => [
        'debug_mode' => env('APP_DEBUG', false),
        'query_logging' => env('DB_QUERY_LOG', false),
        'route_caching' => env('ROUTE_CACHE', true),
        'config_caching' => env('CONFIG_CACHE', true),
        'view_caching' => env('VIEW_CACHE', true),
        'telescope_enabled' => env('TELESCOPE_ENABLED', false),
        'horizon_enabled' => env('HORIZON_ENABLED', true),
    ],

];