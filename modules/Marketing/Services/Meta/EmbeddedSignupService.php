<?php

namespace Modules\Marketing\Services\Meta;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Auth\Models\User;
use Modules\Core\Models\Tenant;
use Modules\Marketing\Services\WhatsAppCredentialsResolver;
use RuntimeException;

/**
 * Drives the Meta Embedded Signup flow that's launched from the tenant's
 * "Connect WhatsApp" button in the Admin panel.
 *
 * The JS popup returns three things to the page:
 *   - code              (one-time OAuth code)
 *   - waba_id           (the WhatsApp Business Account the tenant picked)
 *   - phone_number_id   (the phone number inside that WABA)
 *
 * connectTenant() takes those, exchanges the code for a permanent System
 * User access token via Graph API (using XForce's app credentials stored
 * in platform_settings), then persists encrypted state under
 * tenants.settings.messaging.whatsapp.meta.* so future sends use the
 * tenant's own WABA.
 *
 * No `.env` keys are read here — all platform creds come from
 * PlatformSetting so a SuperAdmin can rotate them from the UI.
 */
class EmbeddedSignupService
{
    public function __construct(
        protected WhatsAppCredentialsResolver $resolver,
    ) {}

    /**
     * Complete the signup for a tenant.
     *
     * @param  array{code: string, phone_number_id: string, waba_id: string}  $signup
     * @return array  {status, phone_number_id, waba_id, display_name, phone_e164}
     *
     * @throws RuntimeException  if the platform Tech Provider creds aren't
     *                           configured yet (admin hasn't pasted app_id /
     *                           secret) or Meta rejects the exchange.
     */
    public function connectTenant(Tenant $tenant, array $signup, ?User $actor = null): array
    {
        $this->markPending($tenant);

        try {
            $token = $this->exchangeCodeForToken($signup['code']);
            $waba = $this->fetchWabaDetails($token, $signup['waba_id']);
            $phone = $this->fetchPhoneDetails($token, $signup['phone_number_id']);

            $this->persistConnection($tenant, [
                'access_token' => $token,
                'waba_id' => $signup['waba_id'],
                'phone_number_id' => $signup['phone_number_id'],
                'business_id' => $waba['business_id'] ?? null,
                'display_name' => $phone['verified_name'] ?? ($waba['name'] ?? null),
                'phone_e164' => $phone['display_phone_number'] ?? null,
                'actor_user_id' => $actor?->id,
            ]);

            $this->resolver->flush($tenant);

            return [
                'status' => 'active',
                'phone_number_id' => $signup['phone_number_id'],
                'waba_id' => $signup['waba_id'],
                'display_name' => $phone['verified_name'] ?? null,
                'phone_e164' => $phone['display_phone_number'] ?? null,
            ];
        } catch (\Throwable $e) {
            $this->markFailed($tenant, $e->getMessage());

            Log::error('whatsapp.embedded_signup_failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), previous: $e);
        }
    }

    /**
     * Wipe the tenant's Meta connection. Does NOT revoke at Meta's side —
     * the tenant must do that manually in Meta Business Manager if they
     * want to remove XForce as a Tech Provider.
     */
    public function disconnect(Tenant $tenant): void
    {
        $tenant->setSetting('messaging.whatsapp.meta', [
            'status' => 'disconnected',
            'disconnected_at' => now()->toIso8601String(),
        ]);

        $this->resolver->flush($tenant);
    }

    /**
     * Exchange the one-time signup code for a permanent System User access
     * token. Meta returns a `User Access Token` here; with the Embedded
     * Signup config_id it's long-lived (does not expire on its own).
     */
    protected function exchangeCodeForToken(string $code): string
    {
        [$appId, $appSecret] = $this->requirePlatformCreds();

        $response = Http::get("https://graph.facebook.com/{$this->graphVersion()}/oauth/access_token", [
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'code' => $code,
        ]);

        if ($response->failed() || ! ($token = $response->json('access_token'))) {
            $msg = $response->json('error.message') ?? 'Meta did not return an access token.';
            throw new RuntimeException("Meta token exchange failed: {$msg}");
        }

        return (string) $token;
    }

    protected function fetchWabaDetails(string $token, string $wabaId): array
    {
        $response = Http::withToken($token)
            ->get("https://graph.facebook.com/{$this->graphVersion()}/{$wabaId}", [
                'fields' => 'id,name,timezone_id,currency,business_id',
            ]);

        return $response->ok() ? $response->json() ?? [] : [];
    }

    protected function fetchPhoneDetails(string $token, string $phoneNumberId): array
    {
        $response = Http::withToken($token)
            ->get("https://graph.facebook.com/{$this->graphVersion()}/{$phoneNumberId}", [
                'fields' => 'id,display_phone_number,verified_name,quality_rating',
            ]);

        return $response->ok() ? $response->json() ?? [] : [];
    }

    protected function markPending(Tenant $tenant): void
    {
        $existing = $tenant->getSetting('messaging.whatsapp.meta', []) ?: [];
        $existing['status'] = 'pending';
        unset($existing['last_error']);
        $tenant->setSetting('messaging.whatsapp.meta', $existing);
    }

    protected function markFailed(Tenant $tenant, string $error): void
    {
        $existing = $tenant->getSetting('messaging.whatsapp.meta', []) ?: [];
        $existing['status'] = 'failed';
        $existing['last_error'] = $error;
        $tenant->setSetting('messaging.whatsapp.meta', $existing);
    }

    /**
     * Encrypt the access token before persistence so the JSONB column
     * doesn't sit with a long-lived plaintext credential.
     */
    protected function persistConnection(Tenant $tenant, array $data): void
    {
        $tenant->setSetting('messaging.whatsapp.meta', [
            'status' => 'active',
            'phone_number_id' => $data['phone_number_id'],
            'waba_id' => $data['waba_id'],
            'business_id' => $data['business_id'],
            'access_token' => Crypt::encryptString($data['access_token']),
            'phone_e164' => $data['phone_e164'],
            'display_name' => $data['display_name'],
            'connected_at' => now()->toIso8601String(),
            'connected_by_user_id' => $data['actor_user_id'],
            'last_error' => null,
        ]);
    }

    /**
     * @return array{0: string, 1: string}  [app_id, app_secret]
     */
    protected function requirePlatformCreds(): array
    {
        $appId = (string) PlatformSetting::get('whatsapp_meta_app_id', '');
        $appSecret = (string) PlatformSetting::get('whatsapp_meta_app_secret', '');

        if ($appId === '' || $appSecret === '') {
            throw new RuntimeException('Meta Tech Provider is not configured. Set whatsapp_meta_app_id and whatsapp_meta_app_secret in Platform → Integrations.');
        }

        // App secret may have been stored encrypted in a future revision —
        // tolerate both forms (mirrors the resolver's maybeDecrypt logic).
        try {
            $appSecret = Crypt::decryptString($appSecret);
        } catch (\Throwable) {
            // Plain text, use as-is.
        }

        return [$appId, $appSecret];
    }

    protected function graphVersion(): string
    {
        return (string) PlatformSetting::get(
            'whatsapp_meta_api_version',
            config('marketing.whatsapp.api_version', 'v18.0'),
        );
    }
}
