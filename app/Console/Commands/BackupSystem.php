<?php

namespace App\Console\Commands;

use App\Jobs\SystemBackupJob;
use App\Models\Backup;
use App\Models\PlatformSetting;
use Illuminate\Console\Command;

class BackupSystem extends Command
{
    protected $signature = 'system:backup
                            {--type=database : Backup type: database (full pg_dump), files (storage tar), or full (both)}
                            {--force : Run even if auto_backup is disabled}';

    protected $description = 'Create a platform/system backup (central schema + all tenant schemas in one consistent pg_dump)';

    public function handle(): int
    {
        if (!$this->option('force') && !PlatformSetting::get('auto_backup', true)) {
            $this->info('Auto backup is disabled. Use --force to run anyway.');
            return 0;
        }

        $type = $this->option('type');

        if (!in_array($type, ['database', 'files', 'full'], true)) {
            $this->error("Invalid type: {$type}. Use database, files, or full.");
            return 1;
        }

        $backup = Backup::create([
            'name' => 'Auto System Backup - ' . now()->format('Y-m-d H:i'),
            'type' => $type,
            'disk' => 'local',
            'filename' => 'backup-' . now()->format('Y-m-d-His') . '.sql.gz',
            'status' => 'pending',
            'notes' => 'Automated scheduled system backup',
        ]);

        if (config('queue.default') === 'sync') {
            SystemBackupJob::dispatchSync($backup);
        } else {
            SystemBackupJob::dispatch($backup);
        }

        $this->info("System backup #{$backup->id} ({$type}) queued.");

        return 0;
    }
}
