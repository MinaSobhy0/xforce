<?php

namespace Modules\OdooIntegration\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\OdooIntegration\Events\SyncStarted;
use Modules\OdooIntegration\Events\SyncCompleted;
use Modules\OdooIntegration\Events\SyncFailed;
use Modules\OdooIntegration\Events\RecordSynced;
use Modules\OdooIntegration\Events\ConflictDetected;

class LogSyncActivity
{
    /**
     * Handle the event.
     */
    public function handle($event): void
    {
        match (get_class($event)) {
            SyncStarted::class => $this->handleSyncStarted($event),
            SyncCompleted::class => $this->handleSyncCompleted($event),
            SyncFailed::class => $this->handleSyncFailed($event),
            RecordSynced::class => $this->handleRecordSynced($event),
            ConflictDetected::class => $this->handleConflictDetected($event),
            default => null,
        };
    }

    protected function handleSyncStarted(SyncStarted $event): void
    {
        Log::channel('odoo')->info('Sync started', [
            'log_id' => $event->syncLog->id,
            'entity' => $event->syncLog->entityMapping?->name,
            'direction' => $event->syncLog->direction,
            'sync_type' => $event->syncLog->sync_type,
        ]);
    }

    protected function handleSyncCompleted(SyncCompleted $event): void
    {
        $log = $event->syncLog;

        Log::channel('odoo')->info('Sync completed', [
            'log_id' => $log->id,
            'entity' => $log->entityMapping?->name,
            'direction' => $log->direction,
            'processed' => $log->records_processed,
            'created' => $log->records_created,
            'updated' => $log->records_updated,
            'failed' => $log->records_failed,
            'conflicts' => $log->conflicts_detected,
            'duration_seconds' => $log->duration_seconds,
        ]);
    }

    protected function handleSyncFailed(SyncFailed $event): void
    {
        Log::channel('odoo')->error('Sync failed', [
            'log_id' => $event->syncLog->id,
            'entity' => $event->syncLog->entityMapping?->name,
            'error' => $event->exception->getMessage(),
            'trace' => $event->exception->getTraceAsString(),
        ]);
    }

    protected function handleRecordSynced(RecordSynced $event): void
    {
        if (config('odoo-integration.debug', false)) {
            Log::channel('odoo')->debug('Record synced', [
                'local_id' => $event->syncRecord->local_id,
                'odoo_id' => $event->syncRecord->odoo_id,
                'direction' => $event->direction,
                'action' => $event->action,
            ]);
        }
    }

    protected function handleConflictDetected(ConflictDetected $event): void
    {
        Log::channel('odoo')->warning('Conflict detected', [
            'conflict_id' => $event->conflict->id,
            'entity' => $event->conflict->entityMapping?->name,
            'type' => $event->conflict->conflict_type,
            'local_id' => $event->conflict->syncRecord?->local_id,
            'odoo_id' => $event->conflict->syncRecord?->odoo_id,
        ]);
    }
}
