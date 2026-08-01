<?php

namespace Modules\OdooIntegration\Services\Sync;

use Illuminate\Support\Facades\DB;
use Modules\OdooIntegration\Exceptions\OdooSyncException;
use Modules\OdooIntegration\Models\OdooEntityMapping;
use Modules\OdooIntegration\Models\OdooSyncRecord;
use Modules\OdooIntegration\Services\Api\OdooApiClientInterface;
use Modules\OdooIntegration\Services\RealtimeSyncManager;
use Modules\OdooIntegration\Services\Transform\FieldTransformer;

class ImportService
{
    public function __construct(
        protected FieldTransformer $transformer,
    ) {}

    /**
     * Import a single record from Odoo.
     *
     * @param  string  $syncType  'full' = override existing; 'delta' = create new
     *                            records and apply Odoo-side changes to records
     *                            without local modifications (local edits win)
     */
    public function importRecord(
        OdooEntityMapping $mapping,
        OdooApiClientInterface $client,
        int $odooId,
        ?array $odooData = null,
        string $syncType = 'delta'
    ): array {
        $modelClass = $mapping->local_model;

        if (! class_exists($modelClass)) {
            throw OdooSyncException::mappingNotFound($modelClass);
        }

        // Fetch data if not provided
        if ($odooData === null) {
            $records = $client->read($mapping->odoo_model, [$odooId]);
            if (empty($records)) {
                throw OdooSyncException::importFailed($mapping->odoo_model, $odooId, 'Record not found in Odoo');
            }
            $odooData = $records[0];
        }

        // Check if local record exists by odoo_id
        $existingRecord = $modelClass::where('odoo_id', $odooId)->first();

        // Delta sync with an existing record: the write_date watermark means
        // Odoo changed this record since we last saw it — apply the update
        // UNLESS the local row carries changes newer than its last sync
        // (updated_at > odoo_synced_at). Local edits win until the export
        // path pushes them and re-stamps odoo_synced_at.
        if ($syncType === 'delta' && $existingRecord && $this->hasLocalChanges($mapping, $existingRecord)) {
            return [
                'action' => 'skipped',
                'reason' => 'local_changes',
                'local_id' => $existingRecord->id,
                'odoo_id' => $odooId,
            ];
        }

        // Transform Odoo data to local format
        $localData = $this->transformer->transformImport($mapping, $odooData);

        // Model-level post-transform hook (e.g. unit conversion that needs related type context).
        if (method_exists($modelClass, 'applyOdooImport')) {
            $localData = $modelClass::applyOdooImport($localData, $mapping, $odooData);
        }

        // Existing record (full sync, or delta with no local divergence): override
        if ($existingRecord) {
            return $this->updateExistingRecord($existingRecord, $localData, $odooId, $mapping, $odooData);
        }

        // New record: create
        return $this->createNewRecord($mapping, $odooId, $localData);
    }

    /**
     * Create a new local record from Odoo data.
     */
    protected function createNewRecord(
        OdooEntityMapping $mapping,
        int $odooId,
        array $localData
    ): array {
        $modelClass = $mapping->local_model;

        return DB::transaction(function () use ($mapping, $modelClass, $odooId, $localData) {
            // Suppress realtime sync — these writes are Odoo → local, not user edits.
            return RealtimeSyncManager::suppress(function () use ($mapping, $modelClass, $odooId, $localData) {
                // Check if record exists by key fields
                $existingRecord = $this->findByKeyFields($mapping, $localData);

                if ($existingRecord) {
                    // Link existing record to Odoo
                    $existingRecord->update(array_merge($localData, [
                        'odoo_id' => $odooId,
                        'odoo_synced_at' => now(),
                    ]));

                    return [
                        'action' => 'linked',
                        'local_id' => $existingRecord->id,
                        'odoo_id' => $odooId,
                    ];
                }

                // Create new record
                $localRecord = new $modelClass;
                $localRecord->fill(array_merge($localData, [
                    'tenant_id' => $mapping->tenant_id,
                    'odoo_id' => $odooId,
                    'odoo_synced_at' => now(),
                ]));
                $localRecord->save();

                return [
                    'action' => 'created',
                    'local_id' => $localRecord->id,
                    'odoo_id' => $odooId,
                ];
            });
        });
    }

    /**
     * Whether the local record changed since its last sync round-trip.
     *
     * Preferred signal: the sync record's stored local checksum vs the
     * current one — immune to same-second timestamp ties. Fallback (no sync
     * record yet, e.g. import-only mappings): updated_at newer than
     * odoo_synced_at.
     */
    protected function hasLocalChanges(OdooEntityMapping $mapping, $localRecord): bool
    {
        $syncRecord = OdooSyncRecord::where('entity_mapping_id', $mapping->id)
            ->where('local_id', $localRecord->id)
            ->first();

        if ($syncRecord && ! empty($syncRecord->local_checksum)) {
            return SyncChecksum::calculate($localRecord->toArray()) !== $syncRecord->local_checksum;
        }

        return $localRecord->odoo_synced_at !== null
            && $localRecord->updated_at !== null
            && $localRecord->updated_at->gt($localRecord->odoo_synced_at);
    }

    /**
     * Update existing local record with Odoo data.
     */
    protected function updateExistingRecord(
        $localRecord,
        array $localData,
        int $odooId,
        ?OdooEntityMapping $mapping = null,
        ?array $odooData = null
    ): array {
        RealtimeSyncManager::suppress(fn () => $localRecord->update(array_merge($localData, [
            'odoo_synced_at' => now(),
        ])));

        // Keep the sync record's checksums current, otherwise the next delta
        // would read this very update as a local modification and stop
        // applying Odoo changes.
        if ($mapping) {
            $update = [
                'local_checksum' => SyncChecksum::calculate($localRecord->fresh()->toArray()),
                'last_synced_at' => now(),
                'sync_status' => OdooSyncRecord::STATUS_SYNCED,
                'last_sync_direction' => 'import',
            ];
            if ($odooData !== null) {
                $update['odoo_checksum'] = SyncChecksum::calculate($odooData);
            }

            OdooSyncRecord::where('entity_mapping_id', $mapping->id)
                ->where('local_id', $localRecord->id)
                ->update($update);
        }

        return [
            'action' => 'updated',
            'local_id' => $localRecord->id,
            'odoo_id' => $odooId,
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
        $constrained = false;

        foreach ($keyFields as $field) {
            $localField = $field->local_field;

            // odoo_id was already matched upstream (importRecord's lookup).
            // Records reaching this point have no local row with this odoo_id,
            // so AND-ing it here would make every business key (e.g. email)
            // unmatchable and produce duplicates / unique violations instead
            // of linking the existing local record.
            if ($localField === 'odoo_id') {
                continue;
            }

            if (isset($data[$localField])) {
                $query->where($localField, $data[$localField]);
                $constrained = true;
            }
        }

        if (! $constrained) {
            return null;
        }

        return $query->first();
    }
}
