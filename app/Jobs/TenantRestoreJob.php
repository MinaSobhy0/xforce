<?php

namespace App\Jobs;

use App\Models\Backup;
use App\Models\RestoreRequest;
use Modules\Core\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantRestoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // Don't retry restores automatically
    public int $timeout = 1200; // 20 minutes

    protected RestoreRequest $restoreRequest;
    protected Backup $backup;
    protected Tenant $tenant;

    public function __construct(RestoreRequest $restoreRequest)
    {
        $this->restoreRequest = $restoreRequest;
        $this->backup = $restoreRequest->backup;
        $this->tenant = $restoreRequest->tenant;
    }

    public function handle(): void
    {
        $this->restoreRequest->markInProgress();

        try {
            // Verify backup exists
            if (!$this->backup->exists()) {
                throw new \Exception('Backup file not found: ' . $this->backup->path);
            }

            // Get database connection details
            $dbHost = $this->tenant->database_host ?? config('database.connections.pgsql.host');
            $dbPort = $this->tenant->database_port ?? config('database.connections.pgsql.port');
            $dbName = config('database.connections.pgsql.database');
            $dbUser = $this->tenant->database_username ?? config('database.connections.pgsql.username');
            $dbPass = $this->tenant->database_password ?? config('database.connections.pgsql.password');
            $schema = $this->tenant->database_name;

            // Get the backup file path
            $backupFilePath = $this->getBackupFilePath();

            // Create a temporary file if backup is compressed
            $tempFile = null;
            $sqlFile = $backupFilePath;

            if (str_ends_with($backupFilePath, '.gz')) {
                $tempFile = tempnam(sys_get_temp_dir(), 'restore_');
                $sqlFile = $tempFile;

                // Decompress the backup
                $decompressCommand = sprintf(
                    'gunzip -c %s > %s 2>&1',
                    escapeshellarg($backupFilePath),
                    escapeshellarg($tempFile)
                );

                $output = [];
                $returnCode = 0;
                exec($decompressCommand, $output, $returnCode);

                if ($returnCode !== 0) {
                    throw new \Exception('Failed to decompress backup: ' . implode("\n", $output));
                }
            }

            // Step 1: Drop existing schema objects (but keep the schema)
            Log::info('Dropping existing schema objects', ['schema' => $schema]);
            $this->dropSchemaObjects($schema);

            // Step 2: Restore from backup
            Log::info('Restoring from backup', [
                'backup_id' => $this->backup->id,
                'schema' => $schema,
            ]);

            $restoreCommand = sprintf(
                'PGPASSWORD=%s psql -h %s -p %s -U %s -d %s -f %s 2>&1',
                escapeshellarg($dbPass),
                escapeshellarg($dbHost),
                escapeshellarg($dbPort),
                escapeshellarg($dbUser),
                escapeshellarg($dbName),
                escapeshellarg($sqlFile)
            );

            $output = [];
            $returnCode = 0;
            exec($restoreCommand, $output, $returnCode);

            // Clean up temp file
            if ($tempFile && file_exists($tempFile)) {
                unlink($tempFile);
            }

            // Check for critical errors (some warnings are okay)
            $outputStr = implode("\n", $output);
            if ($returnCode !== 0 && strpos($outputStr, 'ERROR') !== false) {
                // Filter out common non-critical errors
                $criticalErrors = array_filter($output, function ($line) {
                    return strpos($line, 'ERROR') !== false
                        && strpos($line, 'already exists') === false
                        && strpos($line, 'does not exist') === false;
                });

                if (!empty($criticalErrors)) {
                    throw new \Exception('Restore failed with errors: ' . implode("\n", $criticalErrors));
                }
            }

            // Mark as completed
            $this->restoreRequest->markCompleted();

            Log::info('Tenant restore completed', [
                'tenant_id' => $this->tenant->id,
                'tenant_name' => $this->tenant->name,
                'backup_id' => $this->backup->id,
                'restore_request_id' => $this->restoreRequest->id,
            ]);

            // TODO: Send notification to tenant admin

        } catch (\Exception $e) {
            Log::error('Tenant restore failed', [
                'tenant_id' => $this->tenant->id,
                'restore_request_id' => $this->restoreRequest->id,
                'error' => $e->getMessage(),
            ]);

            $this->restoreRequest->markFailed($e->getMessage());

            throw $e;
        }
    }

    protected function getBackupFilePath(): string
    {
        $disk = $this->backup->disk;
        $path = $this->backup->path;

        if ($disk === 'local') {
            return storage_path('app/' . $path);
        }

        // For remote storage, download to temp file
        $tempFile = tempnam(sys_get_temp_dir(), 'backup_');
        $contents = Storage::disk($disk)->get($path);
        file_put_contents($tempFile, $contents);

        return $tempFile;
    }

    protected function dropSchemaObjects(string $schema): void
    {
        $dbHost = $this->tenant->database_host ?? config('database.connections.pgsql.host');
        $dbPort = $this->tenant->database_port ?? config('database.connections.pgsql.port');
        $dbName = config('database.connections.pgsql.database');
        $dbUser = $this->tenant->database_username ?? config('database.connections.pgsql.username');
        $dbPass = $this->tenant->database_password ?? config('database.connections.pgsql.password');

        // Drop all objects in schema but recreate the schema
        $sql = sprintf(
            "DROP SCHEMA IF EXISTS %s CASCADE; CREATE SCHEMA %s;",
            $schema,
            $schema
        );

        $command = sprintf(
            'PGPASSWORD=%s psql -h %s -p %s -U %s -d %s -c %s 2>&1',
            escapeshellarg($dbPass),
            escapeshellarg($dbHost),
            escapeshellarg($dbPort),
            escapeshellarg($dbUser),
            escapeshellarg($dbName),
            escapeshellarg($sql)
        );

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::warning('Schema drop/recreate had issues', [
                'schema' => $schema,
                'output' => $output,
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->restoreRequest->markFailed($exception->getMessage());

        Log::error('Tenant restore job failed permanently', [
            'tenant_id' => $this->tenant->id,
            'restore_request_id' => $this->restoreRequest->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
