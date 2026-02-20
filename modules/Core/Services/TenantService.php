<?php

namespace Modules\Core\Services;

use Modules\Core\Models\Tenant;
use Modules\Core\Models\TenantStatus;
use Modules\Core\Models\TenantUsage;
use XLinic\Framework\Core\Tenancy\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TenantService
{
    public function __construct(
        protected TenantManager $tenantManager
    ) {}

    public function create(array $data): Tenant
    {
        return DB::transaction(function () use ($data) {
            // Create tenant record
            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']),
                'domain' => $data['domain'] ?? null,
                'contact_name' => $data['contact_name'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'status' => $data['status'] ?? TenantStatus::PENDING,
                'settings' => $data['settings'] ?? [],
                'features' => $data['features'] ?? [],
            ]);

            // Create tenant database schema
            $this->createTenantDatabase($tenant);

            // Initialize tenant usage tracking
            TenantUsage::create([
                'tenant_id' => $tenant->id,
                'users' => 0,
                'patients' => 0,
                'appointments' => 0,
                'treatments' => 0,
                'storage_mb' => 0,
                'api_requests' => 0,
                'email_sent' => 0,
                'sms_sent' => 0,
                'reports_generated' => 0,
            ]);

            // Set tenant as active if requested
            if (($data['status'] ?? TenantStatus::PENDING) === TenantStatus::ACTIVE) {
                $this->activate($tenant);
            }

            return $tenant;
        });
    }

    public function update(Tenant $tenant, array $data): Tenant
    {
        return DB::transaction(function () use ($tenant, $data) {
            // Update tenant data
            $tenant->update($data);

            // Handle domain change
            if (isset($data['domain']) && $data['domain'] !== $tenant->domain) {
                $this->updateTenantDomain($tenant, $data['domain']);
            }

            // Handle status change
            if (isset($data['status']) && $data['status'] !== $tenant->status) {
                match ($data['status']) {
                    TenantStatus::ACTIVE => $this->activate($tenant),
                    TenantStatus::SUSPENDED => $this->suspend($tenant),
                    TenantStatus::INACTIVE => $this->deactivate($tenant),
                    default => null,
                };
            }

            return $tenant->fresh();
        });
    }

    public function delete(Tenant $tenant, bool $forceDelete = false): bool
    {
        return DB::transaction(function () use ($tenant, $forceDelete) {
            // Deactivate tenant first
            $this->deactivate($tenant);

            if ($forceDelete) {
                // Drop tenant database schema
                $this->dropTenantDatabase($tenant);

                // Force delete tenant record
                return $tenant->forceDelete();
            } else {
                // Soft delete tenant
                return $tenant->delete();
            }
        });
    }

    public function activate(Tenant $tenant): Tenant
    {
        $tenant->update(['status' => TenantStatus::ACTIVE]);

        // Run tenant-specific setup if needed
        $this->tenantManager->runForTenant($tenant, function () {
            // Seed initial data if needed
            $this->seedTenantData();
        });

        return $tenant;
    }

    public function suspend(Tenant $tenant): Tenant
    {
        $tenant->update(['status' => TenantStatus::SUSPENDED]);

        // Log suspension
        activity()
            ->performedOn($tenant)
            ->withProperties(['reason' => 'Administrative suspension'])
            ->log('Tenant suspended');

        return $tenant;
    }

    public function deactivate(Tenant $tenant): Tenant
    {
        $tenant->update(['status' => TenantStatus::INACTIVE]);

        return $tenant;
    }

    public function createTenantDatabase(Tenant $tenant): void
    {
        $schemaName = $tenant->database_name;
        $connectionName = "tenant_{$tenant->id}";

        Log::info('Creating tenant database schema', [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'schema_name' => $schemaName,
        ]);

        // Create schema in the main database
        DB::statement("CREATE SCHEMA IF NOT EXISTS \"{$schemaName}\"");

        Log::info('Schema created successfully', ['schema_name' => $schemaName]);

        // Configure tenant connection dynamically
        config([
            "database.connections.{$connectionName}" => [
                'driver' => 'pgsql',
                'host' => $tenant->database_host ?? config('database.connections.pgsql.host'),
                'port' => $tenant->database_port ?? config('database.connections.pgsql.port'),
                'database' => config('database.connections.pgsql.database'),
                'username' => $tenant->database_username ?? config('database.connections.pgsql.username'),
                'password' => $tenant->database_password ?? config('database.connections.pgsql.password'),
                'charset' => 'utf8',
                'prefix' => '',
                'prefix_indexes' => true,
                'search_path' => $schemaName,
                'sslmode' => 'prefer',
            ]
        ]);

        // Purge the connection to force Laravel to use new config
        DB::purge($connectionName);

        // Run migrations for tenant using the tenant connection
        $this->tenantManager->runForTenant($tenant, function () use ($connectionName, $schemaName) {
            // Get all module migration paths
            $migrationPaths = $this->getModuleMigrationPaths();

            Log::info('Running tenant migrations', [
                'schema_name' => $schemaName,
                'connection' => $connectionName,
                'paths' => $migrationPaths,
            ]);

            foreach ($migrationPaths as $path) {
                if (is_dir(base_path($path))) {
                    Log::info('Running migrations from path', ['path' => $path]);

                    Artisan::call('migrate', [
                        '--database' => $connectionName,
                        '--path' => $path,
                        '--force' => true,
                    ]);

                    Log::info('Migration output', ['output' => Artisan::output()]);
                }
            }
        });

        Log::info('Tenant database provisioning completed', [
            'tenant_id' => $tenant->id,
            'schema_name' => $schemaName,
        ]);
    }

    /**
     * Get all module migration paths.
     */
    protected function getModuleMigrationPaths(): array
    {
        $paths = [];
        $modulesPath = base_path('modules');

        if (is_dir($modulesPath)) {
            $modules = scandir($modulesPath);
            foreach ($modules as $module) {
                if ($module === '.' || $module === '..') {
                    continue;
                }

                // Check Database/Migrations first (preferred)
                $dbMigrationsPath = "modules/{$module}/Database/Migrations";
                if (is_dir(base_path($dbMigrationsPath))) {
                    $paths[] = $dbMigrationsPath;
                    continue;
                }

                // Fallback to direct Migrations folder
                $migrationsPath = "modules/{$module}/Migrations";
                if (is_dir(base_path($migrationsPath))) {
                    $paths[] = $migrationsPath;
                }
            }
        }

        return $paths;
    }

    public function dropTenantDatabase(Tenant $tenant): void
    {
        $schemaName = $tenant->database_name;

        // Drop schema and all its contents
        DB::statement("DROP SCHEMA IF EXISTS \"{$schemaName}\" CASCADE");
    }

    public function updateTenantDomain(Tenant $tenant, ?string $newDomain): void
    {
        $oldDomain = $tenant->domain;
        $tenant->update(['domain' => $newDomain]);

        activity()
            ->performedOn($tenant)
            ->withProperties([
                'old_domain' => $oldDomain,
                'new_domain' => $newDomain,
            ])
            ->log('Tenant domain updated');
    }

    public function getTenantByDomain(string $domain): ?Tenant
    {
        return Tenant::where('domain', $domain)
            ->where('status', TenantStatus::ACTIVE)
            ->first();
    }

    public function getTenantBySlug(string $slug): ?Tenant
    {
        return Tenant::where('slug', $slug)
            ->where('status', TenantStatus::ACTIVE)
            ->first();
    }

    public function validateTenantAccess(Tenant $tenant, $user = null): bool
    {
        $user = $user ?? auth()->user();

        if (!$user) {
            return false;
        }

        // Super admin can access all tenants
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Check if user belongs to tenant
        return $user->tenant_id === $tenant->id;
    }

    public function getTenantUsageStats(Tenant $tenant): array
    {
        $usage = $tenant->usage;

        if (!$usage) {
            return [];
        }

        return [
            'users' => [
                'current' => $usage->users,
                'limit' => $tenant->max_users,
                'percentage' => $usage->getUsagePercentage('users'),
            ],
            'patients' => [
                'current' => $usage->patients,
                'limit' => $tenant->max_patients,
                'percentage' => $usage->getUsagePercentage('patients'),
            ],
            'storage' => [
                'current' => $usage->storage_mb,
                'limit' => $tenant->max_storage_mb,
                'percentage' => $usage->getUsagePercentage('storage_mb'),
            ],
        ];
    }

    public function seedTenantData(): void
    {
        // Create default admin user for tenant
        // This would be implemented based on specific requirements
    }

    public function exportTenantData(Tenant $tenant, array $options = []): array
    {
        return $this->tenantManager->runForTenant($tenant, function () use ($options) {
            $data = [];

            // Export based on options
            if ($options['include_users'] ?? true) {
                $data['users'] = \Modules\Auth\Models\User::all()->toArray();
            }

            // Add other exports as needed

            return $data;
        });
    }

    public function importTenantData(Tenant $tenant, array $data): void
    {
        $this->tenantManager->runForTenant($tenant, function () use ($data) {
            // Import data into tenant context
            // Implementation would depend on specific requirements
        });
    }

    public function duplicateTenant(Tenant $sourceTenant, array $newTenantData): Tenant
    {
        return DB::transaction(function () use ($sourceTenant, $newTenantData) {
            // Export source tenant data
            $data = $this->exportTenantData($sourceTenant);

            // Create new tenant
            $newTenant = $this->create($newTenantData);

            // Import data to new tenant
            $this->importTenantData($newTenant, $data);

            return $newTenant;
        });
    }
}