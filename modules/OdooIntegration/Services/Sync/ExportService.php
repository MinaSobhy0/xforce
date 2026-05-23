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

class ExportService
{
    public function __construct(
        protected FieldTransformer $transformer,
        protected ConflictResolver $conflictResolver,
    ) {}

    /**
     * Export a single record to Odoo.
     */
    public function exportRecord(
        OdooEntityMapping $mapping,
        OdooApiClientInterface $client,
        int $localId,
        $localRecord = null
    ): array {
        $modelClass = $mapping->local_model;

        if (!class_exists($modelClass)) {
            throw OdooSyncException::mappingNotFound($modelClass);
        }

        // Fetch record if not provided
        if ($localRecord === null) {
            $localRecord = $modelClass::find($localId);
            if (!$localRecord) {
                throw OdooSyncException::exportFailed($mapping->local_model, $localId, 'Record not found locally');
            }
        }

        // Check for existing sync record
        $syncRecord = OdooSyncRecord::where('entity_mapping_id', $mapping->id)
            ->where('local_id', $localId)
            ->first();

        // If the local row was imported from Odoo (model has odoo_id) but no
        // OdooSyncRecord was ever created for it, backfill one on the fly so
        // the export-side update path resolves to write() instead of falling
        // through to create() and producing duplicates / "missing field"
        // errors against records that already exist in Odoo.
        //
        // Also seed both checksums to the *current* values: an empty
        // local_checksum vs the just-computed one would always look like
        // "local changed" to hasConflict(), and with conflict_resolution=
        // manual the export would silently file a conflict instead of
        // pushing. By baselining to current state, this single export sees
        // "no-conflict, just push" — Odoo gets write() + workflow actions
        // (applyOdooExport may also queue an action_approve etc.).
        if (! $syncRecord && ! empty($localRecord->odoo_id)) {
            try {
                $currentOdoo = $client->read($mapping->odoo_model, [(int) $localRecord->odoo_id]);
                $odooChecksumNow = $this->calculateChecksum($currentOdoo[0] ?? []);
            } catch (\Throwable) {
                $odooChecksumNow = '';
            }

            $localChecksumNow = $this->calculateChecksum($localRecord->toArray());

            $syncRecord = OdooSyncRecord::create([
                'tenant_id' => $mapping->tenant_id,
                'entity_mapping_id' => $mapping->id,
                'local_model' => $mapping->local_model,
                'local_id' => $localId,
                'odoo_id' => (int) $localRecord->odoo_id,
                'sync_status' => OdooSyncRecord::STATUS_SYNCED,
                'last_sync_direction' => 'import',
                'last_synced_at' => $localRecord->odoo_synced_at ?? now(),
                'local_checksum' => $localChecksumNow,
                'odoo_checksum' => $odooChecksumNow,
            ]);
        }

        // Transform local data to Odoo format
        $odooData = $this->transformer->transformExport($mapping, $localRecord->toArray());

        // Model-level post-transform hook (mirror of applyOdooImport on the
        // import path). Lets a model inject Odoo-specific fields the local
        // schema doesn't track — e.g. custom required fields added by an
        // Odoo module — or post-process the payload before send.
        if (method_exists($modelClass, 'applyOdooExport')) {
            $odooData = $modelClass::applyOdooExport($odooData, $mapping, $localRecord);
        }

        // Calculate checksum
        $localChecksum = $this->calculateChecksum($localRecord->toArray());

        if ($syncRecord && $syncRecord->odoo_id) {
            return $this->updateExistingOdooRecord($mapping, $client, $syncRecord, $localRecord, $odooData, $localChecksum);
        }

        return $this->createNewOdooRecord($mapping, $client, $localRecord, $odooData, $localChecksum);
    }

