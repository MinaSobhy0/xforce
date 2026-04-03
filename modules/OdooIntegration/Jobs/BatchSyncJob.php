<?php

namespace Modules\OdooIntegration\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\OdooIntegration\Models\OdooConnection;
use Modules\OdooIntegration\Models\OdooEntityMapping;
use Modules\OdooIntegration\Services\Sync\SyncEngine;

class BatchSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times to retry.
     */
    public int $tries = 3;

    /**
     * Timeout in seconds.
     */
    public int $timeout = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?int $connectionId = null,
        public ?int $tenantId = null,
        public string $syncType = 'delta',
        public ?int $triggeredBy = null,
    ) {
        $this->onQueue(config('odoo-integration.queue_name', 'odoo-sync'));
    }

    /**
     * Execute the job.
     */
    public function handle(SyncEngine $syncEngine): void
    {
        Log::info('Starting batch Odoo sync', [
            'connection_id' => $this->connectionId,
            'tenant_id' => $this->tenantId,
            'sync_type' => $this->syncType,
        ]);

        $connectionsQuery = OdooConnection::query()
            ->where('is_active', true);

        if ($this->connectionId) {
            $connectionsQuery->where('id', $this->connectionId);
        }

        if ($this->tenantId) {
            $connectionsQuery->where('tenant_id', $this->tenantId);
        }

        $connections = $connectionsQuery->get();

        foreach ($connections as $connection) {
            $this->syncConnection($connection, $syncEngine);
        }

        Log::info('Batch Odoo sync completed');
    }

    /**
     * Sync all mappings for a connection.
     */
    protected function syncConnection(OdooConnection $connection, SyncEngine $syncEngine): void
    {
        Log::info('Syncing connection', [
            'connection_id' => $connection->id,
            'name' => $connection->name,
        ]);

        try {
            $results = $syncEngine->syncAll($connection, $this->triggeredBy);

            Log::info('Connection sync completed', [
                'connection_id' => $connection->id,
                'results' => collect($results)->map(fn ($r) => $r['action'] ?? 'error')->toArray(),
            ]);
        } catch (\Exception $e) {
            Log::error('Connection sync failed', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Batch Odoo sync job failed', [
            'connection_id' => $this->connectionId,
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
        ]);
    }
}
