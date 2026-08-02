<?php

namespace Modules\OdooIntegration\Services\Sync;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\OdooIntegration\Enums\SyncDirection;
use Modules\OdooIntegration\Enums\SyncStatus;
use Modules\OdooIntegration\Events\SyncCompleted;
use Modules\OdooIntegration\Events\SyncFailed;
use Modules\OdooIntegration\Events\SyncStarted;
use Modules\OdooIntegration\Exceptions\MissingDependencyException;
use Modules\OdooIntegration\Exceptions\OdooRateLimitException;
use Modules\OdooIntegration\Exceptions\OdooSyncException;
use Modules\OdooIntegration\Models\OdooConnection;
use Modules\OdooIntegration\Models\OdooEntityMapping;
use Modules\OdooIntegration\Models\OdooSyncLog;
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
        ?int $localId = null,
        ?int $odooId = null,
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
     *
     * Delta sync: Import new records only (skip existing in local DB)
     * Full sync: Override existing records with Odoo data
     */
    protected function runImport(OdooEntityMapping $mapping, OdooSyncLog $log, string $syncType): void
    {
        $connection = $mapping->connection;
        $client = $this->apiFactory->make($connection);

        // Build domain filters (from entity mapping configuration)
        $domain = $mapping->getOdooDomain();

        // Apply date filter if configured
        $dateFilterDomain = $mapping->getDateFilterDomain();
        if (! empty($dateFilterDomain)) {
            $domain = array_merge($domain, $dateFilterDomain);
        }

        // Delta sync is incremental: only records Odoo modified since the
        // last successful run are fetched (write_date watermark, derived
        // from Odoo's own clock so local clock skew is irrelevant). A short
        // overlap re-reads the boundary — imports are idempotent, so a few
        // duplicated records are cheaper than a missed tie.
        // Full sync always reads everything and overrides local rows.
        $watermark = null;
        if ($syncType === 'delta') {
            $watermark = $this->watermarkService->getWatermark($mapping, 'import');
            if ($watermark) {
                $domain[] = [
                    'write_date',
                    '>',
                    \Carbon\Carbon::parse($watermark)->subMinutes(5)->format('Y-m-d H:i:s'),
                ];
            }
        }
        $maxWriteDate = $watermark;

        // Get total count with rate limit retry
        $totalCount = $this->fetchWithRateLimitRetry(function () use ($client, $mapping, $domain) {
            return $client->searchCount($mapping->odoo_model, $domain);
        });

        if ($totalCount === 0) {
            return;
        }

        // Process in batches
        $batchSize = $mapping->batch_size;
        $offset = 0;

        while ($offset < $totalCount) {
            // Fetch batch with rate limit retry.
            // Stable sort: 'id asc' is a primary key in Odoo, so pagination with OFFSET is
            // deterministic — no records skipped or duplicated across batches.
            // (Sorting by 'write_date asc' alone lets ties shift between calls, which causes
            // some records to be silently omitted when bulk-created rows share a write_date.)
            $records = $this->fetchWithRateLimitRetry(function () use ($client, $mapping, $domain, $offset, $batchSize) {
                return $client->searchRead(
                    $mapping->odoo_model,
                    $domain,
                    $this->getOdooFields($mapping),
                    $offset,
                    $batchSize,
                    'id asc'
                );
            });

            foreach ($records as $record) {
                if (! empty($record['write_date'])
                    && ($maxWriteDate === null || $record['write_date'] > $maxWriteDate)) {
                    $maxWriteDate = $record['write_date'];
                }

                try {
                    $result = $this->importService->importRecord($mapping, $client, $record['id'], $record, $syncType);

                    $log->incrementProcessed();
                    if ($result['action'] === 'created') {
                        $log->incrementCreated();
                    } elseif ($result['action'] === 'updated') {
                        $log->incrementUpdated();
                    } elseif ($result['action'] === 'skipped') {
                        $log->incrementSkipped();
                    } elseif ($result['action'] === 'recreated') {
                        $log->incrementCreated();
                    }
                } catch (MissingDependencyException $e) {
                    // Skip records with missing dependencies (e.g., user_id, staff_profile_id not found locally)
                    $log->incrementSkipped();

                    Log::info('Record skipped due to missing dependency', [
                        'mapping_id' => $mapping->id,
                        'odoo_id' => $record['id'],
                        'dependency_field' => $e->getDependencyField(),
                        'dependency_odoo_id' => $e->getDependencyOdooId(),
                        'dependency_type' => $e->getDependencyType(),
                    ]);
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

            // Rate limit protection: pause between batches to avoid Odoo API throttling
            if ($offset < $totalCount) {
                sleep(2); // 2 second delay between batches
            }
        }

        // Advance the import watermark to the newest write_date seen. Only
        // reached after every batch processed — a crashed run never advances
        // it past unprocessed data.
        if ($maxWriteDate !== null) {
            $this->watermarkService->setWatermark($mapping, 'import', $maxWriteDate);
        }
    }

    /**
     * Execute a callback with rate limit retry logic.
     */
    protected function fetchWithRateLimitRetry(callable $callback, int $maxRetries = 3): mixed
    {
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                return $callback();
            } catch (OdooRateLimitException $e) {
                $attempt++;
                $retryAfter = $e->getRetryAfter();

                Log::info('Rate limit hit, waiting before retry', [
                    'attempt' => $attempt,
                    'retry_after' => $retryAfter,
                ]);

                if ($attempt >= $maxRetries) {
                    throw $e;
                }

                // Wait for the rate limit to reset (add a small buffer)
                sleep($retryAfter + 2);
            }
        }

        return null;
    }

    /**
     * Run export operation.
     */
    protected function runExport(OdooEntityMapping $mapping, OdooSyncLog $log, string $syncType): void
    {
        $connection = $mapping->connection;
        $client = $this->apiFactory->make($connection);

        // Run-start (minus a 1-minute overlap) becomes the next delta's
        // export watermark: records modified during this run — including
        // same-second ties the > comparison would drop — are re-examined
        // next time. Without a watermark, every delta re-exported the
        // ENTIRE table (one Odoo read per record — the main rate-limit hog).
        $startedAt = now()->subMinute()->format('Y-m-d H:i:s');

        // Get local records to export
        $query = $this->buildExportQuery($mapping, $syncType);
        $totalCount = $query->count();

        if ($totalCount === 0) {
            $this->watermarkService->setWatermark($mapping, 'export', $startedAt);

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

            // Rate limit protection: pause between batches to avoid Odoo API throttling
            usleep(500000); // 500ms delay between batches
        });

        // Only reached after every batch processed — a crashed run never
        // advances the watermark past unexported changes.
        $this->watermarkService->setWatermark($mapping, 'export', $startedAt);
    }

    /**
     * Build query for export operation.
     */
    protected function buildExportQuery(OdooEntityMapping $mapping, string $syncType)
    {
        $modelClass = $mapping->local_model;

        if (! class_exists($modelClass)) {
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
            // Skip field mappings without an Odoo field (default-only mappings)
            if ($fieldMapping->allowsImport() && ! empty($fieldMapping->odoo_field)) {
                $fields[] = $fieldMapping->odoo_field;
            }
        }

        // Re-index array to ensure sequential keys (0,1,2...) for XML-RPC encoding
        return array_values(array_unique($fields));
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
