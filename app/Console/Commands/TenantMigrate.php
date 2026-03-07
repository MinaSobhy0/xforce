<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Tenant;

class TenantMigrate extends Command
{
    protected $signature = 'tenant:migrate
                            {--tenant= : Specific tenant ID or slug to migrate}
                            {--all : Migrate all active tenants}
                            {--path= : The path to the migrations files}
                            {--seed : Seed the database after migration}
                            {--fresh : Drop all tables and re-run migrations}
                            {--rollback : Rollback the last migration}
                            {--step=1 : Number of migrations to rollback}';

    protected $description = 'Run migrations for tenant database schema(s)';

    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->migrateAllTenants();
        }

        $tenantIdentifier = $this->option('tenant');
        if (!$tenantIdentifier) {
            $this->error("Please specify --tenant=<id|slug> or use --all");
            return 1;
        }

        // Check if identifier is numeric (ID) or string (slug)
        if (is_numeric($tenantIdentifier)) {
            $tenant = Tenant::find($tenantIdentifier);
        } else {
            $tenant = Tenant::where('slug', $tenantIdentifier)->first();
        }

        if (!$tenant) {
            $this->error("Tenant not found: {$tenantIdentifier}");
            return 1;
        }

        return $this->migrateTenant($tenant);
    }

    protected function migrateAllTenants(): int
    {
        $tenants = Tenant::whereIn('status', ['active', 'trial'])->get();

        if ($tenants->isEmpty()) {
            $this->info("No active tenants found.");
            return 0;
        }

        $this->info("Migrating {$tenants->count()} tenants...");
        $bar = $this->output->createProgressBar($tenants->count());
        $bar->start();

        $failed = [];

        foreach ($tenants as $tenant) {
            try {
                $this->migrateTenantSilently($tenant);
            } catch (\Exception $e) {
                $failed[] = ['tenant' => $tenant->slug, 'error' => $e->getMessage()];
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if (!empty($failed)) {
            $this->error("Some migrations failed:");
            $this->table(['Tenant', 'Error'], $failed);
            return 1;
        }

        $this->info("All tenant migrations completed successfully!");
        return 0;
    }

    protected function migrateTenant(Tenant $tenant): int
    {
        $schemaName = $tenant->database_name ?: ('tenant_' . $tenant->slug);

        $this->info("Migrating tenant: {$tenant->name} (schema: {$schemaName})");

        try {
            // Configure the database connection for tenant schema
            Config::set('database.connections.tenant.search_path', $schemaName);

            // IMPORTANT: Purge ALL connections to force fresh resolution
            DB::purge('tenant');
            app('db')->purge('tenant');

            // Also clear the database manager's resolved connections
            app()->forgetInstance('db');

            // Verify connection has correct search_path before proceeding
            $connection = DB::connection('tenant');
            $currentPath = $connection->selectOne("SHOW search_path");
            $this->line("Search path set to: " . ($currentPath->search_path ?? 'unknown'));

            // If search_path isn't set correctly, set it explicitly via SQL
            if ($currentPath->search_path !== $schemaName) {
                $connection->statement("SET search_path TO \"{$schemaName}\"");
                $this->line("Search path explicitly set via SQL");
            }

            // Get migration paths
            $paths = $this->getMigrationPaths();

            // Run migrations using shell command to ensure fresh process with correct config
            $pathArgs = [];
            foreach ($paths as $path) {
                if (is_dir(base_path($path))) {
                    $pathArgs[] = '--path=' . $path;
                }
            }

            // Determine migration action
            if ($this->option('fresh')) {
                $this->warn("Running fresh migration (dropping all tables)...");
                $cmd = 'migrate:fresh';
            } elseif ($this->option('rollback')) {
                $steps = (int) $this->option('step');
                $this->info("Rolling back {$steps} migration(s)...");
                $cmd = 'migrate:rollback';
            } else {
                $cmd = 'migrate';
            }

            // Use process to run migration with env variable for search_path
            $process = new \Symfony\Component\Process\Process([
                'php', 'artisan', $cmd,
                '--database=tenant',
                '--force',
                ...$pathArgs,
            ], base_path(), [
                'DB_TENANT_SEARCH_PATH' => $schemaName,
            ]);
            $process->setTimeout(300);
            $process->run(function ($type, $buffer) {
                $this->output->write($buffer);
            });

            // Run seeder if requested
            if ($this->option('seed')) {
                $this->call('tenant:seed', ['--tenant' => $tenant->id]);
            }

            // Reset search path
            Config::set('database.connections.tenant.search_path', 'public');
            DB::purge('tenant');

            $this->info("Migration completed for tenant: {$tenant->name}");
            return 0;

        } catch (\Exception $e) {
            Config::set('database.connections.tenant.search_path', 'public');
            DB::purge('tenant');
            $this->error("Migration failed: " . $e->getMessage());
            return 1;
        }
    }

    protected function migrateTenantSilently(Tenant $tenant): void
    {
        $schemaName = $tenant->database_name ?: ('tenant_' . $tenant->slug);

        Config::set('database.connections.tenant.search_path', $schemaName);
        DB::purge('tenant');
        DB::connection('tenant')->reconnect();

        $this->callSilently('migrate', [
            '--database' => 'tenant',
            '--path' => $this->getMigrationPaths(),
            '--force' => true,
        ]);

        Config::set('database.connections.tenant.search_path', 'public');
        DB::purge('tenant');
    }

    protected function getMigrationPaths(): array
    {
        $customPath = $this->option('path');
        if ($customPath) {
            return [$customPath];
        }

        // Default paths for tenant migrations - ordered by dependencies
        return [
            'database/migrations/tenant',
            'modules/Core/Database/Migrations',
            'modules/Auth/Database/Migrations',
            'modules/Accounting/Database/Migrations',
            'modules/Patients/Database/Migrations',
            'modules/Services/Database/Migrations',
            'modules/Staff/Database/Migrations',
            'modules/Inventory/Database/Migrations',
            'modules/Equipment/Database/Migrations',
            'modules/Booking/Database/Migrations',
            'modules/Billing/Database/Migrations',
            'modules/Packages/Database/Migrations',
            'modules/TreatmentPlans/Database/Migrations',
            'modules/Prescriptions/Database/Migrations',
            'modules/GiftCards/Database/Migrations',
            'modules/Memberships/Database/Migrations',
            'modules/Payroll/Database/Migrations',
            'modules/Attendance/Database/Migrations',
            'modules/Assets/Database/Migrations',
            'modules/Marketing/Database/Migrations',
            'modules/Loyalty/Database/Migrations',
            'modules/PatientPortal/Database/Migrations',
        ];
    }
}
