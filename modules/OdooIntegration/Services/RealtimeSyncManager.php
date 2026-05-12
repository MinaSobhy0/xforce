<?php

namespace Modules\OdooIntegration\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Modules\OdooIntegration\Enums\SyncFrequency;
use Modules\OdooIntegration\Models\OdooEntityMapping;

/**
 * Drives the "Real-time" sync_frequency option on OdooEntityMapping.
 *
 * Resolved on first save of any model: looks up an active mapping whose
 * sync_frequency is REALTIME and whose direction permits export. If one
 * matches, a RealtimeSyncJob is dispatched for that record's id.
 *
 * Loop prevention: ImportService wraps its own writes in suppress() so the
 * downstream `eloquent.saved: *` listener skips the dispatch.
 */
class RealtimeSyncManager
{
    /** Suppression depth for nested suppressions (saving inside saving). */
    protected static int $suppressionDepth = 0;

    /** Per-request cache: model class => mapping (or false when none). */
    protected static ?Collection $mappingsByModel = null;

    /**
     * Run a callback with realtime sync dispatching suppressed. Used by the
     * Import path so writes triggered by Odoo→local sync don't echo back.
     */
    public static function suppress(callable $callback): mixed
    {
        self::$suppressionDepth++;

        try {
            return $callback();
        } finally {
            self::$suppressionDepth--;
        }
    }

    public static function isSuppressed(): bool
    {
        return self::$suppressionDepth > 0;
    }

    /**
     * Return the active realtime+export mapping for the given model class,
     * or null. Cached per request.
     */
    public static function mappingFor(string $modelClass): ?OdooEntityMapping
    {
        if (self::$mappingsByModel === null) {
            try {
                self::$mappingsByModel = OdooEntityMapping::query()
                    ->where('sync_frequency', SyncFrequency::REALTIME->value)
                    ->where('is_active', true)
                    ->get()
                    ->keyBy('local_model');
            } catch (\Throwable $e) {
                // DB not ready (migrations, tenancy switch, etc.) — degrade gracefully.
                self::$mappingsByModel = collect();
            }
        }

        $mapping = self::$mappingsByModel->get($modelClass);

        if (! $mapping) {
            return null;
        }

        return $mapping->sync_direction->allowsExport() ? $mapping : null;
    }

    /**
     * Force a re-read on next mappingFor() call.
     * Useful when admins toggle a mapping mid-request (rare).
     */
    public static function flush(): void
    {
        self::$mappingsByModel = null;
    }

    /**
     * Decide whether a saved model should trigger a realtime export.
     * Caller is responsible for the actual dispatch.
     */
    public static function shouldDispatchFor(Model $model): ?OdooEntityMapping
    {
        if (self::isSuppressed()) {
            return null;
        }

        // Models that don't track an Odoo link can't participate.
        if (! in_array('odoo_id', $model->getFillable(), true)
            && ! array_key_exists('odoo_id', $model->getAttributes())) {
            return null;
        }

        // Don't echo: skip writes whose only payload was the post-sync metadata.
        $changes = $model->getChanges();
        unset($changes['updated_at'], $changes['odoo_synced_at']);
        if (empty($changes) && ! $model->wasRecentlyCreated) {
            return null;
        }

        return self::mappingFor($model::class);
    }
}
