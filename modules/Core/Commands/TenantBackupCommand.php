<?php

namespace Modules\Core\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Models\Tenant;
use Symfony\Component\Process\Process;

class TenantBackupCommand extends Command
{
    protected $signature = 'tenants:backup
                            {tenant? : Tenant ID or slug (optional, backs up all if not specified)}
                            {--disk=local : Storage disk for backup}
                            {--only-database : Only backup database, skip files}
                            {--only-files : Only backup files, skip database}
                            {--compress : Compress backup with gzip}';

    protected $description = 'Backup tenant database and files';

    public function handle(): int
    {
        $tenantIdentifier = $this->argument('tenant');

        if ($tenantIdentifier) {
            $tenant = Tenant::where('id', $tenantIdentifier)
                ->orWhere('slug', $tenantIdentifier)
                ->first();

            if (!$tenant) {
                $this->error("Tenant not found: {$tenantIdentifier}");
                return Command::FAILURE;
            }

            return $this->backupTenant($tenant);
        }

        // Backup all active tenants
        $tenants = Tenant::active()->get();

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants found.');
            return Command::SUCCESS;
        }

        $this->info("Backing up {$tenants->count()} tenants...");

        $failed = 0;
        foreach ($tenants as $tenant) {
            if ($this->backupTenant($tenant) !== Command::SUCCESS) {
                $failed++;
            }
        }

        if ($failed > 0) {
            $this->warn("{$failed} tenant(s) failed to backup.");
            return Command::FAILURE;
        }

        $this->info('All tenants backed up successfully.');
        return Command::SUCCESS;
    }

    protected function backupTenant(Tenant $tenant): int
    {
        $this->info("Backing up tenant: {$tenant->name} ({$tenant->id})");

        $timestamp = now()->format('Y-m-d_H-i-s');
        $backupDir = "backups/tenants/{$tenant->id}/{$timestamp}";
        $disk = $this->option('disk');
        $compress = $this->option('compress');

        try {
            // Ensure backup directory exists
            Storage::disk($disk)->makeDirectory($backupDir);

            $manifest = [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'tenant_slug' => $tenant->slug,
                'created_at' => now()->toIso8601String(),
                'version' => config('app.version', '1.0.0'),
                'components' => [],
            ];

            // Backup database
            if (!$this->option('only-files')) {
                $dbResult = $this->backupDatabase($tenant, $backupDir, $disk, $compress);
                if ($dbResult) {
                    $manifest['components']['database'] = $dbResult;
                    $this->line("  - Database backup: {$dbResult['size_human']}");
                }
            }

            // Backup files
            if (!$this->option('only-database')) {
                $filesResult = $this->backupFiles($tenant, $backupDir, $disk, $compress);
                if ($filesResult) {
                    $manifest['components']['files'] = $filesResult;
                    $this->line("  - Files backup: {$filesResult['size_human']}");
                }
            }

            // Save manifest
            Storage::disk($disk)->put(
                "{$backupDir}/manifest.json",
                json_encode($manifest, JSON_PRETTY_PRINT)
            );

            $this->info("  Backup completed: {$backupDir}");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("  Backup failed: {$e->getMessage()}");
            \Log::error("Tenant backup failed for {$tenant->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    protected function backupDatabase(Tenant $tenant, string $backupDir, string $disk, bool $compress): ?array
    {
        $config = $tenant->getDatabaseConfig();
        $filename = $compress ? 'database.sql.gz' : 'database.sql';
        $tempPath = storage_path("app/temp/{$tenant->id}_{$filename}");

        // Ensure temp directory exists
        if (!is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        // Build pg_dump command
        $command = [
            'pg_dump',
            '-h', $config['host'],
            '-p', (string) ($config['port'] ?? 5432),
            '-U', $config['username'],
            '-d', $config['database'],
            '--no-owner',
            '--no-acl',
            '-F', 'p', // Plain text format
        ];

        if ($config['schema'] ?? null) {
            $command[] = '--schema=' . $config['schema'];
        }

        $env = ['PGPASSWORD' => $config['password']];

        if ($compress) {
            // Pipe through gzip
            $process = Process::fromShellCommandline(
                implode(' ', array_map('escapeshellarg', $command)) . ' | gzip > ' . escapeshellarg($tempPath),
                null,
                $env
            );
        } else {
            $command[] = '-f';
            $command[] = $tempPath;
            $process = new Process($command, null, $env);
        }

        $process->setTimeout(600); // 10 minutes
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException("pg_dump failed: " . $process->getErrorOutput());
        }

        // Move to storage
        $content = file_get_contents($tempPath);
        Storage::disk($disk)->put("{$backupDir}/{$filename}", $content);

        $size = filesize($tempPath);
        unlink($tempPath);

        return [
            'filename' => $filename,
            'size' => $size,
            'size_human' => $this->humanFilesize($size),
            'checksum' => md5($content),
        ];
    }

    protected function backupFiles(Tenant $tenant, string $backupDir, string $disk, bool $compress): ?array
    {
        $tenantStoragePath = storage_path("app/tenants/{$tenant->id}");

        if (!is_dir($tenantStoragePath)) {
            $this->line("  - No files to backup for tenant");
            return null;
        }

        $filename = $compress ? 'files.tar.gz' : 'files.tar';
        $tempPath = storage_path("app/temp/{$tenant->id}_{$filename}");

        // Ensure temp directory exists
        if (!is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        // Build tar command
        $tarFlags = $compress ? '-czf' : '-cf';
        $command = [
            'tar',
            $tarFlags,
            $tempPath,
            '-C',
            $tenantStoragePath,
            '.',
        ];

        $process = new Process($command);
        $process->setTimeout(600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException("tar failed: " . $process->getErrorOutput());
        }

        // Move to storage
        $content = file_get_contents($tempPath);
        Storage::disk($disk)->put("{$backupDir}/{$filename}", $content);

        $size = filesize($tempPath);
        unlink($tempPath);

        return [
            'filename' => $filename,
            'size' => $size,
            'size_human' => $this->humanFilesize($size),
            'checksum' => md5($content),
        ];
    }

    protected function humanFilesize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
