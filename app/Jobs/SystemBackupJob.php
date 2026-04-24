<?php

namespace App\Jobs;

use App\Models\Backup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Dumps the entire PostgreSQL database (central schema + all tenant schemas)
 * in a single pg_dump transaction for a globally-consistent snapshot.
 * Also optionally tars storage/app (excluding backups/ and tmp/) for type=full|files.
 */
class SystemBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600;

    public function __construct(protected Backup $backup) {}

    public function handle(): void
    {
        $this->backup->markRunning();

        try {
            $type = $this->backup->type;
            $dir = storage_path('app/backups/system/' . $this->backup->id);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $components = [];
            $totalSize = 0;

            if (in_array($type, ['full', 'database'], true)) {
                $sqlPath = $dir . '/database.sql.gz';
                $this->dumpDatabase($sqlPath);
                $size = filesize($sqlPath);
                $components['database'] = ['file' => 'database.sql.gz', 'size' => $size];
                $totalSize += $size;
            }

            if (in_array($type, ['full', 'files'], true)) {
                $tarPath = $dir . '/files.tar.gz';
                $this->dumpFiles($tarPath);
                $size = filesize($tarPath);
                $components['files'] = ['file' => 'files.tar.gz', 'size' => $size];
                $totalSize += $size;
            }

            $this->writeManifest($dir, $components, $totalSize);

            $this->backup->markCompleted($totalSize, 'backups/system/' . $this->backup->id);

            Log::info('System backup completed', [
                'backup_id' => $this->backup->id,
                'type' => $type,
                'size' => $totalSize,
                'components' => array_keys($components),
            ]);
        } catch (\Throwable $e) {
            Log::error('System backup failed', [
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
    }

    protected function dumpDatabase(string $outputPath): void
    {
        $dbHost = env('DB_HOST_DIRECT', config('database.connections.pgsql.host'));
        $dbPort = env('DB_PORT_DIRECT', 5432);
        $dbName = config('database.connections.pgsql.database');
        $dbUser = config('database.connections.pgsql.username');
        $dbPass = config('database.connections.pgsql.password');

        $pgpassFile = $this->createSecurePgpassFile($dbHost, $dbPort, $dbName, $dbUser, $dbPass);

        try {
            // Single pg_dump of the entire database, piped straight to gzip.
            // --no-owner / --no-acl keeps the dump portable across environments.
            $escapedOut = escapeshellarg($outputPath);
            $cmd = sprintf(
                'pg_dump -h %s -p %s -U %s -d %s --no-owner --no-acl | gzip -9 > %s',
                escapeshellarg($dbHost),
                escapeshellarg((string) $dbPort),
                escapeshellarg($dbUser),
                escapeshellarg($dbName),
                $escapedOut
            );

            $result = Process::timeout($this->timeout)
                ->env([
                    'PGPASSFILE' => $pgpassFile,
                    'HOME' => storage_path('app/tmp'),
                ])
                ->run(['bash', '-c', $cmd . '; exit ${PIPESTATUS[0]}']);

            if (!$result->successful()) {
                throw new \RuntimeException('pg_dump failed: ' . $result->errorOutput());
            }

            if (!file_exists($outputPath) || filesize($outputPath) < 100) {
                throw new \RuntimeException('Database dump is empty or missing');
            }
        } finally {
            if (file_exists($pgpassFile)) {
                unlink($pgpassFile);
            }
        }
    }

    protected function dumpFiles(string $outputPath): void
    {
        $source = base_path('storage/app');
        $escapedOut = escapeshellarg($outputPath);

        $cmd = sprintf(
            'tar --exclude=%s --exclude=%s -czf %s -C %s %s',
            escapeshellarg('storage/app/backups'),
            escapeshellarg('storage/app/tmp'),
            $escapedOut,
            escapeshellarg(base_path()),
            escapeshellarg('storage/app')
        );

        $result = Process::timeout($this->timeout)->run(['bash', '-c', $cmd]);

        if (!$result->successful()) {
            throw new \RuntimeException('tar failed: ' . $result->errorOutput());
        }

        if (!file_exists($outputPath) || filesize($outputPath) < 100) {
            throw new \RuntimeException('Files archive is empty or missing');
        }
    }

    protected function writeManifest(string $dir, array $components, int $totalSize): void
    {
        $manifest = [
            'version' => '1.0',
            'type' => $this->backup->type,
            'backup_id' => $this->backup->id,
            'created_at' => now()->toIso8601String(),
            'pg_version' => $this->detectPgVersion(),
            'php_version' => PHP_VERSION,
            'components' => $components,
            'total_size' => $totalSize,
        ];

        file_put_contents($dir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
    }

    protected function detectPgVersion(): ?string
    {
        try {
            $row = \DB::selectOne('SHOW server_version');
            return $row?->server_version;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function createSecurePgpassFile(string $host, int|string $port, string $database, string $user, string $password): string
    {
        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0700, true);
        }

        $pgpassFile = $tmpDir . '/.pgpass_' . uniqid('sysbk_', true);
        $escapedPassword = str_replace(['\\', ':'], ['\\\\', '\\:'], $password);
        $pgpassContent = "{$host}:{$port}:{$database}:{$user}:{$escapedPassword}\n";

        file_put_contents($pgpassFile, $pgpassContent);
        chmod($pgpassFile, 0600);

        return $pgpassFile;
    }
}
