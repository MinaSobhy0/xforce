<?php

namespace Modules\OdooIntegration\Services\Sync;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\OdooIntegration\Models\OdooEntityMapping;
use Modules\OdooIntegration\Models\OdooSyncRecord;
use Modules\OdooIntegration\Models\OdooSyncConflict;
use Modules\OdooIntegration\Enums\ConflictResolution;
use Modules\OdooIntegration\Events\RecordSynced;
use Modules\OdooIntegration\Events\ConflictDetected;
use Modules\OdooIntegration\Exceptions\OdooSyncException;
use Modules\OdooIntegration\Services\Api\OdooApiClientInterface;
use Modules\OdooIntegration\Services\Transform\FieldTransformer;

class ImportService
{
    public function __construct(
        protected FieldTransformer $transformer,
        protected ConflictResolver $conflictResolver,
    ) {}

    /**
     * Import a single record from Odoo.
     */
    public function importRecord(
        OdooEntityMapping $mapping,
        OdooApiClientInterface $client,
        int $odooId,
        ?array $odooData = null
    ): array {
        // Fetch data if not provided
        if ($odooData === null) {
            $records = $client->read($mapping->odoo_model, [$odooId]);
            if (empty($records)) {
                throw OdooSyncException::importFailed($mapping->odoo_model, $odooId, 'Record not found in Odoo');
            }
            $odooData = $records[0];
        }

        // Check for existing sync record
        $syncRecord = OdooSyncRecord::findByOdooId($mapping->id, $odooId);

        // Transform Odoo data to local format
        $localData = $this->transformer->transformImport($mapping, $odooData);

        // Calculate checksums
        $odooChecksum = $this->calculateChecksum($odooData);

        if ($syncRecord) {
            return $this->updateExistingRecord($mapping, $syncRecord, $localData, $odooData, $odooChecksum);
        }

        return $this->createNewRecord($mapping, $odooId, $localData, $odooData, $odooChecksum);
    }

    /**
     * Create a new local record from Odoo data.
     */
    protected function createNewRecord(
        OdooEntityMapping $mapping,
        int $odooId,
        array $localData,
        array $odooData,
        string $odooChecksum
    ): array {
        $modelClass = $mapping->local_model;

        if (!class_exists($modelClass)) {
            throw OdooSyncException::mappingNotFound($modelClass);
        }

        return DB::transaction(function () use ($mapping, $modelClass, $odooId, $localData, $odooData, $odooChecksum) {
            // Check if record exists by key fields
            $existingRecord = $this->findByKeyFields($mapping, $localData);

            if ($existingRecord) {
                // Link existing record to Odoo
                $existingRecord->update(array_merge($localData, [
                    'odoo_id' => $odooId,
                    'odoo_synced_at' => now(),
                ]));

                $syncRecord = OdooSyncRecord::create([
                    'tenant_id' => $mapping->tenant_id,
                    'entity_mapping_id' => $mapping->id,
                    'local_model' => $mapping->local_model,
                    'local_id' => $existingRecord->id,
                    'odoo_id' => $odooId,
                    'sync_status' => OdooSyncRecord::STATUS_SYNCED,
                    'last_sync_direction' => 'import',
                    'last_synced_at' => now(),
                    'local_checksum' => $this->calculateChecksum($existingRecord->toArray()),
                    'odoo_checksum' => $odooChecksum,
                ]);

                event(new RecordSynced($syncRecord, 'import', 'updated'));

                return [
                    'action' => 'updated',
                    'local_id' => $existingRecord->id,
                    'odoo_id' => $odooId,
                ];
            }

            // Create new record
            $localRecord = new $modelClass();
            $localRecord->fill(array_merge($localData, [
                'tenant_id' => $mapping->tenant_id,
                'odoo_id' => $odooId,
                'odoo_synced_at' => now(),
            ]));
            $localRecord->save();

            $syncRecord = OdooSyncRecord::create([
                'tenant_id' => $mapping->tenant_id,
                'entity_mapping_id' => $mapping->id,
                'local_model' => $mapping->local_model,
                'local_id' => $localRecord->id,
                'odoo_id' => $odooId,
                'sync_status' => OdooSyncRecord::STATUS_SYNCED,
                'last_sync_direction' => 'import',
                'last_synced_at' => now(),
                'local_checksum' => $this->calculateChecksum($localRecord->toArray()),
                'odoo_checksum' => $odooChecksum,
            ]);

            event(new RecordSynced($syncRecord, 'import', 'created'));

            return [
                'action' => 'created',
                'local_id' => $localRecord->id,
                'odoo_id' => $odooId,
            ];
        });
    }

