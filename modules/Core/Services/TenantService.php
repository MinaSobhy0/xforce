<?php

namespace Modules\Core\Services;

use Modules\Core\Models\Tenant;
use Modules\Core\Models\TenantStatus;
use Modules\Core\Models\TenantUsage;
use XLinic\Framework\Core\Tenancy\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
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
                'services' => 0,
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

        // Grant permissions to the database user
        $dbUser = config('database.connections.pgsql.username');
        DB::statement("GRANT ALL ON SCHEMA \"{$schemaName}\" TO \"{$dbUser}\"");

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
            // Get tenant-specific migration files (excludes platform-only)
            $migrationFiles = $this->getTenantMigrationFiles();

            Log::info('Running tenant migrations', [
                'schema_name' => $schemaName,
                'connection' => $connectionName,
                'file_count' => count($migrationFiles),
            ]);

            foreach ($migrationFiles as $file) {
                $relativePath = str_replace(base_path() . '/', '', $file);

                Log::info('Running migration', ['file' => basename($file)]);

                Artisan::call('migrate', [
                    '--database' => $connectionName,
                    '--path' => $relativePath,
                    '--force' => true,
                ]);

                $output = trim(Artisan::output());
                if ($output) {
                    Log::info('Migration output', ['output' => $output]);
                }
            }
        });

        Log::info('Tenant database provisioning completed', [
            'tenant_id' => $tenant->id,
            'schema_name' => $schemaName,
        ]);

        // Seed default roles and permissions
        $this->seedDefaultRolesAndPermissions($tenant);

        // Create default branch for the tenant
        $this->createDefaultBranch($tenant);

        // Create owner user if contact_email is set
        if ($tenant->contact_email) {
            $this->createOwnerUser($tenant);
        }
    }

    /**
     * Seed default roles and permissions for a tenant.
     */
    protected function seedDefaultRolesAndPermissions(Tenant $tenant): void
    {
        $schemaName = $tenant->database_name;

        try {
            DB::statement("SET search_path TO \"{$schemaName}\"");

            // Define permissions (using Spatie's default structure)
            $permissionNames = [
                'system.view', 'system.manage',
                'users.view', 'users.create', 'users.edit', 'users.delete',
                'roles.view', 'roles.create', 'roles.edit',
                'patients.view', 'patients.create', 'patients.edit', 'patients.delete',
                'appointments.view', 'appointments.create', 'appointments.edit', 'appointments.delete',
                'services.view', 'services.create', 'services.edit',
                'billing.view', 'billing.create', 'billing.edit',
                'reports.view', 'reports.export',
                'profile.view', 'profile.edit',
            ];

            foreach ($permissionNames as $permName) {
                $existing = DB::table('permissions')->where('name', $permName)->where('guard_name', 'web')->first();
                if (!$existing) {
                    DB::table('permissions')->insert([
                        'name' => $permName,
                        'guard_name' => 'web',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Get all permission IDs for super_admin
            $allPermissionIds = DB::table('permissions')->pluck('id')->toArray();

            // Define roles (using Spatie's default structure)
            $roles = ['super_admin', 'admin', 'manager', 'doctor', 'receptionist'];

            foreach ($roles as $roleName) {
                $existing = DB::table('roles')->where('name', $roleName)->where('guard_name', 'web')->first();
                if (!$existing) {
                    $roleId = DB::table('roles')->insertGetId([
                        'name' => $roleName,
                        'guard_name' => 'web',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Assign all permissions to super_admin
                    if ($roleName === 'super_admin') {
                        foreach ($allPermissionIds as $permId) {
                            DB::table('role_has_permissions')->insert([
                                'permission_id' => $permId,
                                'role_id' => $roleId,
                            ]);
                        }
                    }
                }
            }

            Log::info('Default roles and permissions seeded', ['tenant_id' => $tenant->id]);
            DB::statement("SET search_path TO public");

        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            Log::error('Failed to seed roles and permissions', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create a default branch for a tenant.
     */
    public function createDefaultBranch(Tenant $tenant): ?string
    {
        $schemaName = $tenant->database_name;

        try {
            // Switch to tenant schema
            DB::statement("SET search_path TO \"{$schemaName}\"");

            // Check if any branch already exists
            $existingBranch = DB::table('branches')->first();
            if ($existingBranch) {
                Log::info('Default branch already exists', ['tenant_id' => $tenant->id, 'branch_id' => $existingBranch->id]);
                DB::statement("SET search_path TO public");
                return $existingBranch->id;
            }

            $branchId = Str::uuid()->toString();

            // Create the default main branch
            DB::table('branches')->insert([
                'id' => $branchId,
                'tenant_id' => $tenant->id,
                'name' => $tenant->name . ' - Main Branch',
                'code' => 'MAIN',
                'address' => $tenant->settings['address'] ?? null,
                'city' => $tenant->settings['city'] ?? null,
                'phone' => $tenant->contact_phone,
                'email' => $tenant->contact_email,
                'timezone' => $tenant->timezone ?? 'Africa/Cairo',
                'currency_code' => $tenant->currency ?? 'EGP',
                'is_active' => true,
                'is_main' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('Default branch created', [
                'tenant_id' => $tenant->id,
                'branch_id' => $branchId,
            ]);

            DB::statement("SET search_path TO public");
            return $branchId;

        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            Log::error('Failed to create default branch', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Create the owner user for a tenant.
     *
     * @return array{user_id: string, password: string}|null
     */
    public function createOwnerUser(Tenant $tenant, ?string $password = null): ?array
    {
        if (!$tenant->contact_email) {
            Log::warning('Cannot create owner user: no contact_email set', ['tenant_id' => $tenant->id]);
            return null;
        }

        $schemaName = $tenant->database_name;
        $password = $password ?? Str::random(12);

        try {
            // Switch to tenant schema
            DB::statement("SET search_path TO \"{$schemaName}\"");

            // Check if user with this email already exists
            $existing = DB::table('users')->where('email', $tenant->contact_email)->first();
            if ($existing) {
                // Update password for existing user
                DB::table('users')->where('id', $existing->id)->update([
                    'password' => Hash::make($password),
                    'updated_at' => now(),
                ]);

                // Ensure user is assigned to main branch
                $this->ensureUserAssignedToMainBranch($tenant, $existing->id);

                Log::info('Owner user password reset', ['tenant_id' => $tenant->id, 'email' => $tenant->contact_email]);
                DB::statement("SET search_path TO public");

                // Also update password in public schema for owner portal
                $this->updateOwnerUserPasswordInPublicSchema($tenant->contact_email, $password);

                // Update tenant with owner_user_id and store password
                $settings = $tenant->settings ?? [];
                $settings['initial_owner_password'] = $password;
                $settings['initial_owner_created_at'] = now()->toISOString();
                $updateData = ['settings' => $settings];
                if (!$tenant->owner_user_id) {
                    $updateData['owner_user_id'] = $existing->id;
                }
                $tenant->update($updateData);

                return ['user_id' => $existing->id, 'password' => $password];
            }

            $userId = Str::uuid()->toString();
            $nameParts = explode('@', $tenant->contact_email);
            $firstName = $tenant->contact_name ? explode(' ', $tenant->contact_name)[0] : ucfirst($nameParts[0]);
            $lastName = $tenant->contact_name && str_contains($tenant->contact_name, ' ')
                ? trim(substr($tenant->contact_name, strpos($tenant->contact_name, ' ')))
                : 'Admin';

            // Insert user
            DB::table('users')->insert([
                'id' => $userId,
                'tenant_id' => $tenant->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $tenant->contact_email,
                'username' => $nameParts[0],
                'phone' => $tenant->contact_phone,
                'password' => Hash::make($password),
                'status' => 'active',
                'language' => $tenant->locale ?? 'ar',
                'timezone' => $tenant->timezone ?? 'Africa/Cairo',
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assign super_admin role if roles table exists
            $superAdminRole = null;
            try {
                $superAdminRole = DB::table('roles')->where('name', 'super_admin')->first();
                if ($superAdminRole) {
                    DB::table('model_has_roles')->insert([
                        'role_id' => $superAdminRole->id,
                        'model_type' => 'Modules\\Auth\\Models\\User',
                        'model_id' => $userId,
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Could not assign role to owner user', ['error' => $e->getMessage()]);
            }

            // Assign owner user to the main branch
            try {
                $mainBranch = DB::table('branches')->where('is_main', true)->first();
                if ($mainBranch && $superAdminRole) {
                    DB::table('user_branch_roles')->insert([
                        'id' => Str::uuid()->toString(),
                        'tenant_id' => $tenant->id,
                        'user_id' => $userId,
                        'branch_id' => $mainBranch->id,
                        'role_id' => $superAdminRole->id,
                        'is_primary' => true,
                        'is_active' => true,
                        'assigned_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    Log::info('Owner user assigned to main branch', [
                        'tenant_id' => $tenant->id,
                        'user_id' => $userId,
                        'branch_id' => $mainBranch->id,
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Could not assign owner user to branch', ['error' => $e->getMessage()]);
            }

            // Update tenant with owner_user_id and store initial password
            DB::statement("SET search_path TO public");

            // Also create owner user in public schema for owner portal access
            $this->createOwnerUserInPublicSchema($tenant, $userId, $password, $firstName, $lastName, $nameParts[0]);

            $settings = $tenant->settings ?? [];
            $settings['initial_owner_password'] = $password;
            $settings['initial_owner_created_at'] = now()->toISOString();
            $tenant->update([
                'owner_user_id' => $userId,
                'settings' => $settings,
            ]);

            Log::info('Owner user created', [
                'tenant_id' => $tenant->id,
                'user_id' => $userId,
                'email' => $tenant->contact_email,
            ]);

            return ['user_id' => $userId, 'password' => $password];

        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            Log::error('Failed to create owner user', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Update owner user password in public schema.
     */
    protected function updateOwnerUserPasswordInPublicSchema(string $email, string $password): void
    {
        try {
            DB::statement("SET search_path TO public");
            $existing = DB::table('users')->where('email', $email)->first();
            if ($existing) {
                DB::table('users')->where('id', $existing->id)->update([
                    'password' => Hash::make($password),
                    'updated_at' => now(),
                ]);
                Log::info('Owner user password updated in public schema', ['email' => $email]);
            }
        } catch (\Exception $e) {
            Log::warning('Could not update owner password in public schema', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Create or update owner user in public schema for owner portal access.
     */
    protected function createOwnerUserInPublicSchema(
        Tenant $tenant,
        string $userId,
        string $password,
        string $firstName,
        string $lastName,
        string $username
    ): void {
        try {
            // Ensure we're in public schema
            DB::statement("SET search_path TO public");

            // Check if user already exists in public schema
            $existing = DB::table('users')->where('email', $tenant->contact_email)->first();

            if ($existing) {
                // Update existing user's password
                DB::table('users')->where('id', $existing->id)->update([
                    'password' => Hash::make($password),
                    'updated_at' => now(),
                ]);
                Log::info('Owner user updated in public schema', [
                    'tenant_id' => $tenant->id,
                    'user_id' => $existing->id,
                ]);
            } else {
                // Create new user in public schema
                DB::table('users')->insert([
                    'id' => $userId,
                    'tenant_id' => $tenant->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $tenant->contact_email,
                    'username' => $username,
                    'phone' => $tenant->contact_phone,
                    'password' => Hash::make($password),
                    'status' => 'active',
                    'language' => $tenant->locale ?? 'ar',
                    'timezone' => $tenant->timezone ?? 'Africa/Cairo',
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Assign clinic_owner role if it exists
                try {
                    $ownerRole = DB::table('roles')->where('name', 'clinic_owner')->first();
                    if ($ownerRole) {
                        DB::table('model_has_roles')->insert([
                            'role_id' => $ownerRole->id,
                            'model_type' => 'Modules\\Auth\\Models\\User',
                            'model_id' => $userId,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Could not assign clinic_owner role in public schema', ['error' => $e->getMessage()]);
                }

                Log::info('Owner user created in public schema', [
                    'tenant_id' => $tenant->id,
                    'user_id' => $userId,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to create owner user in public schema', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Ensure a user is assigned to the main branch with super_admin role.
     */
    protected function ensureUserAssignedToMainBranch(Tenant $tenant, string $userId): void
    {
        try {
            $mainBranch = DB::table('branches')->where('is_main', true)->first();
            if (!$mainBranch) {
                return;
            }

            // Check if assignment already exists
            $existingAssignment = DB::table('user_branch_roles')
                ->where('user_id', $userId)
                ->where('branch_id', $mainBranch->id)
                ->first();

            if ($existingAssignment) {
                return;
            }

            $superAdminRole = DB::table('roles')->where('name', 'super_admin')->first();
            if (!$superAdminRole) {
                return;
            }

            DB::table('user_branch_roles')->insert([
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenant->id,
                'user_id' => $userId,
                'branch_id' => $mainBranch->id,
                'role_id' => $superAdminRole->id,
                'is_primary' => true,
                'is_active' => true,
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('User assigned to main branch', [
                'tenant_id' => $tenant->id,
                'user_id' => $userId,
                'branch_id' => $mainBranch->id,
            ]);
        } catch (\Exception $e) {
            Log::warning('Could not ensure user is assigned to main branch', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get all tenant migration files (excludes platform-only migrations).
     */
    protected function getTenantMigrationFiles(): array
    {
        $files = [];
        $modulesPath = base_path('modules');

        // Migrations that should NOT run in tenant schemas (platform-only)
        $platformOnlyPatterns = [
            'create_tenants_table',
            'create_tenant_subscriptions_table',
            'create_tenant_usage_table',
            'create_tenant_modules_table',
        ];

        if (is_dir($modulesPath)) {
            $modules = scandir($modulesPath);
            foreach ($modules as $module) {
                if ($module === '.' || $module === '..') {
                    continue;
                }

                // Check Database/Migrations first (preferred)
                $migrationsDir = base_path("modules/{$module}/Database/Migrations");
                if (!is_dir($migrationsDir)) {
                    $migrationsDir = base_path("modules/{$module}/Migrations");
                }

                if (is_dir($migrationsDir)) {
                    $migrationFiles = glob($migrationsDir . '/*.php');
                    foreach ($migrationFiles as $file) {
                        $filename = basename($file);

                        // Skip platform-only migrations
                        $skip = false;
                        foreach ($platformOnlyPatterns as $pattern) {
                            if (str_contains($filename, $pattern)) {
                                $skip = true;
                                Log::info('Skipping platform-only migration', ['file' => $filename]);
                                break;
                            }
                        }

                        if (!$skip) {
                            $files[] = $file;
                        }
                    }
                }
            }
        }

        // Sort files by filename (timestamp) to ensure proper migration order
        usort($files, function ($a, $b) {
            return basename($a) <=> basename($b);
        });

        return $files;
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