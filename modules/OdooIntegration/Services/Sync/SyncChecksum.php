<?php

namespace Modules\OdooIntegration\Services\Sync;

/**
 * Canonical checksum for change detection on both sides of the sync.
 * Timestamps are excluded — they move on every sync stamp and would make
 * every record look modified.
 */
final class SyncChecksum
{
    public static function calculate(array $data): string
    {
        unset($data['created_at'], $data['updated_at'], $data['odoo_synced_at']);
        ksort($data);

        return md5(json_encode($data));
    }

    /**
     * Checksum a model deterministically: loaded relations are excluded —
     * whether a relation happens to be loaded (e.g. by an export hook) must
     * not change the record's identity.
     */
    public static function forModel(\Illuminate\Database\Eloquent\Model $model): string
    {
        return self::calculate($model->withoutRelations()->toArray());
    }
}
