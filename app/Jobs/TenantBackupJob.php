<?php

namespace App\Jobs;

use App\Models\Backup;
use Modules\Core\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class TenantBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600; // 10 minutes

    protected Backup $backup;
    protected Tenant $tenant;

    public function __construct(Backup $backup)
    {
        $this->backup = $backup;
        $this->tenant = $backup->tenant;
    }

    public function handle(): void
    {
        $this->backup->markRunning();

        try {
            // Get database connection details
            // Use direct PostgreSQL port (5432) instead of PgBouncer (6432) for pg_dump
            $dbHost = env('DB_HOST_DIRECT', $this->tenant->database_host ?? config('database.connections.pgsql.host'));
            $dbPort = env('DB_PORT_DIRECT', 5432); // Direct PostgreSQL port, not PgBouncer
            $dbName = config('database.connections.pgsql.database'); // Main database
            $dbUser = $this->tenant->database_username ?? config('database.connections.pgsql.username');
            $dbPass = $this->tenant->database_password ?? config('database.connections.pgsql.password');
            $schema = $this->tenant->database_name; // Tenant schema name

            // Check if tenant schema exists before attempting backup
            if (!$this->schemaExists($schema, $dbHost, $dbPort, $dbName, $dbUser, $dbPass)) {
                Log::warning('Skipping backup - tenant schema not provisioned', [
                    'tenant_id' => $this->tenant->id,
                    'schema' => $schema,
                ]);
                $this->backup->markFailed('Schema not provisioned');
                return;
            }

            // Create backup directory if it doesn't exist
            $backupDir = storage_path('app/backups/tenants/' . $this->tenant->id);
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            // Generate backup filename
            $filename = sprintf(
                'tenant_%s_%s.sql.gz',
                $this->tenant->slug,
                now()->format('Y-m-d_His')
            );
            $backupPath = $backupDir . '/' . $filename;

            // Build pg_dump command for schema-only backup
            $command = sprintf(
                'PGPASSWORD=%s pg_dump -h %s -p %s -U %s -d %s -n %s --no-owner --no-acl | gzip > %s 2>&1',
                escapeshellarg($dbPass),
                escapeshellarg($dbHost),
                escapeshellarg($dbPort),
                escapeshellarg($dbUser),
                escapeshellarg($dbName),
                escapeshellarg($schema),
                escapeshellarg($backupPath)
            );

            // Execute backup command
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception('pg_dump failed: ' . implode("\n", $output));
            }

            // Verify backup file exists and has content
            if (!file_exists($backupPath) || filesize($backupPath) < 100) {
                throw new \Exception('Backup file is empty or not created');
            }

            // Get file size
            $fileSize = filesize($backupPath);

            // Move to storage disk
            $storagePath = 'backups/tenants/' . $this->tenant->id . '/' . $filename;

            // If using local disk, the file is already in the right place
            // For S3 or other disks, we'd need to upload
            if ($this->backup->disk !== 'local') {
                Storage::disk($this->backup->disk)->put(
                    $storagePath,
                    file_get_contents($backupPath)
                );
                // Remove local file after upload
                unlink($backupPath);
            } else {
                $storagePath = 'backups/tenants/' . $this->tenant->id . '/' . $filename;
            }

            // Mark backup as completed
            $this->backup->markCompleted($fileSize, $storagePath);

            Log::info('Tenant backup completed', [
                'tenant_id' => $this->tenant->id,
                'tenant_name' => $this->tenant->name,
                'backup_id' => $this->backup->id,
                'size' => $fileSize,
                'path' => $storagePath,
            ]);

        } catch (\Exception $e) {
            Log::error('Tenant backup failed', [
                'tenant_id' => $this->tenant->id,
                'backup_id' => $this->backup->id,
                'error' => $e->getMessage(),
            ]);

            $this->backup->markFailed($e->getMessage());

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->backup->markFailed($exception->getMessage());

        Log::error('Tenant backup job failed permanently', [
            'tenant_id' => $this->tenant->id,
            'backup_id' => $this->backup->id,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Check if the tenant schema exists in the database.
     */
    protected function schemaExists(string $schema, string $host, int $port, string $database, string $user, string $password): bool
    {
        try {
            $command = sprintf(
                'PGPASSWORD=%s psql -h %s -p %s -U %s -d %s -tAc "SELECT 1 FROM information_schema.schemata WHERE schema_name = %s" 2>/dev/null',
                escapeshellarg($password),
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($user),
                escapeshellarg($database),
                escapeshellarg($schema)
            );

            $output = trim(shell_exec($command) ?? '');
            return $output === '1';
        } catch (\Exception $e) {
            Log::warning('Failed to check schema existence', [
                'schema' => $schema,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