    /**
     * Update an existing local record from Odoo data.
     */
    protected function updateExistingRecord(
        OdooEntityMapping $mapping,
        OdooSyncRecord $syncRecord,
        array $localData,
        array $odooData,
        string $odooChecksum
    ): array {
        $localRecord = $syncRecord->getLocalRecord();

        if (!$localRecord) {
            // Local record was deleted, handle based on conflict resolution
            return $this->handleDeletedLocally($mapping, $syncRecord, $localData, $odooData, $odooChecksum);
        }

        $localChecksum = $this->calculateChecksum($localRecord->toArray());

        // Check for conflicts (both sides modified since last sync)
        if ($this->hasConflict($syncRecord, $localChecksum, $odooChecksum)) {
            return $this->handleConflict($mapping, $syncRecord, $localRecord, $localData, $odooData);
        }

        // No conflict - update local record
        return DB::transaction(function () use ($localRecord, $syncRecord, $localData, $odooChecksum) {
            $localRecord->update(array_merge($localData, [
                'odoo_synced_at' => now(),
            ]));

            $newLocalChecksum = $this->calculateChecksum($localRecord->fresh()->toArray());

            $syncRecord->markSynced('import', $newLocalChecksum, $odooChecksum);

            event(new RecordSynced($syncRecord, 'import', 'updated'));

            return [
                'action' => 'updated',
                'local_id' => $localRecord->id,
                'odoo_id' => $syncRecord->odoo_id,
            ];
        });
    }

    /**
     * Check if there's a conflict between local and Odoo data.
     */
    protected function hasConflict(OdooSyncRecord $syncRecord, string $localChecksum, string $odooChecksum): bool
    {
        // If checksums haven't changed, no conflict
        if ($localChecksum === $syncRecord->local_checksum && $odooChecksum === $syncRecord->odoo_checksum) {
            return false;
        }

        // If only one side changed, no conflict
        if ($localChecksum === $syncRecord->local_checksum || $odooChecksum === $syncRecord->odoo_checksum) {
            return false;
        }

        // Both sides changed since last sync - conflict!
        return true;
    }

    /**
     * Handle a conflict between local and Odoo data.
     */
    protected function handleConflict(
        OdooEntityMapping $mapping,
        OdooSyncRecord $syncRecord,
        $localRecord,
        array $transformedOdooData,
        array $rawOdooData
    ): array {
        $resolution = $mapping->conflict_resolution;

        return match ($resolution) {
            ConflictResolution::ODOO_WINS => $this->resolveOdooWins($syncRecord, $localRecord, $transformedOdooData, $rawOdooData),
            ConflictResolution::LOCAL_WINS => $this->resolveLocalWins($syncRecord),
            ConflictResolution::NEWEST_WINS => $this->resolveNewestWins($syncRecord, $localRecord, $transformedOdooData, $rawOdooData),
            ConflictResolution::MANUAL => $this->createConflictRecord($mapping, $syncRecord, $localRecord, $rawOdooData),
            default => $this->createConflictRecord($mapping, $syncRecord, $localRecord, $rawOdooData),
        };
    }

    /**
     * Resolve conflict by keeping Odoo data.
     */
    protected function resolveOdooWins(OdooSyncRecord $syncRecord, $localRecord, array $localData, array $odooData): array
    {
        return DB::transaction(function () use ($syncRecord, $localRecord, $localData, $odooData) {
            $localRecord->update(array_merge($localData, [
                'odoo_synced_at' => now(),
            ]));

            $newChecksum = $this->calculateChecksum($localRecord->fresh()->toArray());
            $syncRecord->markSynced('import', $newChecksum, $this->calculateChecksum($odooData));

            return [
                'action' => 'updated',
                'local_id' => $localRecord->id,
                'odoo_id' => $syncRecord->odoo_id,
                'resolution' => 'odoo_wins',
            ];
        });
    }

