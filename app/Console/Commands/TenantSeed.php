<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Tenant;

class TenantSeed extends Command
{
    protected $signature = 'tenant:seed
                            {--tenant= : Specific tenant ID or slug to seed}
                            {--all : Seed all active tenants}
                            {--class= : Specific seeder class to run}';

    protected $description = 'Seed default data for tenant database schema(s)';

    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->seedAllTenants();
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

        return $this->seedTenant($tenant);
    }

    protected function seedAllTenants(): int
    {
        $tenants = Tenant::whereIn('status', ['active', 'trial'])->get();

        if ($tenants->isEmpty()) {
            $this->info("No active tenants found.");
            return 0;
        }

        $this->info("Seeding {$tenants->count()} tenants...");
        $bar = $this->output->createProgressBar($tenants->count());
        $bar->start();

        $failed = [];

        foreach ($tenants as $tenant) {
            try {
                $this->seedTenantSilently($tenant);
            } catch (\Exception $e) {
                $failed[] = ['tenant' => $tenant->slug, 'error' => $e->getMessage()];
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if (!empty($failed)) {
            $this->error("Some seeds failed:");
            $this->table(['Tenant', 'Error'], $failed);
            return 1;
        }

        $this->info("All tenant seeds completed successfully!");
        return 0;
    }

    protected function seedTenant(Tenant $tenant): int
    {
        $schemaName = 'tenant_' . str_replace('-', '_', $tenant->slug);

        $this->info("Seeding tenant: {$tenant->name} (schema: {$schemaName})");

        try {
            // Set the search path to tenant schema
            DB::statement("SET search_path TO \"{$schemaName}\", public");
            Config::set('database.connections.tenant.search_path', $schemaName);

            // Run seeders
            $seederClass = $this->option('class');

            if ($seederClass) {
                Artisan::call('db:seed', [
                    '--database' => 'tenant',
                    '--class' => $seederClass,
                    '--force' => true,
                ]);
            } else {
                // Run all module seeders
                $seeders = $this->getTenantSeeders();
                foreach ($seeders as $seeder) {
                    if (class_exists($seeder)) {
                        $this->line("  Running: {$seeder}");
                        Artisan::call('db:seed', [
                            '--database' => 'tenant',
                            '--class' => $seeder,
                            '--force' => true,
                        ]);
                    }
                }
            }

            // Reset search path
            DB::statement("SET search_path TO public");

            $this->info("Seeding completed for tenant: {$tenant->name}");
            return 0;

        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            $this->error("Seeding failed: " . $e->getMessage());
            return 1;
        }
    }

    protected function seedTenantSilently(Tenant $tenant): void
    {
        $schemaName = 'tenant_' . str_replace('-', '_', $tenant->slug);

        DB::statement("SET search_path TO \"{$schemaName}\", public");
        Config::set('database.connections.tenant.search_path', $schemaName);

        $seeders = $this->getTenantSeeders();
        foreach ($seeders as $seeder) {
            if (class_exists($seeder)) {
                Artisan::call('db:seed', [
                    '--database' => 'tenant',
                    '--class' => $seeder,
                    '--force' => true,
                ]);
            }
        }

        DB::statement("SET search_path TO public");
    }

    protected function getTenantSeeders(): array
    {
        return [
            // Core module seeders
            \Modules\Core\Database\Seeders\CoreDatabaseSeeder::class,

            // Treatments module seeders
            \Modules\Treatments\Database\Seeders\TreatmentsDatabaseSeeder::class,

            // Billing module seeders
            \Modules\Billing\Database\Seeders\BillingDatabaseSeeder::class,

            // Staff module seeders
            \Modules\Staff\Database\Seeders\StaffDatabaseSeeder::class,

            // Add more module seeders as needed
        ];
    }
}
