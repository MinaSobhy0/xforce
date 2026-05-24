<?php

namespace Modules\Marketing\Services;

use App\Models\PlatformSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\Tenant;

/**
 * Resolves Meta WhatsApp Business API credentials for a sender.
 *
 * Lookup order:
 *   1. Tenant's own connection (tenants.settings.messaging.whatsapp.meta.*),
 *      if the tenant has completed Embedded Signup and status = active.
 *   2. Platform-global defaults from platform_settings (the same keys
 *      that drive SuperAdmin → Integrations → WhatsApp). This keeps
 *      un-onboarded tenants working under XForce's umbrella while we
 *      roll out the per-tenant connect flow.
 *
 * Encrypted tokens (Crypt::encryptString) are decrypted here; plaintext
 * is tolerated so half-migrated rows don't crash.
 */
class WhatsAppCredentialsResolver
{
    /**
     * Per-request cache so a single send loop doesn't re-read settings/
     * decrypt tokens for every recipient.
     *
     * @var array<int|string, array|null>
     */
    protected array $cache = [];

    /**
     * @return array|null  ['phone_number_id', 'access_token', 'business_id',
     *                      'api_version', 'source' => 'tenant'|'platform']
     *                     or null when neither tenant nor platform is configured.
     */
    public function resolveFor(?Tenant $tenant): ?array
    {
        $cacheKey = $tenant?->id ?? 'platform';
        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $resolved = $this->resolveFromTenant($tenant) ?? $this->resolveFromPlatform();

        if ($resolved) {
            Log::info('whatsapp.creds_resolved', [
                'source' => $resolved['source'],
                'tenant_id' => $tenant?->id,
            ]);
        }

        return $this->cache[$cacheKey] = $resolved;
    }

    /**
     * Drop the cached entry for a tenant — call after connect/disconnect
     * so the next send picks up the new state without a worker restart.
     */
    public function flush(?Tenant $tenant = null): void
    {
        if ($tenant) {
            unset($this->cache[$tenant->id]);

            return;
        }

        $this->cache = [];
    }

    protected function resolveFromTenant(?Tenant $tenant): ?array
    {
        if (! $tenant) {
            return null;
        }

        $meta = $tenant->getSetting('messaging.whatsapp.meta');
        if (! is_array($meta) || ($meta['status'] ?? null) !== 'active') {
            return null;
        }

        $token = $this->maybeDecrypt($meta['access_token'] ?? null);
        $phoneNumberId = $meta['phone_number_id'] ?? null;
        if (! $token || ! $phoneNumberId) {
            return null;
        }

        return [
            'phone_number_id' => (string) $phoneNumberId,
            'access_token' => $token,
            'business_id' => $meta['waba_id'] ?? ($meta['business_id'] ?? null),
            'api_version' => $meta['api_version']
                ?? PlatformSetting::get('whatsapp_meta_api_version', 'v18.0'),
            'source' => 'tenant',
        ];
    }

    protected function resolveFromPlatform(): ?array
    {
        $token = $this->maybeDecrypt(PlatformSetting::get('whatsapp_meta_access_token', ''));
        $phoneNumberId = (string) PlatformSetting::get('whatsapp_meta_phone_number_id', '');

        if (! $token || ! $phoneNumberId) {
            return null;
        }

        return [
            'phone_number_id' => $phoneNumberId,
            'access_token' => $token,
            'business_id' => PlatformSetting::get('whatsapp_meta_business_id', null),
            'api_version' => PlatformSetting::get('whatsapp_meta_api_version', config('marketing.whatsapp.api_version', 'v18.0')),
            'source' => 'platform',
        ];
    }

    /**
     * Decrypt a token if it looks encrypted; otherwise return as-is.
     * Laravel's Crypt::encryptString produces base64 ciphertext that's
     * never going to look like a real Meta access token, so a failed
     * decrypt is a strong signal the value was stored plaintext.
     */
    protected function maybeDecrypt(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
    }
}