    /**
     * Create a new record in Odoo.
     */
    protected function createNewOdooRecord(
        OdooEntityMapping $mapping,
        OdooApiClientInterface $client,
        $localRecord,
        array $odooData,
        string $localChecksum
    ): array {
        return DB::transaction(function () use ($mapping, $client, $localRecord, $odooData, $localChecksum) {
            // Create in Odoo
            $odooId = $client->create($mapping->odoo_model, $odooData);

            // Read back for checksum
            $odooRecord = $client->read($mapping->odoo_model, [$odooId]);
            $odooChecksum = $this->calculateChecksum($odooRecord[0] ?? []);

            // Update local record with odoo_id
            $localRecord->update([
                'odoo_id' => $odooId,
                'odoo_synced_at' => now(),
            ]);

            // Create or update sync record
            $syncRecord = OdooSyncRecord::updateOrCreate(
                [
                    'entity_mapping_id' => $mapping->id,
                    'local_id' => $localRecord->id,
                ],
                [
                    'tenant_id' => $mapping->tenant_id,
                    'local_model' => $mapping->local_model,
                    'odoo_id' => $odooId,
                    'sync_status' => OdooSyncRecord::STATUS_SYNCED,
                    'last_sync_direction' => 'export',
                    'last_synced_at' => now(),
                    'local_checksum' => $localChecksum,
                    'odoo_checksum' => $odooChecksum,
                ]
            );

            event(new RecordSynced($syncRecord, 'export', 'created'));

            return [
                'action' => 'created',
                'local_id' => $localRecord->id,
                'odoo_id' => $odooId,
            ];
        });
    }

    /**
     * Update an existing record in Odoo.
     */
    protected function updateExistingOdooRecord(
        OdooEntityMapping $mapping,
        OdooApiClientInterface $client,
        OdooSyncRecord $syncRecord,
        $localRecord,
        array $odooData,
        string $localChecksum
    ): array {
        // Check current Odoo state for conflict detection
        $currentOdooRecords = $client->read($mapping->odoo_model, [$syncRecord->odoo_id]);

        if (empty($currentOdooRecords)) {
            // Odoo record was deleted
            return $this->handleDeletedInOdoo($mapping, $client, $syncRecord, $localRecord, $odooData, $localChecksum);
        }

        $currentOdooData = $currentOdooRecords[0];
        $currentOdooChecksum = $this->calculateChecksum($currentOdooData);

        // Check for conflicts
        if ($this->hasConflict($syncRecord, $localChecksum, $currentOdooChecksum)) {
            return $this->handleConflict($mapping, $client, $syncRecord, $localRecord, $odooData, $currentOdooData);
        }

        // No conflict - update Odoo
        return DB::transaction(function () use ($mapping, $client, $syncRecord, $localRecord, $odooData, $localChecksum) {
            // Extract workflow actions emitted by applyOdooExport. State transitions
            // on records like hr.leave can't be done via write() — they go through
            // Odoo workflow methods (action_refuse, action_approve, action_validate).
            $actions = $odooData['__odoo_actions'] ?? [];
            unset($odooData['__odoo_actions']);

            if (! empty($odooData)) {
                $client->write($mapping->odoo_model, [$syncRecord->odoo_id], $odooData);
            }

            foreach ($actions as $method) {
                try {
                    $client->execute($mapping->odoo_model, $method, [[$syncRecord->odoo_id]]);
                } catch (\Throwable $e) {
                    // Many Odoo action_* methods return None and Odoo's own
                    // RPC controller marshals the response with allow_none=
                    // False, so a successful call comes back to us as a
                    // marshal TypeError. The action itself has already run
                    // server-side; treat this specific fault as success.
                    if (! str_contains($e->getMessage(), 'cannot marshal None')) {
                        throw $e;
                    }
                }
            }

            // Read back for checksum
            $odooRecord = $client->read($mapping->odoo_model, [$syncRecord->odoo_id]);
            $odooChecksum = $this->calculateChecksum($odooRecord[0] ?? []);

            $localRecord->update(['odoo_synced_at' => now()]);
            $syncRecord->markSynced('export', $localChecksum, $odooChecksum);

            event(new RecordSynced($syncRecord, 'export', 'updated'));

            return [
                'action' => 'updated',
                'local_id' => $localRecord->id,
                'odoo_id' => $syncRecord->odoo_id,
            ];
        });
    }

