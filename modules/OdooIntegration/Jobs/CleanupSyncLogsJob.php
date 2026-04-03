<?php

namespace Modules\OdooIntegration\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\OdooIntegration\Models\OdooSyncLog;
use Modules\OdooIntegration\Models\OdooSyncConflict;

class CleanupSyncLogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times to retry.
     */
    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?int $retentionDays = null,
    ) {
        $this->onQueue(config('odoo-integration.queue_name', 'odoo-sync'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $retentionDays = $this->retentionDays ?? config('odoo-integration.log_retention_days', 30);
        $cutoffDate = now()->subDays($retentionDays);

        Log::info('Starting Odoo sync logs cleanup', [
            'retention_days' => $retentionDays,
            'cutoff_date' => $cutoffDate->toDateString(),
        ]);

        // Delete old sync logs
        $logsDeleted = OdooSyncLog::where('created_at', '<', $cutoffDate)
            ->whereIn('status', ['completed', 'failed', 'cancelled'])
            ->delete();

        Log::info('Deleted old sync logs', ['count' => $logsDeleted]);

        // Delete resolved/dismissed conflicts older than retention
        $conflictsDeleted = OdooSyncConflict::where('created_at', '<', $cutoffDate)
            ->whereIn('status', ['resolved', 'dismissed'])
            ->delete();

        Log::info('Deleted old resolved conflicts', ['count' => $conflictsDeleted]);

        Log::info('Odoo sync logs cleanup completed', [
            'logs_deleted' => $logsDeleted,
            'conflicts_deleted' => $conflictsDeleted,
        ]);
    }
}
