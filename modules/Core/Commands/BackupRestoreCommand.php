<?php

namespace Modules\Core\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Tenant;
use Symfony\Component\Process\Process;

class BackupRestoreCommand extends Command
{
    protected $signature = 'backup:restore
                            {path : Path to backup directory or zip file}
                            {--disk=local : Storage disk where backup is located}
                            {--tenant= : Tenant ID to restore to (creates new if not exists)}
                            {--only-database : Only restore database}
                            {--only-files : Only restore files}
                            {--force : Skip confirmation prompts}
                            {--dry-run : Show what would be restored without executing}';

    protected $description = 'Restore a backup (system or tenant-specific)';

    public function handle(): int
    {
        $path = $this->argument('path');
        $disk = $this->option('disk');
        $dryRun = $this->option('dry-run');

        // Check if backup exists
        if (!Storage::disk($disk)->exists($path)) {
            $this->error("Backup not found: {$path}");
            return Command::FAILURE;
        }

        // Load manifest
        $manifestPath = rtrim($path, '/') . '/manifest.json';
        if (!Storage::disk($disk)->exists($manifestPath)) {
            $this->error("Manifest not found. Invalid backup directory.");
            return Command::FAILURE;
        }

        $manifest = json_decode(Storage::disk($disk)->get($manifestPath), true);

        $this->info("Backup Information:");
        $this->table(
            ['Property', 'Value'],
            [
                ['Tenant ID', $manifest['tenant_id'] ?? 'N/A (System backup)'],
                ['Tenant Name', $manifest['tenant_name'] ?? 'N/A'],
                ['Created At', $manifest['created_at']],
                ['Version', $manifest['version'] ?? 'Unknown'],
                ['Components', implode(', ', array_keys($manifest['components'] ?? []))],
            ]
        );

        if ($dryRun) {
            $this->info('Dry run mode - no changes will be made.');
            return Command::SUCCESS;
        }

        // Confirmation
        if (!$this->option('force')) {
            if (!$this->confirm('This will overwrite existing data. Continue?', false)) {
                $this->info('Restore cancelled.');
                return Command::SUCCESS;
            }
        }

        // Determine if this is a tenant backup
        if (isset($manifest['tenant_id'])) {
            return $this->restoreTenantBackup($path, $manifest, $disk);
        }

        return $this->restoreSystemBackup($path, $manifest, $disk);
    }

    protected function restoreTenantBackup(string $path, array $manifest, string $disk): int
    {
        $tenantId = $this->option('tenant') ?? $manifest['tenant_id'];

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("Tenant not found: {$tenantId}");
            $this->info("To create a new tenant from this backup, use: php artisan tenant:create");
            return Command::FAILURE;
        }

        $this->info("Restoring backup to tenant: {$tenant->name} ({$tenant->id})");

