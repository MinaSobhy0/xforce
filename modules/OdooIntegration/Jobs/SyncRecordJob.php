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

class SyncRecordJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times to retry.
     */
    public int $tries = 3;

    /**
     * Backoff between retries.
     */
    public array $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $entityMappingId,
        public ?int $localId = null,
        public ?int $odooId = null,
        public string $direction = 'import',
    ) {
        $this->onQueue(config('odoo-integration.queue_name', 'odoo-sync'));
    }

    /**
     * Execute the job.
     */
    public function handle(SyncEngine $syncEngine): void
    {
        $mapping = OdooEntityMapping::find($this->entityMappingId);

        if (!$mapping || !$mapping->is_active) {
            Log::info('Odoo record sync skipped - mapping inactive', [
                'mapping_id' => $this->entityMappingId,
            ]);
            return;
        }

        Log::info('Starting Odoo record sync', [
            'mapping_id' => $mapping->id,
            'local_id' => $this->localId,
            'odoo_id' => $this->odooId,
            'direction' => $this->direction,
        ]);

        try {
            $result = $syncEngine->syncRecord(
                $mapping,
                $this->localId,
                $this->odooId,
                $this->direction
            );

            Log::info('Odoo record sync completed', [
                'mapping_id' => $mapping->id,
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Odoo record sync failed', [
                'mapping_id' => $mapping->id,
                'local_id' => $this->localId,
                'odoo_id' => $this->odooId,
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
        Log::error('Odoo record sync job failed permanently', [
            'mapping_id' => $this->entityMappingId,
            'local_id' => $this->localId,
            'odoo_id' => $this->odooId,
            'error' => $exception->getMessage(),
        ]);
    }
}
