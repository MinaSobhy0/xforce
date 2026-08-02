<?php

namespace Modules\OdooIntegration\Services\Sync;

use Modules\OdooIntegration\Services\Api\OdooApiClientInterface;

/**
 * Which fields an Odoo model actually has (fields_get, cached per client
 * instance). Used to silently drop mapped custom fields that don't exist on
 * a given Odoo install — a missing custom field must degrade to "not
 * synced", never break the whole entity sync. Returns null when the
 * catalog itself can't be fetched (no filtering is applied then).
 */
final class OdooFieldCatalog
{
    /** @var array<string, array<int, string>|null> */
    private static array $cache = [];

    public static function available(OdooApiClientInterface $client, string $model): ?array
    {
        $key = spl_object_id($client).':'.$model;

        if (! array_key_exists($key, self::$cache)) {
            try {
                $fields = $client->execute($model, 'fields_get', [], ['attributes' => ['type']]);
                self::$cache[$key] = is_array($fields) && $fields !== [] ? array_keys($fields) : null;
            } catch (\Throwable) {
                self::$cache[$key] = null;
            }
        }

        return self::$cache[$key];
    }

    public static function flush(): void
    {
        self::$cache = [];
    }
}
