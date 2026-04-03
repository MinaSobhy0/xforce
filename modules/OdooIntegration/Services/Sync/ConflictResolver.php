<?php

namespace Modules\OdooIntegration\Services\Sync;

use Illuminate\Support\Facades\DB;
use Modules\OdooIntegration\Models\OdooSyncConflict;
use Modules\OdooIntegration\Models\OdooSyncRecord;
use Modules\OdooIntegration\Services\Api\OdooApiFactory;
use Modules\OdooIntegration\Services\Transform\FieldTransformer;
use Modules\OdooIntegration\Exceptions\OdooSyncException;

class ConflictResolver
{
    public function __construct(
        protected OdooApiFactory $apiFactory,
        protected FieldTransformer $transformer,
    ) {}

    /**
     * Resolve a conflict by keeping local data.
     */
    public function keepLocal(OdooSyncConflict $conflict, int $userId, ?string $notes = null): array
    {
        $syncRecord = $conflict->syncRecord;
        $mapping = $conflict->entityMapping;
        $connection = $mapping->connection;

        $localRecord = $syncRecord->getLocalRecord();
        if (!$localRecord) {
            throw new OdooSyncException('Local record no longer exists');
        }

        $client = $this->apiFactory->make($connection);
        $client->authenticate();

        return DB::transaction(function () use ($conflict, $syncRecord, $mapping, $localRecord, $client, $userId, $notes) {
            // Transform and push to Odoo
            $odooData = $this->transformer->transformExport($mapping, $localRecord->toArray());

            if ($syncRecord->odoo_id) {
                $client->write($mapping->odoo_model, [$syncRecord->odoo_id], $odooData);
            } else {
                $odooId = $client->create($mapping->odoo_model, $odooData);
                $syncRecord->update(['odoo_id' => $odooId]);
                $localRecord->update(['odoo_id' => $odooId]);
            }

            // Update checksums
            $odooRecord = $client->read($mapping->odoo_model, [$syncRecord->odoo_id]);
            $localChecksum = md5(json_encode($localRecord->toArray()));
            $odooChecksum = md5(json_encode($odooRecord[0] ?? []));

            $localRecord->update(['odoo_synced_at' => now()]);
            $syncRecord->markSynced('export', $localChecksum, $odooChecksum);

            // Resolve conflict
            $conflict->resolve(OdooSyncConflict::RESOLUTION_KEEP_LOCAL, $userId, $notes);

            return [
                'success' => true,
                'action' => 'keep_local',
                'local_id' => $syncRecord->local_id,
                'odoo_id' => $syncRecord->odoo_id,
            ];
        });
    }

    /**
     * Resolve a conflict by keeping Odoo data.
     */
    public function keepOdoo(OdooSyncConflict $conflict, int $userId, ?string $notes = null): array
    {
        $syncRecord = $conflict->syncRecord;
        $mapping = $conflict->entityMapping;
        $connection = $mapping->connection;

        $client = $this->apiFactory->make($connection);
        $client->authenticate();

        return DB::transaction(function () use ($conflict, $syncRecord, $mapping, $client, $userId, $notes) {
            // Fetch latest Odoo data
            $odooRecords = $client->read($mapping->odoo_model, [$syncRecord->odoo_id]);

            if (empty($odooRecords)) {
                // Odoo record deleted - handle deletion
                return $this->handleOdooDeleted($conflict, $userId, $notes);
            }

            $odooData = $odooRecords[0];
            $localData = $this->transformer->transformImport($mapping, $odooData);

            $localRecord = $syncRecord->getLocalRecord();
            $modelClass = $mapping->local_model;

            if ($localRecord) {
                $localRecord->update(array_merge($localData, ['odoo_synced_at' => now()]));
            } else {
                // Recreate local record
                $localRecord = new $modelClass();
                $localRecord->fill(array_merge($localData, [
                    'tenant_id' => $mapping->tenant_id,
                    'odoo_id' => $syncRecord->odoo_id,
                    'odoo_synced_at' => now(),
                ]));
                $localRecord->save();

                $syncRecord->update(['local_id' => $localRecord->id]);
            }

            // Update checksums
            $localChecksum = md5(json_encode($localRecord->fresh()->toArray()));
            $odooChecksum = md5(json_encode($odooData));

            $syncRecord->markSynced('import', $localChecksum, $odooChecksum);

            // Resolve conflict
            $conflict->resolve(OdooSyncConflict::RESOLUTION_KEEP_ODOO, $userId, $notes);

            return [
                'success' => true,
                'action' => 'keep_odoo',
                'local_id' => $syncRecord->local_id,
                'odoo_id' => $syncRecord->odoo_id,
            ];
        });
    }

