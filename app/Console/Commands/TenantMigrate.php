<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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

        $tenant = Tenant::where('id', $tenantIdentifier)
            ->orWhere('slug', $tenantIdentifier)
            ->first();

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
        $schemaName = 'tenant_' . str_replace('-', '_', $tenant->slug);

        $this->info("Migrating tenant: {$tenant->name} (schema: {$schemaName})");

        try {
            // Set the search path to tenant schema
            DB::statement("SET search_path TO \"{$schemaName}\", public");

            // Configure the database connection for tenant
            Config::set('database.connections.tenant.search_path', $schemaName);

            // Determine migration action
            if ($this->option('fresh')) {
                $this->warn("Running fresh migration (dropping all tables)...");
                Artisan::call('migrate:fresh', [
                    '--database' => 'tenant',
                    '--path' => $this->getMigrationPaths(),
                    '--force' => true,
                ]);
            } elseif ($this->option('rollback')) {
                $steps = (int) $this->option('step');
                $this->info("Rolling back {$steps} migration(s)...");
                Artisan::call('migrate:rollback', [
                    '--database' => 'tenant',
                    '--path' => $this->getMigrationPaths(),
                    '--step' => $steps,
                    '--force' => true,
                ]);
            } else {
                Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--path' => $this->getMigrationPaths(),
                    '--force' => true,
                ]);
            }

            $this->line(Artisan::output());

            // Run seeder if requested
            if ($this->option('seed')) {
                $this->call('tenant:seed', ['--tenant' => $tenant->id]);
            }

            // Reset search path
            DB::statement("SET search_path TO public");

            $this->info("Migration completed for tenant: {$tenant->name}");
            return 0;

        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            $this->error("Migration failed: " . $e->getMessage());
            return 1;
        }
    }

    protected function migrateTenantSilently(Tenant $tenant): void
    {
        $schemaName = 'tenant_' . str_replace('-', '_', $tenant->slug);

        DB::statement("SET search_path TO \"{$schemaName}\", public");
        Config::set('database.connections.tenant.search_path', $schemaName);

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => $this->getMigrationPaths(),
            '--force' => true,
        ]);

        DB::statement("SET search_path TO public");
    }

    protected function getMigrationPaths(): array
    {
        $customPath = $this->option('path');
        if ($customPath) {
            return [$customPath];
        }

        // Default paths for tenant migrations
        return [
            'database/migrations/tenant',
            'modules/Core/Database/Migrations',
            'modules/Patients/Database/Migrations',
            'modules/Services/Database/Migrations',
            'modules/Booking/Database/Migrations',
            'modules/Billing/Database/Migrations',
            'modules/Staff/Database/Migrations',
            'modules/Inventory/Database/Migrations',
            'modules/Equipment/Database/Migrations',
            'modules/Packages/Database/Migrations',
            'modules/GiftCards/Database/Migrations',
            'modules/Memberships/Database/Migrations',
            'modules/Payroll/Database/Migrations',
            'modules/Marketing/Database/Migrations',
            'modules/Reporting/Database/Migrations',
            'modules/PatientPortal/Database/Migrations',
        ];
    }
}
