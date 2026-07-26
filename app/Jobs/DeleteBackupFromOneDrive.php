<?php

namespace App\Jobs;

use App\Services\OneDriveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Removes a backup's OneDrive copy when the local one is deleted, so the
 * retention window (backup_retention days) applies off-site exactly as it
 * does locally. Takes the remote path (not the Backup model) because the
 * record is already gone by the time this runs.
 */
class DeleteBackupFromOneDrive implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int> Seconds between retries. */
    public array $backoff = [60, 300];

    public function __construct(protected string $remotePath) {}

    public function handle(OneDriveService $oneDrive): void
    {
        if (!OneDriveService::isConnected()) {
            return;
        }

        $oneDrive->delete($this->remotePath);

        Log::info('OneDrive backup copy deleted', ['remote_path' => $this->remotePath]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('OneDrive backup deletion failed permanently', [
            'remote_path' => $this->remotePath,
            'error' => $exception->getMessage(),
        ]);
    }
}
