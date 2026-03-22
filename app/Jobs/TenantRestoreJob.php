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
use Illuminate\Support\Facades\Process;

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
        $pgpassFile = null;
        $tempFile = null;

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

            // SECURITY: Validate schema name to prevent SQL injection
            if (!$this->validateSchemaName($schema)) {
                throw new \Exception('Invalid schema name format');
            }

            // Get the backup file path
            $backupFilePath = $this->getBackupFilePath();

            // Create a temporary file if backup is compressed
            $sqlFile = $backupFilePath;

            if (str_ends_with($backupFilePath, '.gz')) {
                $tempFile = tempnam(sys_get_temp_dir(), 'restore_');
                $sqlFile = $tempFile;

                // SECURITY: Use Process facade instead of exec()
                $result = Process::timeout(600)->run([
                    'gunzip', '-c', $backupFilePath,
                ]);

                if (!$result->successful()) {
                    throw new \Exception('Failed to decompress backup: ' . $result->errorOutput());
                }

                file_put_contents($tempFile, $result->output());
            }

            // SECURITY: Create secure .pgpass file instead of using PGPASSWORD in command
            $pgpassFile = $this->createSecurePgpassFile($dbHost, (int) $dbPort, $dbName, $dbUser, $dbPass);

            // Step 1: Drop existing schema objects (but keep the schema)
            Log::info('Dropping existing schema objects', ['schema' => $schema]);
            $this->dropSchemaObjects($schema, $pgpassFile);

            // Step 2: Restore from backup
            Log::info('Restoring from backup', [
                'backup_id' => $this->backup->id,
                'schema' => $schema,
            ]);

            // SECURITY: Use Process facade with .pgpass file for authentication
            $result = Process::timeout(1200)
                ->env(['PGPASSFILE' => $pgpassFile])
                ->run([
                    'psql',
                    '-h', $dbHost,
                    '-p', (string) $dbPort,
                    '-U', $dbUser,
                    '-d', $dbName,
                    '-f', $sqlFile,
                ]);

            $outputStr = $result->output() . $result->errorOutput();

            // Check for critical errors (some warnings are okay)
            if (!$result->successful() && strpos($outputStr, 'ERROR') !== false) {
                // Filter out common non-critical errors
                $lines = explode("\n", $outputStr);
                $criticalErrors = array_filter($lines, function ($line) {
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

        } catch (\Exception $e) {
            Log::error('Tenant restore failed', [
                'tenant_id' => $this->tenant->id,
                'restore_request_id' => $this->restoreRequest->id,
                'error' => $e->getMessage(),
            ]);

            $this->restoreRequest->markFailed($e->getMessage());

            throw $e;
        } finally {
            // SECURITY: Always clean up temp files
            if ($tempFile && file_exists($tempFile)) {
                unlink($tempFile);
            }
            if ($pgpassFile && file_exists($pgpassFile)) {
                unlink($pgpassFile);
            }
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

    protected function dropSchemaObjects(string $schema, string $pgpassFile): void
    {
        $dbHost = $this->tenant->database_host ?? config('database.connections.pgsql.host');
        $dbPort = $this->tenant->database_port ?? config('database.connections.pgsql.port');
        $dbName = config('database.connections.pgsql.database');
        $dbUser = $this->tenant->database_username ?? config('database.connections.pgsql.username');

        // SECURITY: Use quoted identifier for schema name
        $quotedSchema = '"' . str_replace('"', '""', $schema) . '"';

        // Drop all objects in schema but recreate the schema
        $sql = "DROP SCHEMA IF EXISTS {$quotedSchema} CASCADE; CREATE SCHEMA {$quotedSchema};";

        // SECURITY: Use Process facade with .pgpass file for authentication
        $result = Process::timeout(120)
            ->env(['PGPASSFILE' => $pgpassFile])
            ->run([
                'psql',
                '-h', $dbHost,
                '-p', (string) $dbPort,
                '-U', $dbUser,
                '-d', $dbName,
                '-c', $sql,
            ]);

        if (!$result->successful()) {
            Log::warning('Schema drop/recreate had issues', [
                'schema' => $schema,
                'output' => $result->errorOutput(),
            ]);
        }
    }

    /**
     * SECURITY: Create a secure .pgpass file for PostgreSQL authentication.
     * This avoids exposing the password in command line arguments or process list.
     */
    protected function createSecurePgpassFile(string $host, int $port, string $database, string $user, string $password): string
    {
        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0700, true);
        }

        $pgpassFile = $tmpDir . '/.pgpass_' . uniqid('restore_', true);

        // Escape special characters in password for .pgpass format
        $escapedPassword = str_replace(['\\', ':'], ['\\\\', '\\:'], $password);

        $pgpassContent = "{$host}:{$port}:{$database}:{$user}:{$escapedPassword}\n";

        file_put_contents($pgpassFile, $pgpassContent);
        chmod($pgpassFile, 0600);

        return $pgpassFile;
    }

    /**
     * SECURITY: Validate schema name to prevent SQL injection.
     */
    protected function validateSchemaName(string $schemaName): bool
    {
        // Schema names must be lowercase alphanumeric with underscores
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $schemaName)) {
            return false;
        }

        // Max length for PostgreSQL identifiers
        if (strlen($schemaName) > 63) {
            return false;
        }

        // Reserved schema names
        $reserved = ['public', 'pg_catalog', 'information_schema', 'pg_toast', 'pg_temp'];
        if (in_array(strtolower($schemaName), $reserved)) {
            return false;
        }

        return true;
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
