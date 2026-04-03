<?php

namespace Modules\OdooIntegration\Services\Sync;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\OdooIntegration\Models\OdooEntityMapping;
use Modules\OdooIntegration\Models\OdooSyncLog;

class WatermarkService
{
    /**
     * Cache prefix for watermarks.
     */
    protected const CACHE_PREFIX = 'odoo_watermark_';

    /**
     * Get the watermark (last sync timestamp) for an entity mapping.
     */
    public function getWatermark(OdooEntityMapping $mapping, string $direction = 'import'): ?string
    {
        $cacheKey = $this->getCacheKey($mapping, $direction);

        // Try cache first
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        // Fall back to database
        $watermark = $this->getWatermarkFromDatabase($mapping, $direction);

        if ($watermark) {
            // Cache for 1 hour
            Cache::put($cacheKey, $watermark, 3600);
        }

        return $watermark;
    }

    /**
     * Set the watermark for an entity mapping.
     */
    public function setWatermark(OdooEntityMapping $mapping, string $direction, string $timestamp): void
    {
        $cacheKey = $this->getCacheKey($mapping, $direction);

        // Validate timestamp format
        if (!$this->isValidTimestamp($timestamp)) {
            return;
        }

        // Only update if newer than current
        $current = $this->getWatermark($mapping, $direction);
        if ($current && $timestamp <= $current) {
            return;
        }

        // Update cache
        Cache::put($cacheKey, $timestamp, 3600);

        // Update last sync log watermark (for persistence)
        $this->updateLastSyncLogWatermark($mapping, $direction, $timestamp);
    }

    /**
     * Clear watermark for an entity mapping.
     */
    public function clearWatermark(OdooEntityMapping $mapping, ?string $direction = null): void
    {
        if ($direction) {
            Cache::forget($this->getCacheKey($mapping, $direction));
        } else {
            Cache::forget($this->getCacheKey($mapping, 'import'));
            Cache::forget($this->getCacheKey($mapping, 'export'));
        }
    }

    /**
     * Clear all watermarks for a connection.
     */
    public function clearConnectionWatermarks(int $connectionId): void
    {
        $mappings = OdooEntityMapping::where('odoo_connection_id', $connectionId)->get();

        foreach ($mappings as $mapping) {
            $this->clearWatermark($mapping);
        }
    }

    /**
     * Get watermark from database (last successful sync log).
     */
    protected function getWatermarkFromDatabase(OdooEntityMapping $mapping, string $direction): ?string
    {
        $log = OdooSyncLog::where('entity_mapping_id', $mapping->id)
            ->where('direction', $direction)
            ->where('status', 'completed')
            ->whereNotNull('watermark')
            ->orderBy('completed_at', 'desc')
            ->first();

        return $log?->watermark;
    }

    /**
     * Update the watermark in the last sync log.
     */
    protected function updateLastSyncLogWatermark(OdooEntityMapping $mapping, string $direction, string $timestamp): void
    {
        DB::table('odoo_sync_logs')
            ->where('entity_mapping_id', $mapping->id)
            ->where('direction', $direction)
            ->where('status', 'running')
            ->orderBy('id', 'desc')
            ->limit(1)
            ->update(['watermark' => $timestamp]);
    }

    /**
     * Get cache key for a mapping and direction.
     */
    protected function getCacheKey(OdooEntityMapping $mapping, string $direction): string
    {
        return self::CACHE_PREFIX . "{$mapping->id}_{$direction}";
    }

    /**
     * Validate timestamp format (Odoo datetime format).
     */
    protected function isValidTimestamp(string $timestamp): bool
    {
        // Odoo uses ISO format: 2024-01-01 12:00:00 or 2024-01-01T12:00:00
        $patterns = [
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $timestamp)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reset watermark to force full sync.
     */
    public function resetForFullSync(OdooEntityMapping $mapping): void
    {
        $this->clearWatermark($mapping);

        // Also clear any running sync logs' watermarks
        DB::table('odoo_sync_logs')
            ->where('entity_mapping_id', $mapping->id)
            ->update(['watermark' => null]);
    }

    /**
     * Get all watermarks for a connection.
     */
    public function getConnectionWatermarks(int $connectionId): array
    {
        $mappings = OdooEntityMapping::where('odoo_connection_id', $connectionId)
            ->where('is_active', true)
            ->get();

        $watermarks = [];

        foreach ($mappings as $mapping) {
            $watermarks[$mapping->id] = [
                'name' => $mapping->name,
                'import' => $this->getWatermark($mapping, 'import'),
                'export' => $this->getWatermark($mapping, 'export'),
            ];
        }

        return $watermarks;
    }
}
