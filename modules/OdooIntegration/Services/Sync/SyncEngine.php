<?php

namespace Modules\OdooIntegration\Services\Sync;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\OdooIntegration\Models\OdooConnection;
use Modules\OdooIntegration\Models\OdooEntityMapping;
use Modules\OdooIntegration\Models\OdooSyncLog;
use Modules\OdooIntegration\Enums\SyncDirection;
use Modules\OdooIntegration\Enums\SyncStatus;
use Modules\OdooIntegration\Events\SyncStarted;
use Modules\OdooIntegration\Events\SyncCompleted;
use Modules\OdooIntegration\Events\SyncFailed;
use Modules\OdooIntegration\Exceptions\OdooSyncException;
use Modules\OdooIntegration\Services\Api\OdooApiFactory;

class SyncEngine
{
    public function __construct(
        protected OdooApiFactory $apiFactory,
        protected ImportService $importService,
        protected ExportService $exportService,
        protected WatermarkService $watermarkService,
    ) {}

    /**
     * Run a full sync for all active entity mappings of a connection.
     */
    public function syncAll(OdooConnection $connection, ?int $triggeredBy = null): array
    {
        $results = [];
        $mappings = $connection->entityMappings()
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();

        foreach ($mappings as $mapping) {
            try {
                $results[$mapping->id] = $this->syncEntity($mapping, 'full', $triggeredBy);
            } catch (\Exception $e) {
                Log::error('Sync failed for mapping', [
                    'mapping_id' => $mapping->id,
                    'error' => $e->getMessage(),
                ]);
                $results[$mapping->id] = ['error' => $e->getMessage()];
            }
        }

        $connection->markSynced();

        return $results;
    }

