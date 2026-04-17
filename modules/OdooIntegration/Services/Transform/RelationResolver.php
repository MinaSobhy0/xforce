<?php

namespace Modules\OdooIntegration\Services\Transform;

use Modules\OdooIntegration\Models\OdooEntityMapping;

class RelationResolver
{
    /**
     * In-memory cache for the current request.
     */
    protected array $cache = [];

    /**
     * Resolve an Odoo ID to a local ID.
     * Checks the local model's odoo_id column directly.
     */
    public function resolveToLocalId(
        int $odooId,
        ?string $localModel,
        ?string $odooModel,
        int $connectionId
    ): ?int {
        if (!$odooId || !$localModel || !class_exists($localModel)) {
            return null;
        }

        // Check in-memory cache first
        $cacheKey = "{$localModel}:{$odooId}";
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey] ?: null;
        }

        // Direct lookup by odoo_id column
        $record = $localModel::where('odoo_id', $odooId)->first();
        $localId = $record?->id;

        // Cache result (store false for null to differentiate from "not cached")
        $this->cache[$cacheKey] = $localId ?: false;

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
        if (!$localId || !$localModel || !class_exists($localModel)) {
            return null;
        }

        // Direct lookup
        $record = $localModel::find($localId);

        return $record?->odoo_id;
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
        if (empty($odooIds) || !$localModel || !class_exists($localModel)) {
            return [];
        }

        $results = [];
        $uncached = [];

        // Check in-memory cache
        foreach ($odooIds as $odooId) {
            $cacheKey = "{$localModel}:{$odooId}";
            if (isset($this->cache[$cacheKey])) {
                $results[$odooId] = $this->cache[$cacheKey] ?: null;
            } else {
                $uncached[] = $odooId;
            }
        }

        // Bulk lookup uncached
        if (!empty($uncached)) {
            $records = $localModel::whereIn('odoo_id', $uncached)->get()->keyBy('odoo_id');

            foreach ($uncached as $odooId) {
                $localId = $records->get($odooId)?->id;
                $results[$odooId] = $localId;
                $this->cache["{$localModel}:{$odooId}"] = $localId ?: false;
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
        if (empty($localIds) || !$localModel || !class_exists($localModel)) {
            return [];
        }

        $records = $localModel::whereIn('id', $localIds)
            ->whereNotNull('odoo_id')
            ->get()
            ->keyBy('id');

        $results = [];
        foreach ($localIds as $localId) {
            $results[$localId] = $records->get($localId)?->odoo_id;
        }

        return $results;
    }

    /**
     * Clear in-memory cache.
     */
    public function clearCache(): void
    {
        $this->cache = [];
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