        try {
            // Restore database
            if (!$this->option('only-files') && isset($manifest['components']['database'])) {
                $this->restoreDatabase($tenant, $path, $manifest['components']['database'], $disk);
                $this->info("  - Database restored successfully");
            }

            // Restore files
            if (!$this->option('only-database') && isset($manifest['components']['files'])) {
                $this->restoreFiles($tenant, $path, $manifest['components']['files'], $disk);
                $this->info("  - Files restored successfully");
            }

            $this->info("Restore completed successfully!");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Restore failed: {$e->getMessage()}");
            \Log::error("Backup restore failed", [
                'tenant_id' => $tenantId,
                'path' => $path,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    protected function restoreSystemBackup(string $path, array $manifest, string $disk): int
    {
        $this->info("Restoring system backup...");

        try {
            // For system backups, use spatie/laravel-backup's approach
            // This is a simplified version - full implementation would integrate with spatie

            if (!$this->option('only-files') && isset($manifest['components']['database'])) {
                $this->restoreSystemDatabase($path, $manifest['components']['database'], $disk);
                $this->info("  - Database restored successfully");
            }

            if (!$this->option('only-database') && isset($manifest['components']['files'])) {
                $this->restoreSystemFiles($path, $manifest['components']['files'], $disk);
                $this->info("  - Files restored successfully");
            }

            $this->info("System restore completed successfully!");
            $this->warn("Please run 'php artisan config:clear' and 'php artisan cache:clear' after restore.");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("System restore failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    protected function restoreDatabase(Tenant $tenant, string $backupPath, array $dbManifest, string $disk): void
    {
        // Use main database config (tenants use schema-based isolation)
        $config = config('database.connections.pgsql');
        // Connect directly to PostgreSQL, not PgBouncer
        $host = env('DB_HOST_DIRECT', $config['host']);
        $port = env('DB_PORT_DIRECT', 5432); // Direct PostgreSQL port, not PgBouncer

        $filename = $dbManifest['filename'];
        $isCompressed = str_ends_with($filename, '.gz');

        // Download backup to temp
        $tempPath = storage_path("app/temp/restore_{$tenant->id}.sql" . ($isCompressed ? '.gz' : ''));

        if (!is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        file_put_contents(
            $tempPath,
            Storage::disk($disk)->get("{$backupPath}/{$filename}")
        );

        // Verify checksum if available
        if (isset($dbManifest['checksum'])) {
            $actualChecksum = md5_file($tempPath);
            if ($actualChecksum !== $dbManifest['checksum']) {
                unlink($tempPath);
                throw new \RuntimeException("Checksum mismatch for database backup");
            }
        }

        // Decompress if needed
        $sqlPath = $tempPath;
        if ($isCompressed) {
            $sqlPath = str_replace('.gz', '', $tempPath);
            $process = new Process(['gunzip', '-k', '-f', $tempPath]);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new \RuntimeException("Failed to decompress: " . $process->getErrorOutput());
            }
        }

        $env = ['PGPASSWORD' => $config['password']];
        $schemaName = $tenant->database_name;

        // First, drop the existing schema if it exists (CASCADE to drop all objects)
        $dropSchemaCommand = [
            'psql',
            '-h', $host,
            '-p', (string) $port,
            '-U', $config['username'],
            '-d', $config['database'],
            '-c', "DROP SCHEMA IF EXISTS \"{$schemaName}\" CASCADE;",
        ];

        $dropProcess = new Process($dropSchemaCommand, null, $env);
        $dropProcess->setTimeout(300);
        $dropProcess->run();

        if (!$dropProcess->isSuccessful()) {
            // Log but don't fail - schema might not exist
            \Log::warning("Schema drop returned error (may be ok if schema didn't exist): " . $dropProcess->getErrorOutput());
        }

        // Restore using psql - connect to main database, restore creates the schema from backup
        $command = [
            'psql',
            '-h', $host,
            '-p', (string) $port,
            '-U', $config['username'],
            '-d', $config['database'], // Main database (e.g., xlinic)
            '-f', $sqlPath,
        ];

        $process = new Process($command, null, $env);
        $process->setTimeout(600);
        $process->run();

        // Cleanup
        @unlink($tempPath);
        if ($isCompressed) {
            @unlink($sqlPath);
        }

        if (!$process->isSuccessful()) {
            throw new \RuntimeException("psql restore failed: " . $process->getErrorOutput());
        }
    }

    protected function restoreFiles(Tenant $tenant, string $backupPath, array $filesManifest, string $disk): void
    {
        $filename = $filesManifest['filename'];
        $isCompressed = str_ends_with($filename, '.gz');

        $tempPath = storage_path("app/temp/restore_{$tenant->id}_files.tar" . ($isCompressed ? '.gz' : ''));

        if (!is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        file_put_contents(
            $tempPath,
            Storage::disk($disk)->get("{$backupPath}/{$filename}")
        );

        // Verify checksum
        if (isset($filesManifest['checksum'])) {
            $actualChecksum = md5_file($tempPath);
            if ($actualChecksum !== $filesManifest['checksum']) {
                unlink($tempPath);
                throw new \RuntimeException("Checksum mismatch for files backup");
            }
        }

        // Prepare target directory
        $targetPath = storage_path("app/tenants/{$tenant->id}");

        // Backup existing files before overwriting
        if (is_dir($targetPath)) {
            $backupExisting = "{$targetPath}_pre_restore_" . time();
            rename($targetPath, $backupExisting);
        }

        mkdir($targetPath, 0755, true);

        // Extract
        $tarFlags = $isCompressed ? '-xzf' : '-xf';
        $command = [
            'tar',
            $tarFlags,
            $tempPath,
            '-C',
            $targetPath,
        ];

        $process = new Process($command);
        $process->setTimeout(600);
        $process->run();

        unlink($tempPath);

        if (!$process->isSuccessful()) {
            throw new \RuntimeException("tar extract failed: " . $process->getErrorOutput());
        }
    }

    protected function restoreSystemDatabase(string $backupPath, array $dbManifest, string $disk): void
    {
        $config = config('database.connections.' . config('database.default'));
        $filename = $dbManifest['filename'];
        $isCompressed = str_ends_with($filename, '.gz');

        $tempPath = storage_path("app/temp/restore_system.sql" . ($isCompressed ? '.gz' : ''));

        if (!is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        file_put_contents(
            $tempPath,
            Storage::disk($disk)->get("{$backupPath}/{$filename}")
        );

        $sqlPath = $tempPath;
        if ($isCompressed) {
            $sqlPath = str_replace('.gz', '', $tempPath);
            $process = new Process(['gunzip', '-k', '-f', $tempPath]);
            $process->run();
        }

        $env = ['PGPASSWORD' => $config['password']];

        $command = [
            'psql',
            '-h', $config['host'],
            '-p', (string) ($config['port'] ?? 5432),
            '-U', $config['username'],
            '-d', $config['database'],
            '-f', $sqlPath,
        ];

        $process = new Process($command, null, $env);
        $process->setTimeout(1800); // 30 minutes for system backup
        $process->run();

        @unlink($tempPath);
        if ($isCompressed) {
            @unlink($sqlPath);
        }

        if (!$process->isSuccessful()) {
            throw new \RuntimeException("System database restore failed: " . $process->getErrorOutput());
        }
    }

    protected function restoreSystemFiles(string $backupPath, array $filesManifest, string $disk): void
    {
        $filename = $filesManifest['filename'];
        $isCompressed = str_ends_with($filename, '.gz');

        $tempPath = storage_path("app/temp/restore_system_files.tar" . ($isCompressed ? '.gz' : ''));

        file_put_contents(
            $tempPath,
            Storage::disk($disk)->get("{$backupPath}/{$filename}")
        );

        $targetPath = storage_path('app');
        $tarFlags = $isCompressed ? '-xzf' : '-xf';

        $command = [
            'tar',
            $tarFlags,
            $tempPath,
            '-C',
            $targetPath,
            '--overwrite',
        ];

        $process = new Process($command);
        $process->setTimeout(1800);
        $process->run();

        unlink($tempPath);

        if (!$process->isSuccessful()) {
            throw new \RuntimeException("System files restore failed: " . $process->getErrorOutput());
        }
    }
}
