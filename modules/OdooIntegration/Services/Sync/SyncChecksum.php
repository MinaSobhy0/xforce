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

    /**
     * Checksum an Odoo record over ONLY the mapping's odoo fields (plus the
     * standard id/write_date/create_date the engine always fetches). Import
     * stores checksums from mapped-subset reads while export used to read
     * full records — a full-record basis can never match the stored one, so
     * both sides must checksum the same subset.
     */
    public static function forOdoo(\Modules\OdooIntegration\Models\OdooEntityMapping $mapping, array $record): string
    {
        $fields = ['id', 'write_date', 'create_date'];
        foreach ($mapping->getActiveFieldMappings() as $fieldMapping) {
            if (! empty($fieldMapping->odoo_field)) {
                $fields[] = $fieldMapping->odoo_field;
            }
        }

        return self::calculate(array_intersect_key($record, array_flip($fields)));
    }
}