    /**
     * Check if there's a conflict.
     *
     * A null/empty stored baseline (either side) means we never recorded a
     * snapshot for that direction yet — common for backfilled sync records.
     * Without a baseline we can't decide "changed since last sync", so we
     * accept the push instead of refusing it as a "manual" conflict that
     * never gets resolved.
     */
    protected function hasConflict(OdooSyncRecord $syncRecord, string $localChecksum, string $odooChecksum): bool
    {
        // No baseline → can't detect conflict → not a conflict.
        if (empty($syncRecord->local_checksum) || empty($syncRecord->odoo_checksum)) {
            return false;
        }

        // If checksums match stored values, no conflict
        if ($localChecksum === $syncRecord->local_checksum && $odooChecksum === $syncRecord->odoo_checksum) {
            return false;
        }

        // If only local changed, no conflict
        if ($odooChecksum === $syncRecord->odoo_checksum) {
            return false;
        }

        // Odoo also changed - potential conflict
        // But if local hasn't changed, just accept Odoo changes
        if ($localChecksum === $syncRecord->local_checksum) {
            return false;
        }

        // Both changed - conflict!
        return true;
    }

    /**
     * Handle a conflict during export.
     */
    protected function handleConflict(
        OdooEntityMapping $mapping,
        OdooApiClientInterface $client,
        OdooSyncRecord $syncRecord,
        $localRecord,
        array $odooData,
        array $currentOdooData
    ): array {
        $resolution = $mapping->conflict_resolution;

        return match ($resolution) {
            ConflictResolution::LOCAL_WINS => $this->resolveLocalWins($mapping, $client, $syncRecord, $localRecord, $odooData),
            ConflictResolution::ODOO_WINS => $this->resolveOdooWins($syncRecord),
            ConflictResolution::NEWEST_WINS => $this->resolveNewestWins($mapping, $client, $syncRecord, $localRecord, $odooData, $currentOdooData),
            ConflictResolution::MANUAL => $this->createConflictRecord($mapping, $syncRecord, $localRecord, $currentOdooData),
            default => $this->createConflictRecord($mapping, $syncRecord, $localRecord, $currentOdooData),
        };
    }

    /**
     * Resolve by pushing local changes.
     */
    protected function resolveLocalWins(
        OdooEntityMapping $mapping,
        OdooApiClientInterface $client,
        OdooSyncRecord $syncRecord,
        $localRecord,
        array $odooData
    ): array {
        return DB::transaction(function () use ($mapping, $client, $syncRecord, $localRecord, $odooData) {
            // Same payload handling as the no-conflict path: extract any
            // workflow actions emitted by applyOdooExport (e.g. action_approve
            // for hr.leave) and dispatch them after the write. Otherwise Odoo
            // sees "__odoo_actions" as an unknown field and rejects the write.
            $actions = $odooData['__odoo_actions'] ?? [];
            unset($odooData['__odoo_actions']);

            if (! empty($odooData)) {
                $client->write($mapping->odoo_model, [$syncRecord->odoo_id], $odooData);
            }

            foreach ($actions as $method) {
                try {
                    $client->execute($mapping->odoo_model, $method, [[$syncRecord->odoo_id]]);
                } catch (\Throwable $e) {
                    // Same "cannot marshal None" workaround as the main path:
                    // Odoo's action_* methods return None and Odoo's own RPC
                    // controller fails to marshal that response, even though
                    // the state transition has already applied server-side.
                    if (! str_contains($e->getMessage(), 'cannot marshal None')) {
                        throw $e;
                    }
                }
            }

            $odooRecord = $client->read($mapping->odoo_model, [$syncRecord->odoo_id]);
            $odooChecksum = $this->calculateChecksum($odooRecord[0] ?? []);
            $localChecksum = $this->calculateChecksum($localRecord->toArray());

            $localRecord->update(['odoo_synced_at' => now()]);
            $syncRecord->markSynced('export', $localChecksum, $odooChecksum);

            return [
                'action' => 'updated',
                'local_id' => $localRecord->id,
                'odoo_id' => $syncRecord->odoo_id,
                'resolution' => 'local_wins',
            ];
        });
    }

