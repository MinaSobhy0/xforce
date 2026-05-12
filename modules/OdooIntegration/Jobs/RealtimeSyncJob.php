<?php

namespace Modules\OdooIntegration\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\OdooIntegration\Models\OdooEntityMapping;
use Modules\OdooIntegration\Services\RealtimeSyncManager;
use Modules\OdooIntegration\Services\Sync\SyncEngine;

/**
 * Push a single local record to Odoo on demand. Dispatched by the
 * eloquent.saved: * listener when a record belongs to a mapping with
 * sync_frequency=REALTIME and the direction allows export.
 */
class RealtimeSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public int $timeout = 120;

    public function __construct(
        public int $entityMappingId,
        public int $localId,
    ) {
        $this->onQueue(config('odoo-integration.queue_name', 'odoo-sync'));
        $this->onConnection('tenant');
    }

    public function handle(SyncEngine $syncEngine): void
    {
        $mapping = OdooEntityMapping::find($this->entityMappingId);

        if (! $mapping || ! $mapping->is_active) {
            return;
        }

        if (! $mapping->sync_direction->allowsExport()) {
            return;
        }

        try {
            // Suppress so the post-export odoo_id write doesn't echo back.
            RealtimeSyncManager::suppress(function () use ($syncEngine, $mapping) {
                $syncEngine->syncRecord(
                    mapping: $mapping,
                    localId: $this->localId,
                    direction: 'export',
                );
            });
        } catch (\Throwable $e) {
            Log::warning('Realtime Odoo sync failed', [
                'mapping_id' => $mapping->id,
                'local_id' => $this->localId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