    /**
     * Resolve a conflict by merging specific fields.
     */
    public function merge(OdooSyncConflict $conflict, array $fieldChoices, int $userId, ?string $notes = null): array
    {
        $syncRecord = $conflict->syncRecord;
        $mapping = $conflict->entityMapping;
        $connection = $mapping->connection;

        $localRecord = $syncRecord->getLocalRecord();
        if (!$localRecord) {
            throw new OdooSyncException('Local record no longer exists');
        }

        $client = $this->apiFactory->make($connection);
        $client->authenticate();

        return DB::transaction(function () use ($conflict, $syncRecord, $mapping, $localRecord, $client, $fieldChoices, $userId, $notes) {
            // Start with local data
            $mergedLocalData = $localRecord->toArray();
            $mergedOdooData = [];

            // Apply field choices
            foreach ($fieldChoices as $field => $choice) {
                if ($choice === 'odoo') {
                    $mergedLocalData[$field] = $conflict->odoo_data[$field] ?? null;
                }
            }

            // Update local record
            $localRecord->update(array_merge($mergedLocalData, ['odoo_synced_at' => now()]));

            // Transform merged data for Odoo
            $odooData = $this->transformer->transformExport($mapping, $localRecord->fresh()->toArray());

            // Update Odoo
            $client->write($mapping->odoo_model, [$syncRecord->odoo_id], $odooData);

            // Update checksums
            $odooRecord = $client->read($mapping->odoo_model, [$syncRecord->odoo_id]);
            $localChecksum = md5(json_encode($localRecord->fresh()->toArray()));
            $odooChecksum = md5(json_encode($odooRecord[0] ?? []));

            $syncRecord->markSynced('bidirectional', $localChecksum, $odooChecksum);

            // Resolve conflict
            $conflict->resolve(OdooSyncConflict::RESOLUTION_MERGE, $userId, $notes);

            return [
                'success' => true,
                'action' => 'merge',
                'local_id' => $syncRecord->local_id,
                'odoo_id' => $syncRecord->odoo_id,
                'merged_fields' => $fieldChoices,
            ];
        });
    }

    /**
     * Skip/dismiss a conflict without resolving.
     */
    public function skip(OdooSyncConflict $conflict, int $userId, ?string $notes = null): array
    {
        $conflict->dismiss($userId, $notes);

        return [
            'success' => true,
            'action' => 'skip',
            'conflict_id' => $conflict->id,
        ];
    }

    /**
     * Handle case where Odoo record was deleted.
     */
    protected function handleOdooDeleted(OdooSyncConflict $conflict, int $userId, ?string $notes): array
    {
        $syncRecord = $conflict->syncRecord;
        $localRecord = $syncRecord->getLocalRecord();

        // Soft delete local record if it exists
        if ($localRecord && method_exists($localRecord, 'delete')) {
            $localRecord->delete();
        }

        // Mark sync record as archived
        $syncRecord->markArchived();

        $conflict->resolve(OdooSyncConflict::RESOLUTION_KEEP_ODOO, $userId, $notes ?? 'Odoo record deleted');

        return [
            'success' => true,
            'action' => 'deleted',
            'local_id' => $syncRecord->local_id,
        ];
    }

    /**
     * Get pending conflicts for a tenant.
     */
    public function getPendingConflicts(?int $tenantId = null): \Illuminate\Database\Eloquent\Collection
    {
        return OdooSyncConflict::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'pending')
            ->with(['syncRecord', 'entityMapping', 'entityMapping.connection'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get conflict count by entity.
     */
    public function getConflictCountByEntity(?int $tenantId = null): array
    {
        return OdooSyncConflict::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'pending')
            ->selectRaw('entity_mapping_id, count(*) as count')
            ->groupBy('entity_mapping_id')
            ->with('entityMapping')
            ->get()
            ->mapWithKeys(fn ($item) => [
                $item->entityMapping->name => $item->count,
            ])
            ->toArray();
    }
}