    /**
     * Resolve by keeping Odoo data.
     */
    protected function resolveOdooWins(OdooSyncRecord $syncRecord): array
    {
        // Mark record for re-import
        $syncRecord->update([
            'sync_status' => OdooSyncRecord::STATUS_PENDING,
        ]);

        return [
            'action' => 'skipped',
            'local_id' => $syncRecord->local_id,
            'odoo_id' => $syncRecord->odoo_id,
            'resolution' => 'odoo_wins',
        ];
    }

    /**
     * Resolve by keeping newest changes.
     */
    protected function resolveNewestWins(
        OdooEntityMapping $mapping,
        OdooApiClientInterface $client,
        OdooSyncRecord $syncRecord,
        $localRecord,
        array $odooData,
        array $currentOdooData
    ): array {
        $localUpdatedAt = $localRecord->updated_at;
        $odooUpdatedAt = isset($currentOdooData['write_date']) ? new \DateTime($currentOdooData['write_date']) : null;

        if (!$odooUpdatedAt || $localUpdatedAt > $odooUpdatedAt) {
            return $this->resolveLocalWins($mapping, $client, $syncRecord, $localRecord, $odooData);
        }

        return $this->resolveOdooWins($syncRecord);
    }

    /**
     * Create a conflict record.
     */
    protected function createConflictRecord(
        OdooEntityMapping $mapping,
        OdooSyncRecord $syncRecord,
        $localRecord,
        array $currentOdooData
    ): array {
        $syncRecord->markConflict();

        $conflict = OdooSyncConflict::createFromRecords(
            $syncRecord,
            $localRecord->toArray(),
            $currentOdooData,
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
     * Handle case where Odoo record was deleted.
     */
    protected function handleDeletedInOdoo(
        OdooEntityMapping $mapping,
        OdooApiClientInterface $client,
        OdooSyncRecord $syncRecord,
        $localRecord,
        array $odooData,
        string $localChecksum
    ): array {
        $resolution = $mapping->conflict_resolution;

        if ($resolution === ConflictResolution::MANUAL) {
            $syncRecord->markConflict();

            $conflict = OdooSyncConflict::create([
                'tenant_id' => $mapping->tenant_id,
                'sync_record_id' => $syncRecord->id,
                'entity_mapping_id' => $mapping->id,
                'status' => 'pending',
                'conflict_type' => OdooSyncConflict::TYPE_DELETED_REMOTELY,
                'local_data' => $localRecord->toArray(),
                'odoo_data' => null,
                'diff' => null,
            ]);

            event(new ConflictDetected($conflict));

            return [
                'action' => 'conflict',
                'local_id' => $localRecord->id,
                'conflict_id' => $conflict->id,
            ];
        }

        // Recreate in Odoo
        $odooId = $client->create($mapping->odoo_model, $odooData);

        $localRecord->update([
            'odoo_id' => $odooId,
            'odoo_synced_at' => now(),
        ]);

        $odooRecord = $client->read($mapping->odoo_model, [$odooId]);
        $odooChecksum = $this->calculateChecksum($odooRecord[0] ?? []);

        $syncRecord->update([
            'odoo_id' => $odooId,
            'sync_status' => OdooSyncRecord::STATUS_SYNCED,
            'last_sync_direction' => 'export',
            'last_synced_at' => now(),
            'local_checksum' => $localChecksum,
            'odoo_checksum' => $odooChecksum,
        ]);

        return [
            'action' => 'recreated',
            'local_id' => $localRecord->id,
            'odoo_id' => $odooId,
        ];
    }

    /**
     * Calculate checksum for conflict detection.
     */
    protected function calculateChecksum(array $data): string
    {
        unset($data['created_at'], $data['updated_at'], $data['odoo_synced_at']);
        ksort($data);
        return md5(json_encode($data));
    }
}
