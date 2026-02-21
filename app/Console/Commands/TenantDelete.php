<?php

namespace App\Console\Commands;

use App\Models\Backup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Tenant;

class TenantDelete extends Command
{
    protected $signature = 'tenant:delete
                            {tenant : Tenant ID or slug}
                            {--force : Skip confirmation}
                            {--keep-schema : Do not drop the database schema}
                            {--backup : Create a backup before deletion}';

    protected $description = 'Delete a tenant and optionally its database schema';

    public function handle(): int
    {
        $tenantIdentifier = $this->argument('tenant');

        $tenant = Tenant::where('id', $tenantIdentifier)
            ->orWhere('slug', $tenantIdentifier)
            ->first();

        if (!$tenant) {
            $this->error("Tenant not found: {$tenantIdentifier}");
            return 1;
        }

        $this->warn("You are about to delete tenant: {$tenant->name} ({$tenant->slug})");
        $this->warn("This action cannot be undone!");

        if (!$this->option('force')) {
            if (!$this->confirm("Are you sure you want to delete this tenant?")) {
                $this->info("Operation cancelled.");
                return 0;
            }

            // Double confirmation for safety
            $confirmName = $this->ask("Type the tenant slug to confirm: '{$tenant->slug}'");
            if ($confirmName !== $tenant->slug) {
                $this->error("Confirmation failed. Slug does not match.");
                return 1;
            }
        }

        try {
            // Create backup if requested
            if ($this->option('backup')) {
                $this->info("Creating backup before deletion...");
                $this->call('tenants:backup', ['--tenant' => $tenant->id, '--force' => true]);
            }

            $schemaName = 'tenant_' . str_replace('-', '_', $tenant->slug);

            // Drop schema if not keeping
            if (!$this->option('keep-schema')) {
                $this->info("Dropping database schema: {$schemaName}");
                DB::statement("DROP SCHEMA IF EXISTS \"{$schemaName}\" CASCADE");
                $this->info("Schema dropped successfully.");
            } else {
                $this->warn("Schema '{$schemaName}' was NOT dropped (--keep-schema flag used).");
            }

            // Delete related records
            $this->info("Cleaning up related records...");

            // Delete backups records
            Backup::where('tenant_id', $tenant->id)->delete();

            // Delete tenant usage
            DB::table('tenant_usage')->where('tenant_id', $tenant->id)->delete();

            // Soft delete or hard delete the tenant
            $tenant->forceDelete();

            $this->newLine();
            $this->info("Tenant '{$tenant->name}' has been deleted successfully.");

            return 0;

        } catch (\Exception $e) {
            $this->error("Failed to delete tenant: " . $e->getMessage());
            return 1;
        }
    }
}
