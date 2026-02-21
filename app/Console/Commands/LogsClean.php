<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class LogsClean extends Command
{
    protected $signature = 'logs:clean
                            {--days=30 : Delete logs older than this many days}
                            {--dry-run : Show what would be deleted without deleting}
                            {--all : Delete all log files regardless of age}';

    protected $description = 'Clean old log files to free up disk space';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $deleteAll = $this->option('all');
        $days = (int) $this->option('days');

        if ($dryRun) {
            $this->info("[DRY RUN] No files will be deleted.");
            $this->newLine();
        }

        $logsPath = storage_path('logs');
        $totalSize = 0;
        $deletedFiles = 0;
        $deletedSize = 0;

        if (!File::isDirectory($logsPath)) {
            $this->error("Logs directory not found: {$logsPath}");
            return 1;
        }

        $files = File::files($logsPath);
        $cutoffDate = now()->subDays($days);

        $this->info("Scanning log files...");
        $this->newLine();

        foreach ($files as $file) {
            $fileName = $file->getFilename();
            $fileSize = $file->getSize();
            $lastModified = \Carbon\Carbon::createFromTimestamp($file->getMTime());

            $totalSize += $fileSize;

            // Determine if file should be deleted
            $shouldDelete = false;
            $reason = '';

            if ($deleteAll) {
                $shouldDelete = true;
                $reason = 'all logs requested';
            } elseif ($lastModified->lt($cutoffDate)) {
                $shouldDelete = true;
                $reason = "older than {$days} days";
            }

            if ($shouldDelete) {
                $deletedSize += $fileSize;
                $deletedFiles++;

                if ($dryRun) {
                    $this->line("  [WOULD DELETE] {$fileName} ({$this->formatBytes($fileSize)}) - {$reason}");
                } else {
                    File::delete($file->getPathname());
                    $this->line("  <fg=red>Deleted:</> {$fileName} ({$this->formatBytes($fileSize)})");
                }
            } else {
                $this->line("  <fg=green>Keeping:</> {$fileName} ({$this->formatBytes($fileSize)})");
            }
        }

        // Also clean Laravel's daily log files if using daily driver
        $this->cleanDailyLogs($logsPath, $cutoffDate, $dryRun, $deletedFiles, $deletedSize);

        $this->newLine();
        $this->info("=== Summary ===");
        $this->line("Total log files scanned: " . count($files));
        $this->line("Total size: " . $this->formatBytes($totalSize));
        $this->line("Files " . ($dryRun ? 'to be ' : '') . "deleted: {$deletedFiles}");
        $this->line("Space " . ($dryRun ? 'to be ' : '') . "freed: " . $this->formatBytes($deletedSize));

        if ($dryRun && $deletedFiles > 0) {
            $this->newLine();
            $this->warn("Run without --dry-run to actually delete the files.");
        }

        return 0;
    }

    protected function cleanDailyLogs(string $logsPath, $cutoffDate, bool $dryRun, int &$deletedFiles, int &$deletedSize): void
    {
        // Match Laravel daily log pattern: laravel-2024-01-15.log
        $pattern = $logsPath . '/laravel-*.log';
        $files = glob($pattern);

        foreach ($files as $file) {
            $fileName = basename($file);

            // Extract date from filename
            if (preg_match('/laravel-(\d{4}-\d{2}-\d{2})\.log/', $fileName, $matches)) {
                $fileDate = \Carbon\Carbon::parse($matches[1]);
                $fileSize = filesize($file);

                if ($fileDate->lt($cutoffDate)) {
                    $deletedSize += $fileSize;
                    $deletedFiles++;

                    if ($dryRun) {
                        $this->line("  [WOULD DELETE] {$fileName} ({$this->formatBytes($fileSize)}) - daily log");
                    } else {
                        unlink($file);
                        $this->line("  <fg=red>Deleted:</> {$fileName} ({$this->formatBytes($fileSize)})");
                    }
                }
            }
        }
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }
}
