<?php

namespace Modules\OdooIntegration\Services\Transform;

use Illuminate\Support\Facades\Cache;
use Modules\OdooIntegration\Models\OdooSyncRecord;
use Modules\OdooIntegration\Models\OdooEntityMapping;

class RelationResolver
{
    /**
     * Cache TTL in seconds.
     */
    protected const CACHE_TTL = 3600;

    /**
     * Resolve an Odoo ID to a local ID.
     */
    public function resolveToLocalId(
        int $odooId,
        ?string $localModel,
        ?string $odooModel,
        int $connectionId
    ): ?int {
        if (!$odooId) {
            return null;
        }

        // Try cache first
        $cacheKey = $this->getCacheKey('to_local', $connectionId, $odooModel ?? '', $odooId);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached ?: null;
        }

        // Look up in sync records
        $syncRecord = OdooSyncRecord::query()
            ->whereHas('entityMapping', function ($q) use ($connectionId, $localModel, $odooModel) {
                $q->where('odoo_connection_id', $connectionId);
                if ($localModel) {
                    $q->where('local_model', $localModel);
                }
                if ($odooModel) {
                    $q->where('odoo_model', $odooModel);
                }
            })
            ->where('odoo_id', $odooId)
            ->first();

        $localId = $syncRecord?->local_id;

        // If not in sync records, try direct lookup by odoo_id column
        if (!$localId && $localModel && class_exists($localModel)) {
            $record = $localModel::where('odoo_id', $odooId)->first();
            $localId = $record?->id;
        }

        // Cache result (including null as false)
        Cache::put($cacheKey, $localId ?: false, self::CACHE_TTL);

        return $localId;
    }

    /**
     * Resolve a local ID to an Odoo ID.
     */
    public function resolveToOdooId(
        int $localId,
        ?string $localModel,
        int $connectionId
    ): ?int {
        if (!$localId) {
            return null;
        }

        // Try cache first
        $cacheKey = $this->getCacheKey('to_odoo', $connectionId, $localModel ?? '', $localId);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached ?: null;
        }

        // Try direct lookup by odoo_id column first (faster)
        if ($localModel && class_exists($localModel)) {
            $record = $localModel::find($localId);
            if ($record && isset($record->odoo_id) && $record->odoo_id) {
                Cache::put($cacheKey, $record->odoo_id, self::CACHE_TTL);
                return $record->odoo_id;
            }
        }

        // Fall back to sync records
        $syncRecord = OdooSyncRecord::query()
            ->whereHas('entityMapping', function ($q) use ($connectionId, $localModel) {
                $q->where('odoo_connection_id', $connectionId);
                if ($localModel) {
                    $q->where('local_model', $localModel);
                }
            })
            ->where('local_id', $localId)
            ->first();

        $odooId = $syncRecord?->odoo_id;

        // Cache result
        Cache::put($cacheKey, $odooId ?: false, self::CACHE_TTL);

        return $odooId;
    }

    /**
     * Bulk resolve Odoo IDs to local IDs.
     */
    public function bulkResolveToLocalIds(
        array $odooIds,
        ?string $localModel,
        ?string $odooModel,
        int $connectionId
    ): array {
        if (empty($odooIds)) {
            return [];
        }

        $results = [];
        $uncached = [];

        // Check cache for each ID
        foreach ($odooIds as $odooId) {
            $cacheKey = $this->getCacheKey('to_local', $connectionId, $odooModel ?? '', $odooId);
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                $results[$odooId] = $cached ?: null;
            } else {
                $uncached[] = $odooId;
            }
        }

        // Bulk lookup uncached IDs
        if (!empty($uncached)) {
            $syncRecords = OdooSyncRecord::query()
                ->whereHas('entityMapping', function ($q) use ($connectionId, $localModel, $odooModel) {
                    $q->where('odoo_connection_id', $connectionId);
                    if ($localModel) {
                        $q->where('local_model', $localModel);
                    }
                    if ($odooModel) {
                        $q->where('odoo_model', $odooModel);
                    }
                })
                ->whereIn('odoo_id', $uncached)
                ->get()
                ->keyBy('odoo_id');

            foreach ($uncached as $odooId) {
                $localId = $syncRecords->get($odooId)?->local_id;
                $results[$odooId] = $localId;

                // Cache result
                $cacheKey = $this->getCacheKey('to_local', $connectionId, $odooModel ?? '', $odooId);
                Cache::put($cacheKey, $localId ?: false, self::CACHE_TTL);
            }
        }

        return $results;
    }

    /**
     * Bulk resolve local IDs to Odoo IDs.
     */
    public function bulkResolveToOdooIds(
        array $localIds,
        ?string $localModel,
        int $connectionId
    ): array {
        if (empty($localIds)) {
            return [];
        }

        $results = [];
        $uncached = [];

        // Check cache for each ID
        foreach ($localIds as $localId) {
            $cacheKey = $this->getCacheKey('to_odoo', $connectionId, $localModel ?? '', $localId);
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                $results[$localId] = $cached ?: null;
            } else {
                $uncached[] = $localId;
            }
        }

        // Bulk lookup uncached IDs
        if (!empty($uncached) && $localModel && class_exists($localModel)) {
            $records = $localModel::whereIn('id', $uncached)
                ->whereNotNull('odoo_id')
                ->get()
                ->keyBy('id');

            foreach ($uncached as $localId) {
                $odooId = $records->get($localId)?->odoo_id;
                $results[$localId] = $odooId;

                // Cache result
                $cacheKey = $this->getCacheKey('to_odoo', $connectionId, $localModel ?? '', $localId);
                Cache::put($cacheKey, $odooId ?: false, self::CACHE_TTL);
            }
        }

        return $results;
    }

    /**
     * Clear cache for a specific record.
     */
    public function clearCache(int $connectionId, string $model, int $localId, int $odooId): void
    {
        Cache::forget($this->getCacheKey('to_local', $connectionId, $model, $odooId));
        Cache::forget($this->getCacheKey('to_odoo', $connectionId, $model, $localId));
    }

    /**
     * Build cache key.
     */
    protected function getCacheKey(string $direction, int $connectionId, string $model, int $id): string
    {
        $modelKey = str_replace('\\', '_', $model);
        return "odoo_relation_{$direction}_{$connectionId}_{$modelKey}_{$id}";
    }

    /**
     * Get or create mapping for a model.
     */
    public function getEntityMapping(int $connectionId, string $localModel): ?OdooEntityMapping
    {
        return OdooEntityMapping::where('odoo_connection_id', $connectionId)
            ->where('local_model', $localModel)
            ->where('is_active', true)
            ->first();
    }
}
