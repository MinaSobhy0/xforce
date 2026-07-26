<?php

namespace App\Jobs;

use App\Models\Backup;
use App\Services\OneDriveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors a completed backup to OneDrive. Tenant backups are a single file;
 * system backups are a directory (dump + manifest) mirrored file-by-file.
 * Runs as its own queued job so a OneDrive outage never fails the backup.
 */
class UploadBackupToOneDrive implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 3600;

    /** @var array<int> Seconds between retries. */
    public array $backoff = [60, 300];

    public function __construct(protected Backup $backup) {}

    public function handle(OneDriveService $oneDrive): void
    {
        if (!OneDriveService::isEnabled()) {
            return;
        }

        if ($this->backup->status !== 'completed' || !$this->backup->path) {
            return;
        }

        $localBase = storage_path('app/' . $this->backup->path);
        $remoteBase = OneDriveService::remotePathFor($this->backup->path);
        $uploaded = 0;

        if (is_dir($localBase)) {
            foreach (scandir($localBase) as $entry) {
                $file = $localBase . '/' . $entry;
                if ($entry === '.' || $entry === '..' || !is_file($file)) {
                    continue;
                }
                $oneDrive->upload($file, $remoteBase . '/' . $entry);
                $uploaded++;
            }
        } else {
            $oneDrive->upload($localBase, $remoteBase);
            $uploaded++;
        }

        Log::info('Backup mirrored to OneDrive', [
            'backup_id' => $this->backup->id,
            'remote_path' => $remoteBase,
            'files' => $uploaded,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('OneDrive backup upload failed permanently', [
            'backup_id' => $this->backup->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
