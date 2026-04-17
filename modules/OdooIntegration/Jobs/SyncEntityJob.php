<?php

namespace Modules\OdooIntegration\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\OdooIntegration\Models\OdooEntityMapping;
use Modules\OdooIntegration\Services\Sync\SyncEngine;

class SyncEntityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times to retry.
     */
    public int $tries = 3;

    /**
     * Backoff between retries (in seconds).
     */
    public array $backoff = [60, 300, 900];

    /**
     * The job timeout in seconds (30 minutes for large syncs).
     */
    public int $timeout = 1800;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $entityMappingId,
        public string $syncType = 'delta',
        public ?int $triggeredBy = null,
    ) {
        $this->onQueue(config('odoo-integration.queue_name', 'odoo-sync'));
        $this->onConnection('tenant'); // Use tenant connection for job storage
    }

    /**
     * Execute the job.
     */
    public function handle(SyncEngine $syncEngine): void
    {
        $mapping = OdooEntityMapping::find($this->entityMappingId);

        if (!$mapping || !$mapping->is_active) {
            Log::info('Odoo sync skipped - mapping inactive', [
                'mapping_id' => $this->entityMappingId,
            ]);
            return;
        }

        Log::info('Starting Odoo entity sync', [
            'mapping_id' => $mapping->id,
            'entity' => $mapping->name,
            'sync_type' => $this->syncType,
        ]);

        try {
            $log = $syncEngine->syncEntity($mapping, $this->syncType, $this->triggeredBy);

            Log::info('Odoo entity sync completed', [
                'mapping_id' => $mapping->id,
                'log_id' => $log->id,
                'processed' => $log->records_processed,
                'created' => $log->records_created,
                'updated' => $log->records_updated,
                'failed' => $log->records_failed,
            ]);
        } catch (\Exception $e) {
            Log::error('Odoo entity sync failed', [
                'mapping_id' => $mapping->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Odoo sync job failed permanently', [
            'mapping_id' => $this->entityMappingId,
            'error' => $exception->getMessage(),
        ]);
    }
}