    /**
     * Resolve conflict by keeping local data.
     */
    protected function resolveLocalWins(OdooSyncRecord $syncRecord): array
    {
        // Mark as synced without updating - export will push local changes
        $syncRecord->update([
            'sync_status' => OdooSyncRecord::STATUS_PENDING,
            'last_sync_direction' => 'import',
        ]);

        return [
            'action' => 'skipped',
            'local_id' => $syncRecord->local_id,
            'odoo_id' => $syncRecord->odoo_id,
            'resolution' => 'local_wins',
        ];
    }

    /**
     * Resolve conflict by keeping the newest modification.
     */
    protected function resolveNewestWins(OdooSyncRecord $syncRecord, $localRecord, array $localData, array $odooData): array
    {
        $localUpdatedAt = $localRecord->updated_at;
        $odooUpdatedAt = isset($odooData['write_date']) ? new \DateTime($odooData['write_date']) : null;

        if ($odooUpdatedAt && $odooUpdatedAt > $localUpdatedAt) {
            return $this->resolveOdooWins($syncRecord, $localRecord, $localData, $odooData);
        }

        return $this->resolveLocalWins($syncRecord);
    }

    /**
     * Create a conflict record for manual resolution.
     */
    protected function createConflictRecord(
        OdooEntityMapping $mapping,
        OdooSyncRecord $syncRecord,
        $localRecord,
        array $odooData
    ): array {
        $syncRecord->markConflict();

        $conflict = OdooSyncConflict::createFromRecords(
            $syncRecord,
            $localRecord->toArray(),
            $odooData,
            OdooSyncConflict::TYPE_BOTH_MODIFIED
        );

        event(new ConflictDetected($conflict));

        return [
            'action' => 'conflict',
            'local_id' => $syncRecord->local_id,
            'odoo_id' => $syncRecord->odoo_id,
            'conflict_id' => $conflict->id,
        ];
    }

    /**
     * Handle case where local record was deleted.
     */
    protected function handleDeletedLocally(
        OdooEntityMapping $mapping,
        OdooSyncRecord $syncRecord,
        array $localData,
        array $odooData,
        string $odooChecksum
    ): array {
        $resolution = $mapping->conflict_resolution;

        if ($resolution === ConflictResolution::MANUAL) {
            $syncRecord->markConflict();

            $conflict = OdooSyncConflict::create([
                'tenant_id' => $mapping->tenant_id,
                'sync_record_id' => $syncRecord->id,
                'entity_mapping_id' => $mapping->id,
                'status' => 'pending',
                'conflict_type' => OdooSyncConflict::TYPE_DELETED_LOCALLY,
                'local_data' => null,
                'odoo_data' => $odooData,
                'diff' => null,
            ]);

            event(new ConflictDetected($conflict));

            return [
                'action' => 'conflict',
                'odoo_id' => $syncRecord->odoo_id,
                'conflict_id' => $conflict->id,
            ];
        }

        // Recreate the record
        $modelClass = $mapping->local_model;
        $localRecord = new $modelClass();
        $localRecord->fill(array_merge($localData, [
            'tenant_id' => $mapping->tenant_id,
            'odoo_id' => $syncRecord->odoo_id,
            'odoo_synced_at' => now(),
        ]));
        $localRecord->save();

        $syncRecord->update([
            'local_id' => $localRecord->id,
            'sync_status' => OdooSyncRecord::STATUS_SYNCED,
            'local_checksum' => $this->calculateChecksum($localRecord->toArray()),
            'odoo_checksum' => $odooChecksum,
            'last_synced_at' => now(),
        ]);

        return [
            'action' => 'recreated',
            'local_id' => $localRecord->id,
            'odoo_id' => $syncRecord->odoo_id,
        ];
    }

    /**
     * Find existing record by key fields.
     */
    protected function findByKeyFields(OdooEntityMapping $mapping, array $data)
    {
        $keyFields = $mapping->getKeyFields();

        if ($keyFields->isEmpty()) {
            return null;
        }

        $modelClass = $mapping->local_model;
        $query = $modelClass::query();

        foreach ($keyFields as $field) {
            $localField = $field->local_field;
            if (isset($data[$localField])) {
                $query->where($localField, $data[$localField]);
            }
        }

        return $query->first();
    }

    /**
     * Calculate a checksum for conflict detection.
     */
    protected function calculateChecksum(array $data): string
    {
        // Remove timestamps and system fields
        unset($data['created_at'], $data['updated_at'], $data['odoo_synced_at']);
        ksort($data);
        return md5(json_encode($data));
    }
}
