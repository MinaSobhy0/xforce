<?php

namespace App\Console\Commands;

use App\Jobs\TenantBackupJob;
use App\Models\Backup;
use App\Models\PlatformSetting;
use Modules\Core\Models\Tenant;
use Illuminate\Console\Command;

class BackupAllTenants extends Command
{
    protected $signature = 'tenants:backup
                            {--tenant= : Backup specific tenant by ID or slug}
                            {--force : Run even if auto_backup is disabled}';

    protected $description = 'Create backups for all active tenants';

    public function handle(): int
    {
        // Check if auto_backup is enabled (unless forced)
        if (!$this->option('force') && !PlatformSetting::get('auto_backup', true)) {
            $this->info('Auto backup is disabled. Use --force to run anyway.');
            return 0;
        }

        $tenantOption = $this->option('tenant');

        if ($tenantOption) {
            // Backup specific tenant
            $tenant = Tenant::where('id', $tenantOption)
                ->orWhere('slug', $tenantOption)
                ->first();

            if (!$tenant) {
                $this->error("Tenant not found: {$tenantOption}");
                return 1;
            }

            $this->backupTenant($tenant);
        } else {
            // Backup all active tenants
            $tenants = Tenant::where('status', 'active')
                ->orWhere('status', 'trial')
                ->get();

            if ($tenants->isEmpty()) {
                $this->info('No active tenants to backup.');
                return 0;
            }

            $this->info("Starting backup for {$tenants->count()} tenants...");

            $bar = $this->output->createProgressBar($tenants->count());
            $bar->start();

            foreach ($tenants as $tenant) {
                $this->backupTenant($tenant);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info('All tenant backups have been queued.');
        }

        // Clean up old backups
        $this->cleanupOldBackups();

        return 0;
    }

    protected function backupTenant(Tenant $tenant): void
    {
        // Create backup record
        $backup = Backup::create([
            'name' => "Auto Backup - {$tenant->name}",
            'type' => 'tenant',
            'disk' => 'local',
            'filename' => sprintf('tenant_%s_%s.sql.gz', $tenant->slug, now()->format('Y-m-d_His')),
            'status' => 'pending',
            'tenant_id' => $tenant->id,
            'notes' => 'Automated scheduled backup',
        ]);

        // Dispatch job (sync if queue is sync, otherwise queue)
        if (config('queue.default') === 'sync') {
            TenantBackupJob::dispatchSync($backup);
        } else {
            TenantBackupJob::dispatch($backup);
        }

        $this->line(" Queued backup for: {$tenant->name}");
    }

    protected function cleanupOldBackups(): void
    {
        $retentionDays = PlatformSetting::get('backup_retention', 30);

        $oldBackups = Backup::where('created_at', '<', now()->subDays($retentionDays))
            ->where('status', 'completed')
            ->get();

        if ($oldBackups->isEmpty()) {
            return;
        }

        $this->info("Cleaning up {$oldBackups->count()} old backups (older than {$retentionDays} days)...");

        foreach ($oldBackups as $backup) {
            $backup->delete(); // This also deletes the file
        }

        $this->info('Old backups cleaned up.');
    }
}
