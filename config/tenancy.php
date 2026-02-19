<?php

declare(strict_types=1);

return [
    'tenant_model' => \Modules\Core\Models\Tenant::class,
    'id_generator' => \Stancl\Tenancy\UUIDGenerator::class,

    'domain_model' => \Stancl\Tenancy\Database\Models\Domain::class,

    'central_domains' => [
        'xlinic.test', // Platform admin
        '127.0.0.1',
        'localhost',
    ],

    'identification' => [
        \Stancl\Tenancy\Identification\DomainIdentification::class,
    ],

    'bootstrappers' => [
        \Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        \Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        \Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        \Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
        \Stancl\Tenancy\Bootstrappers\RedisTenancyBootstrapper::class,
    ],

    'database' => [
        'central_connection' => env('DB_CONNECTION', 'central'),

        'template_tenant_connection' => null,

        'tenant_connection_name' => 'tenant',

        'managers' => [
            'pgsql' => \Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLSchemaManager::class,
        ],

        'template_tenant_db' => null,
    ],

    'cache' => [
        'tag_base' => 'tenant',
    ],

    'filesystem' => [
        'suffix_base' => 'tenant',
        'disks' => [
            'public',
            'local',
            // 's3',
        ],
    ],

    'redis' => [
        'prefix_base' => 'tenant',
        'prefixed_connections' => [
            'default',
            'cache',
            'sessions',
            'queue',
        ],
    ],

    'features' => [
        // \Stancl\Tenancy\Features\UserImpersonation::class,
        // \Stancl\Tenancy\Features\TelescopeTags::class,
        \Stancl\Tenancy\Features\UniversalRoutes::class,
        \Stancl\Tenancy\Features\TenantConfig::class, // https://tenancyforlaravel.com/docs/v3/features/tenant-config
        // \Stancl\Tenancy\Features\CrossDomainRedirect::class, // https://tenancyforlaravel.com/docs/v3/features/cross-domain-redirect
        // \Stancl\Tenancy\Features\ViteBundler::class,
    ],

    'migration_parameters' => [
        '--force' => true,
        '--path' => [
            database_path('migrations/tenant'),
            'modules/*/Migrations',
        ],
        '--realpath' => true,
    ],

    'seeder_parameters' => [
        '--class' => 'DatabaseSeeder', // root seeder class
        '--force' => true,
    ],

    'queue_database_creation' => false,
    'queue_database_deletion' => false,

    'storage_driver' => null, // or 's3'

    'exempt_domains' => [
        'www',
        'mail',
        'ftp',
    ],

    'database_managers' => [
        'sqlite' => \Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class,
        'mysql' => \Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager::class,
        'pgsql' => \Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLSchemaManager::class,
    ],

    'universal_routes' => [
        'api/*',
        'webhooks/*',
        'admin/login',
        'admin/logout',
        'admin/register',
        'admin/password/*',
        'platform/*', // SuperAdmin panel
        'livewire/*',
        '_debugbar/*',
        'horizon/*',
        'telescope/*',
    ],

    // XLinic specific configuration
    'xlinic' => [
        'tenant_identification' => 'subdomain', // subdomain, domain, or path
        'default_schema_prefix' => 'tenant_',
        'auto_create_database' => true,
        'auto_migrate_on_create' => true,
        'auto_seed_on_create' => true,
        'default_features' => [
            'users',
            'patients',
            'appointments',
            'treatments',
            'inventory',
        ],
    ],
];