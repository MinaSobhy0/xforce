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
        // Step 1: Create tenant record in a transaction
        $tenant = DB::transaction(function () use ($data) {
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

            return $tenant;
        });

        // Step 2: Create tenant database schema (outside transaction so schema is visible immediately)
        try {
            $this->createTenantDatabase($tenant);
        } catch (\Exception $e) {
            // Rollback: delete tenant record if database creation fails
            $tenant->usage?->forceDelete();
            $tenant->forceDelete();
            throw $e;
        }

        // Step 3: Activate if requested
        if (($data['status'] ?? TenantStatus::PENDING) === TenantStatus::ACTIVE) {
            $this->activate($tenant);
        }

        return $tenant;
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

        // Create schema in the main database using explicit connection
        // Schema operations must auto-commit to be visible to other connections
        $pgsqlConn = DB::connection('pgsql');
        $pgsqlConn->statement("CREATE SCHEMA IF NOT EXISTS \"{$schemaName}\"");

        // Grant permissions to the database user
        $dbUser = config('database.connections.pgsql.username');
        $pgsqlConn->statement("GRANT ALL ON SCHEMA \"{$schemaName}\" TO \"{$dbUser}\"");

        // Verify schema was created
        $schemaExists = $pgsqlConn->selectOne("
            SELECT EXISTS (
                SELECT 1 FROM information_schema.schemata WHERE schema_name = ?
            ) as exists
        ", [$schemaName])->exists;

        if (!$schemaExists) {
            throw new \RuntimeException("Failed to create schema: {$schemaName}");
        }

        Log::info('Schema created successfully', ['schema_name' => $schemaName]);

        // Build the tenant connection config
        $tenantConnectionConfig = [
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
            // PgBouncer compatibility: emulate prepares to avoid "prepared statement does not exist" errors
            'options' => [
                \PDO::ATTR_PERSISTENT => env('DB_PERSISTENT', false),
                \PDO::ATTR_EMULATE_PREPARES => env('DB_PGBOUNCER', true),
            ],
        ];

        // Configure BOTH the dynamic tenant_X connection AND the generic 'tenant' connection
        // Some migrations use protected $connection = 'tenant' which would override --database
        config([
            "database.connections.{$connectionName}" => $tenantConnectionConfig,
            "database.connections.tenant" => $tenantConnectionConfig,
        ]);

        // Purge both connections to force Laravel to use new config
        DB::purge($connectionName);
        DB::purge('tenant');
        DB::reconnect($connectionName);
        DB::reconnect('tenant');

        // Explicitly set search_path for PgBouncer compatibility on both connections
        DB::connection($connectionName)->statement("SET search_path TO \"{$schemaName}\"");
        DB::connection('tenant')->statement("SET search_path TO \"{$schemaName}\"");

        // Verify search_path is set correctly
        $result = DB::connection($connectionName)->select('SHOW search_path');
        Log::info('Search path set for tenant connection', [
            'connection' => $connectionName,
            'search_path' => $result[0]->search_path ?? 'unknown',
        ]);

        // Run migrations for tenant using direct SQL approach
        $this->runTenantMigrations($tenant, $connectionName, $schemaName);

        Log::info('Tenant database provisioning completed', [
            'tenant_id' => $tenant->id,
            'schema_name' => $schemaName,
        ]);

        // Create default branch for the tenant FIRST (seeders need it)
        $this->createDefaultBranch($tenant);

        // Run tenant seeders (after branch exists for StockLocationSeeder, etc.)
        $this->runTenantSeeders($tenant);

        // Create owner user if contact_email is set
        if ($tenant->contact_email) {
            $this->createOwnerUser($tenant);
        }
    }

    /**
     * Run all tenant seeders in the correct order.
     */
    protected function runTenantSeeders(Tenant $tenant): void
    {
        $schemaName = $tenant->database_name;

        Log::info('Running tenant seeders', [
            'tenant_id' => $tenant->id,
            'schema_name' => $schemaName,
        ]);

        try {
            // Switch to tenant schema
            DB::statement("SET search_path TO \"{$schemaName}\"");

            // Set current tenant in manager for seeders that need it
            $this->tenantManager->setCurrentTenant($tenant);
            app()->instance('currentTenant', $tenant);

            // First, create super_admin role with all permissions
            $this->createSuperAdminRole($tenant);

            // Define seeders to run in order
            $seeders = [
                // Roles and permissions (tenant-level roles)
                \Database\Seeders\TenantRoleSeeder::class,

                // Core settings and sequences
                \Modules\Core\Database\Seeders\DefaultSettingsSeeder::class,
                \Modules\Core\Database\Seeders\DefaultSequenceSeeder::class,

                // Billing - tax rates
                \Modules\Billing\Database\Seeders\TaxRateSeeder::class,

                // Accounting - chart of accounts and journals
                \Modules\Accounting\Database\Seeders\ChartOfAccountsSeeder::class,
                \Modules\Accounting\Database\Seeders\JournalSeeder::class,

                // Payroll defaults
                \Modules\Payroll\Database\Seeders\PayrollDefaultsSeeder::class,

                // Staff - commission plans
                \Modules\Staff\Database\Seeders\CommissionPlanSeeder::class,

                // Service parameter templates
                \Modules\Services\Database\Seeders\ParameterTemplatesSeeder::class,

                // Work schedules (default shift patterns)
                \Modules\Booking\Database\Seeders\WorkScheduleSeeder::class,

                // Time off types
                \Modules\Booking\Database\Seeders\TimeOffTypeSeeder::class,

                // Attendance rules
                \Modules\Attendance\Database\Seeders\AttendanceRuleSeeder::class,

                // Marketing - message templates (must be before automation rules)
                \Modules\Marketing\Database\Seeders\MessageTemplateSeeder::class,

                // Marketing - automation rules
                \Modules\Marketing\Database\Seeders\AutomationRuleSeeder::class,

                // Loyalty - default rules and referral program
                \Modules\Loyalty\Database\Seeders\LoyaltySeeder::class,

                // Default medicines catalog
                \Modules\Prescriptions\Database\Seeders\DefaultMedicinesSeeder::class,

                // Asset types
                \Modules\Assets\Database\Seeders\AssetTypeSeeder::class,

                // Inventory - Unit of Measure
                \Modules\Inventory\Database\Seeders\UomSeeder::class,

                // Inventory - Stock Locations
                \Modules\Inventory\Database\Seeders\StockLocationSeeder::class,
            ];

            foreach ($seeders as $seederClass) {
                if (class_exists($seederClass)) {
                    try {
                        $seeder = new $seederClass();
                        $seeder->run();
                        Log::info('Seeder completed', ['seeder' => class_basename($seederClass)]);
                    } catch (\Exception $e) {
                        Log::warning('Seeder failed', [
                            'seeder' => class_basename($seederClass),
                            'error' => $e->getMessage(),
                        ]);
                        // Continue with other seeders even if one fails
                    }
                }
            }

            Log::info('All tenant seeders completed', ['tenant_id' => $tenant->id]);

            // Reset search_path to public
            DB::statement("SET search_path TO public");

        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            Log::error('Failed to run tenant seeders', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create super_admin role with all permissions.
     */
    protected function createSuperAdminRole(Tenant $tenant): void
    {
        // Define base permissions
        $permissionNames = [
            'system.view', 'system.manage',
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'roles.view', 'roles.create', 'roles.edit', 'roles.manage',
            'permissions.view', 'permissions.manage',
            'patients.view', 'patients.create', 'patients.edit', 'patients.delete',
            'appointments.view', 'appointments.create', 'appointments.edit', 'appointments.delete',
            'services.view', 'services.create', 'services.edit', 'services.delete',
            'billing.view', 'billing.create', 'billing.edit', 'billing.delete',
            'reports.view', 'reports.export',
            'profile.view', 'profile.edit',
            'settings.view', 'settings.edit',
        ];

        // Create permissions
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

        // Get all permission IDs
        $allPermissionIds = DB::table('permissions')->pluck('id')->toArray();

        // Create super_admin role if not exists
        $existing = DB::table('roles')->where('name', 'super_admin')->where('guard_name', 'web')->first();
        if (!$existing) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => 'super_admin',
                'guard_name' => 'web',
                'display_name' => 'Super Admin',
                'is_system' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assign all permissions to super_admin
            foreach ($allPermissionIds as $permId) {
                DB::table('role_has_permissions')->insert([
                    'permission_id' => $permId,
                    'role_id' => $roleId,
                ]);
            }

            Log::info('super_admin role created with all permissions', ['tenant_id' => $tenant->id]);
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
                        'display_name' => ucwords(str_replace('_', ' ', $roleName)),
                        'is_system' => in_array($roleName, ['super_admin', 'admin']),
                        'is_active' => true,
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

            // Create the default main branch
            $branchId = DB::table('branches')->insertGetId([
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

            $nameParts = explode('@', $tenant->contact_email);
            $firstName = $tenant->contact_name ? explode(' ', $tenant->contact_name)[0] : ucfirst($nameParts[0]);
            $lastName = $tenant->contact_name && str_contains($tenant->contact_name, ' ')
                ? trim(substr($tenant->contact_name, strpos($tenant->contact_name, ' ')))
                : 'Admin';

            // Insert user
            $userId = DB::table('users')->insertGetId([
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
     * Run migrations for a tenant using direct database operations.
     */
    protected function runTenantMigrations(Tenant $tenant, string $connectionName, string $schemaName): void
    {
        Log::info('Running tenant migrations via direct execution', [
            'tenant_id' => $tenant->id,
            'schema_name' => $schemaName,
        ]);

        // Get the connection for this tenant
        $connection = DB::connection($connectionName);

        // Ensure we're using the correct schema
        $connection->statement("SET search_path TO \"{$schemaName}\"");

        // Create migrations table if it doesn't exist (use fully qualified name)
        $migrationsTableExists = $connection->selectOne("
            SELECT EXISTS (
                SELECT FROM pg_tables
                WHERE schemaname = ? AND tablename = 'migrations'
            ) as exists
        ", [$schemaName])->exists;

        if (!$migrationsTableExists) {
            $connection->statement("
                CREATE TABLE \"{$schemaName}\".migrations (
                    id SERIAL PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL,
                    batch INTEGER NOT NULL
                )
            ");
            Log::info('Created migrations table in tenant schema');
        }

        // Get already run migrations (use fully qualified table name)
        $ranMigrations = $connection->select("SELECT migration FROM \"{$schemaName}\".migrations");
        $ranMigrations = array_map(fn($r) => $r->migration, $ranMigrations);

        // Get all migration files
        $migrationFiles = $this->getTenantMigrationFiles();
        $batchResult = $connection->selectOne("SELECT COALESCE(MAX(batch), 0) as max_batch FROM \"{$schemaName}\".migrations");
        $batch = ($batchResult->max_batch ?? 0) + 1;

        $successCount = 0;
        $errorCount = 0;

        foreach ($migrationFiles as $file) {
            $migrationName = pathinfo($file, PATHINFO_FILENAME);

            // Skip if already run
            if (in_array($migrationName, $ranMigrations)) {
                continue;
            }

            try {
                // Include and run the migration
                $migration = require $file;

                if (is_object($migration) && method_exists($migration, 'up')) {
                    // Set the connection for Schema operations within migration
                    $originalConnection = config('database.default');
                    config(['database.default' => $connectionName]);

                    // Ensure schema path is set before running migration
                    $connection->statement("SET search_path TO \"{$schemaName}\"");

                    $migration->up();

                    // Reset to original connection
                    config(['database.default' => $originalConnection]);

                    // Record the migration (use fully qualified table name)
                    $connection->statement(
                        "INSERT INTO \"{$schemaName}\".migrations (migration, batch) VALUES (?, ?)",
                        [$migrationName, $batch]
                    );

                    $successCount++;
                    Log::debug('Migration completed', ['migration' => $migrationName]);
                }
            } catch (\Exception $e) {
                $errorCount++;
                Log::error('Migration failed', [
                    'migration' => $migrationName,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        Log::info('Tenant migrations completed', [
            'success' => $successCount,
            'errors' => $errorCount,
            'skipped' => count($migrationFiles) - $successCount - $errorCount,
        ]);
    }

    /**
     * Get all tenant migration files (excludes platform-only migrations).
     * Files are sorted globally by timestamp to ensure correct dependency order.
     */
    protected function getTenantMigrationFiles(): array
    {
        $files = [];

        // Migrations that should NOT run in tenant schemas (platform-only or data migrations)
        $platformOnlyPatterns = [
            'create_tenants_table',
            'create_tenant_subscriptions_table',
            'create_tenant_usage_table',
            'create_tenant_modules_table',
            // Data migration that iterates over existing schemas - breaks new tenant provisioning
            'create_gift_card_journal',
        ];

        // Collect migrations from all modules
        $modulesPath = base_path('modules');
        if (is_dir($modulesPath)) {
            $allModules = scandir($modulesPath);
            foreach ($allModules as $module) {
                if ($module === '.' || $module === '..') {
                    continue;
                }

                $migrationsDir = base_path("modules/{$module}/Database/Migrations");
                if (!is_dir($migrationsDir)) {
                    continue;
                }

                $migrationFiles = glob($migrationsDir . '/*.php');
                foreach ($migrationFiles as $file) {
                    $filename = basename($file);

                    // Skip platform-only migrations
                    $skip = false;
                    foreach ($platformOnlyPatterns as $pattern) {
                        if (str_contains($filename, $pattern)) {
                            $skip = true;
                            Log::debug('Skipping platform-only migration', ['file' => $filename]);
                            break;
                        }
                    }

                    if (!$skip) {
                        $files[] = $file;
                    }
                }
            }
        }

        // Sort ALL migrations globally by filename (timestamp) to ensure correct dependency order
        // Migrations are named like: 2024_01_01_000000_create_xxx_table.php
        // This ensures tables are created in the correct order across all modules
        usort($files, function ($a, $b) {
            return basename($a) <=> basename($b);
        });

        return $files;
    }

    /**
     * Get tenant migration paths in the correct order.
     * Returns relative paths to migration directories.
     */
    protected function getTenantMigrationPaths(): array
    {
        // Define module order to ensure dependencies are met
        // Auth first (creates users table), then Core, then other modules
        $moduleOrder = [
            'Auth',
            'Core',
            'Staff',
            'Patients',
            'Services',
            'Equipment',
            'Inventory',
            'Booking',
            'Billing',
            'Packages',
            'GiftCards',
            'Memberships',
            'Payroll',
            'Accounting',
            'Marketing',
            'Loyalty',
            'TreatmentPlans',
            'PatientPortal',
            'Attendance',
            'Assets',
            'Prescriptions',
        ];

        $paths = [];

        foreach ($moduleOrder as $module) {
            $path = "modules/{$module}/Database/Migrations";
            if (is_dir(base_path($path))) {
                $paths[] = $path;
            }
        }

        // Add any modules not in the predefined order
        $modulesPath = base_path('modules');
        if (is_dir($modulesPath)) {
            $allModules = scandir($modulesPath);
            foreach ($allModules as $module) {
                if ($module === '.' || $module === '..') {
                    continue;
                }
                if (!in_array($module, $moduleOrder)) {
                    $path = "modules/{$module}/Database/Migrations";
                    if (is_dir(base_path($path))) {
                        $paths[] = $path;
                    }
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