    /**
     * Sync a specific entity mapping.
     */
    public function syncEntity(
        OdooEntityMapping $mapping,
        string $syncType = 'delta',
        ?int $triggeredBy = null
    ): OdooSyncLog {
        $connection = $mapping->connection;

        // Create sync log
        $log = OdooSyncLog::create([
            'tenant_id' => $mapping->tenant_id,
            'connection_id' => $connection->id,
            'entity_mapping_id' => $mapping->id,
            'sync_type' => $syncType,
            'direction' => $this->determineDirection($mapping),
            'status' => SyncStatus::PENDING,
            'triggered_by' => $triggeredBy,
        ]);

        event(new SyncStarted($log));

        try {
            $log->start();

            $client = $this->apiFactory->make($connection);
            $client->authenticate();

            // Determine sync direction
            $direction = $mapping->sync_direction;

            if ($direction->allowsImport()) {
                $this->runImport($mapping, $log, $syncType);
            }

            if ($direction->allowsExport()) {
                $this->runExport($mapping, $log, $syncType);
            }

            $log->complete();
            event(new SyncCompleted($log));

        } catch (\Exception $e) {
            $log->fail([['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]]);
            event(new SyncFailed($log, $e));

            throw $e;
        }

        return $log->fresh();
    }

    /**
     * Sync a single record.
     */
    public function syncRecord(
        OdooEntityMapping $mapping,
        int $localId = null,
        int $odooId = null,
        string $direction = 'import'
    ): array {
        $connection = $mapping->connection;
        $client = $this->apiFactory->make($connection);
        $client->authenticate();

        if ($direction === 'import' && $odooId) {
            return $this->importService->importRecord($mapping, $client, $odooId);
        }

        if ($direction === 'export' && $localId) {
            return $this->exportService->exportRecord($mapping, $client, $localId);
        }

        throw new OdooSyncException('Invalid sync parameters');
    }

    /**
     * Run import operation.
     */
    protected function runImport(OdooEntityMapping $mapping, OdooSyncLog $log, string $syncType): void
    {
        $connection = $mapping->connection;
        $client = $this->apiFactory->make($connection);

        // Get watermark for delta sync
        $watermark = null;
        if ($syncType === 'delta') {
            $watermark = $this->watermarkService->getWatermark($mapping, 'import');
        }

        // Build domain filters
        $domain = $mapping->getOdooDomain();
        if ($watermark) {
            $domain[] = ['write_date', '>', $watermark];
        }

        // Get total count
        $totalCount = $client->searchCount($mapping->odoo_model, $domain);

        if ($totalCount === 0) {
            return;
        }

        // Process in batches
        $batchSize = $mapping->batch_size;
        $offset = 0;

        while ($offset < $totalCount) {
            $records = $client->searchRead(
                $mapping->odoo_model,
                $domain,
                $this->getOdooFields($mapping),
                $offset,
                $batchSize,
                'write_date asc'
            );

            foreach ($records as $record) {
                try {
                    $result = $this->importService->importRecord($mapping, $client, $record['id'], $record);

                    $log->incrementProcessed();
                    if ($result['action'] === 'created') {
                        $log->incrementCreated();
                    } elseif ($result['action'] === 'updated') {
                        $log->incrementUpdated();
                    } elseif ($result['action'] === 'conflict') {
                        $log->incrementConflicts();
                    }

                    // Update watermark after each successful record
                    if (isset($record['write_date'])) {
                        $this->watermarkService->setWatermark($mapping, 'import', $record['write_date']);
                    }
                } catch (\Exception $e) {
                    $log->incrementFailed();
                    $log->addError("Failed to import record {$record['id']}: {$e->getMessage()}");

                    Log::warning('Import record failed', [
                        'mapping_id' => $mapping->id,
                        'odoo_id' => $record['id'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $offset += $batchSize;
        }
    }

    /**
     * Run export operation.
     */
    protected function runExport(OdooEntityMapping $mapping, OdooSyncLog $log, string $syncType): void
    {
        $connection = $mapping->connection;
        $client = $this->apiFactory->make($connection);

        // Get local records to export
        $query = $this->buildExportQuery($mapping, $syncType);
        $totalCount = $query->count();

        if ($totalCount === 0) {
            return;
        }

        // Process in batches
        $batchSize = $mapping->batch_size;

        $query->chunk($batchSize, function ($records) use ($mapping, $client, $log) {
            foreach ($records as $record) {
                try {
                    $result = $this->exportService->exportRecord($mapping, $client, $record->id, $record);

                    $log->incrementProcessed();
                    if ($result['action'] === 'created') {
                        $log->incrementCreated();
                    } elseif ($result['action'] === 'updated') {
                        $log->incrementUpdated();
                    } elseif ($result['action'] === 'conflict') {
                        $log->incrementConflicts();
                    }
                } catch (\Exception $e) {
                    $log->incrementFailed();
                    $log->addError("Failed to export record {$record->id}: {$e->getMessage()}");

                    Log::warning('Export record failed', [
                        'mapping_id' => $mapping->id,
                        'local_id' => $record->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    /**
     * Build query for export operation.
     */
    protected function buildExportQuery(OdooEntityMapping $mapping, string $syncType)
    {
        $modelClass = $mapping->local_model;

        if (!class_exists($modelClass)) {
            throw OdooSyncException::mappingNotFound($modelClass);
        }

        $query = $modelClass::query();

        if ($syncType === 'delta') {
            $watermark = $this->watermarkService->getWatermark($mapping, 'export');
            if ($watermark) {
                $query->where('updated_at', '>', $watermark);
            }
        }

        return $query;
    }

    /**
     * Get Odoo field names for a mapping.
     */
    protected function getOdooFields(OdooEntityMapping $mapping): array
    {
        $fields = ['id', 'write_date', 'create_date'];

        foreach ($mapping->getActiveFieldMappings() as $fieldMapping) {
            if ($fieldMapping->allowsImport()) {
                $fields[] = $fieldMapping->odoo_field;
            }
        }

        return array_unique($fields);
    }

    /**
     * Determine primary sync direction for logging.
     */
    protected function determineDirection(OdooEntityMapping $mapping): string
    {
        return match ($mapping->sync_direction) {
            SyncDirection::IMPORT => 'import',
            SyncDirection::EXPORT => 'export',
            SyncDirection::BIDIRECTIONAL => 'bidirectional',
            default => 'import',
        };
    }
}
