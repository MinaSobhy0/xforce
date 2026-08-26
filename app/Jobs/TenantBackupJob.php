<?php

namespace App\Jobs;

use App\Models\Backup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Tenant;

class TenantBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    // Matches SystemBackupJob: a multi-GB schema takes well over the previous
    // 10 minutes to dump and compress, and a timeout mid-dump leaves a partial file.
    public int $timeout = 3600; // 1 hour

    protected Backup $backup;

    protected Tenant $tenant;

    /**
     * Pattern for valid schema names (alphanumeric and underscores only).
     */
    protected const SCHEMA_NAME_PATTERN = '/^[a-z][a-z0-9_]*$/';

    public function __construct(Backup $backup)
    {
        $this->backup = $backup;
        $this->tenant = $backup->tenant;
    }

    /**
     * Validate schema name to prevent command injection.
     */
    protected function validateSchemaName(string $schema): bool
    {
        return preg_match(self::SCHEMA_NAME_PATTERN, $schema) === 1
            && strlen($schema) <= 63
            && ! in_array($schema, ['public', 'pg_catalog', 'information_schema']);
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

            // SECURITY: Validate schema name to prevent command injection
            if (! $this->validateSchemaName($schema)) {
                Log::error('Invalid schema name - potential security issue', [
                    'tenant_id' => $this->tenant->id,
                    'schema' => $schema,
                ]);
                $this->backup->markFailed('Invalid schema name');

                return;
            }

            // Check if tenant schema exists before attempting backup using safe PDO query
            if (! $this->schemaExistsSafe($schema)) {
                Log::warning('Skipping backup - tenant schema not provisioned', [
                    'tenant_id' => $this->tenant->id,
                    'schema' => $schema,
                ]);
                $this->backup->markFailed('Schema not provisioned');

                return;
            }

            // Create backup directory if it doesn't exist
            $backupDir = storage_path('app/backups/tenants/'.$this->tenant->id);
            if (! is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            // Generate backup filename with sanitized slug
            $safeSlug = preg_replace('/[^a-z0-9_-]/', '', $this->tenant->slug);
            $filename = sprintf(
                'tenant_%s_%s.sql.gz',
                $safeSlug,
                now()->format('Y-m-d_His')
            );
            $backupPath = $backupDir.'/'.$filename;

            // SECURITY: Create temporary .pgpass file instead of using PGPASSWORD env var
            // This prevents password from being visible in process listings
            $pgpassFile = $this->createSecurePgpassFile($dbHost, $dbPort, $dbName, $dbUser, $dbPass);

            try {
                // Stream pg_dump straight through gzip into the target file.
                //
                // Never capture the dump on stdout: Symfony Process buffers captured
                // output into php://temp, which spills to a file in the system temp
                // dir past 1MB. With a multi-GB schema that grew to 80GB+ in /tmp,
                // and gzencode($result->output()) then loaded the whole dump into a
                // single string, OOM-killing the worker. Piping to a file keeps both
                // disk and memory use flat regardless of schema size.
                //
                // Uses the .pgpass file for authentication (see $pgpassFile).
                // ${PIPESTATUS[0]} propagates pg_dump's exit code rather than gzip's,
                // so a failed dump cannot masquerade as success and leave a
                // silently-truncated backup behind.
                $cmd = sprintf(
                    'pg_dump -h %s -p %s -U %s -d %s -n %s --no-owner --no-acl | gzip -9 > %s',
                    escapeshellarg($dbHost),
                    escapeshellarg((string) $dbPort),
                    escapeshellarg($dbUser),
                    escapeshellarg($dbName),
                    escapeshellarg($schema),
                    escapeshellarg($backupPath)
                );

                $result = Process::timeout($this->timeout)
                    ->env([
                        'PGPASSFILE' => $pgpassFile,
                        'HOME' => storage_path('app/tmp'), // Required for .pgpass
                    ])
                    ->run(['bash', '-c', $cmd.'; exit ${PIPESTATUS[0]}']);

                if (! $result->successful()) {
                    // Remove the partial file so a truncated dump is never mistaken
                    // for a usable backup.
                    if (file_exists($backupPath)) {
                        unlink($backupPath);
                    }

                    throw new \Exception('pg_dump failed: '.$result->errorOutput());
                }

            } finally {
                // SECURITY: Always clean up the pgpass file
                if (file_exists($pgpassFile)) {
                    unlink($pgpassFile);
                }
            }

            // Verify backup file exists and has content
            if (! file_exists($backupPath) || filesize($backupPath) < 100) {
                throw new \Exception('Backup file is empty or not created');
            }

            // Get file size
            $fileSize = filesize($backupPath);

            // Move to storage disk
            $storagePath = 'backups/tenants/'.$this->tenant->id.'/'.$filename;

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
                $storagePath = 'backups/tenants/'.$this->tenant->id.'/'.$filename;
            }

            // Mark backup as completed
            $this->backup->markCompleted($fileSize, $storagePath);

            if (\App\Services\BackblazeService::isEnabled()) {
                UploadBackupToBackblaze::dispatch($this->backup);
            }

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
     * Create a secure .pgpass file for PostgreSQL authentication.
     * SECURITY: This prevents password exposure in process listings.
     */
    protected function createSecurePgpassFile(string $host, int $port, string $database, string $user, string $password): string
    {
        $tmpDir = storage_path('app/tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0700, true);
        }

        $pgpassFile = $tmpDir.'/.pgpass_'.uniqid('backup_', true);

        // Format: hostname:port:database:username:password
        // Escape colons and backslashes in password
        $escapedPassword = str_replace(['\\', ':'], ['\\\\', '\\:'], $password);
        $pgpassContent = "{$host}:{$port}:{$database}:{$user}:{$escapedPassword}\n";

        // Write with restrictive permissions (required by PostgreSQL)
        file_put_contents($pgpassFile, $pgpassContent);
        chmod($pgpassFile, 0600);

        return $pgpassFile;
    }

    /**
     * Check if the tenant schema exists using safe PDO query.
     * SECURITY: Uses parameterized query instead of shell command.
     */
    protected function schemaExistsSafe(string $schema): bool
    {
        try {
            $result = DB::select(
                'SELECT 1 FROM information_schema.schemata WHERE schema_name = ?',
                [$schema]
            );

            return count($result) > 0;
        } catch (\Exception $e) {
            Log::warning('Failed to check schema existence', [
                'schema' => $schema,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @deprecated Use schemaExistsSafe() instead.
     * Check if the tenant schema exists in the database.
     */
    protected function schemaExists(string $schema, string $host, int $port, string $database, string $user, string $password): bool
    {
        // SECURITY: Delegate to safe PDO-based method instead of shell command
        return $this->schemaExistsSafe($schema);
    }
}
