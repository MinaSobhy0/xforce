<?php

namespace Modules\Marketing\Services\Meta;

use Illuminate\Support\Facades\Log;
use Modules\Core\Models\Tenant;

/**
 * Resolves a tenant from a Meta WhatsApp Business Account (WABA) id —
 * the only routing key Meta puts on inbound webhook deliveries.
 *
 * Meta payload shape (per developers.facebook.com/docs/whatsapp/cloud-api/webhooks):
 *   {
 *     "entry": [
 *       { "id": "<WABA_ID>",   // <-- this, NOT phone_number_id
 *         "changes": [ { "value": { "metadata": {"phone_number_id": "..."}, ... } } ] }
 *     ]
 *   }
 *
 * Per-request cache keeps repeat resolutions cheap when a single webhook
 * payload contains multiple `entry` items for the same tenant.
 */
class WabaTenantResolver
{
    /** @var array<string, ?Tenant> */
    protected array $cache = [];

    public function resolveByWabaId(string $wabaId): ?Tenant
    {
        if ($wabaId === '') {
            return null;
        }

        if (array_key_exists($wabaId, $this->cache)) {
            return $this->cache[$wabaId];
        }

        $tenant = Tenant::findByWabaId($wabaId);

        Log::info('whatsapp.webhook.tenant_resolve', [
            'waba_id' => $wabaId,
            'tenant_id' => $tenant?->id,
            'hit' => $tenant !== null,
        ]);

        return $this->cache[$wabaId] = $tenant;
    }

    public function flush(): void
    {
        $this->cache = [];
    }
}
