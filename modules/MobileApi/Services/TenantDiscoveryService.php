<?php

namespace Modules\MobileApi\Services;

use App\Models\TenantAppCode;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Tenant;

class TenantDiscoveryService
{
    /**
     * Resolve a tenant from an app code (QR, short code, etc).
     */
    public function resolveByCode(string $code): ?array
    {
        $appCode = TenantAppCode::with('tenant')
            ->byCode($code)
            ->first();

        if (! $appCode) {
            return null;
        }

        if (! $appCode->isValid()) {
            $errorData = $this->getCodeErrorData($appCode);

            return [
                'valid' => false,
                'error_code' => $errorData['code'],
                'error' => $errorData['message'],
            ];
        }

        $tenant = $appCode->tenant;

        if (! $tenant || ! $tenant->isActive()) {
            return [
                'valid' => false,
                'error_code' => 'TENANT_INACTIVE',
                'error' => __('mobile_api::mobile.tenant.tenant_inactive'),
            ];
        }

        // Increment usage count
        $appCode->incrementUsage();

        return [
            'valid' => true,
            'tenant' => $this->formatTenantResponse($tenant),
        ];
    }

    /**
     * Validate a tenant by slug.
     */
    public function validateBySlug(string $slug): ?array
    {
        $tenant = Tenant::where('slug', $slug)->first();

        if (! $tenant) {
            return null;
        }

        if (! $tenant->isActive()) {
            return [
                'valid' => false,
                'error' => __('mobile_api::mobile.tenant.tenant_inactive'),
            ];
        }

        return [
            'valid' => true,
            'tenant' => $this->formatTenantResponse($tenant),
        ];
    }

    /**
     * Lookup a tenant by domain.
     */
    public function lookupByDomain(string $domain): ?array
    {
        // Check tenant_domains table first
        $tenantDomain = DB::table('tenant_domains')
            ->where('domain', $domain)
            ->where('is_verified', true)
            ->first();

        if ($tenantDomain) {
            $tenant = Tenant::find($tenantDomain->tenant_id);
        } else {
            // Fall back to direct domain lookup on tenants table
            $tenant = Tenant::where('domain', $domain)->first();
        }

        if (! $tenant) {
            return null;
        }

        if (! $tenant->isActive()) {
            return [
                'valid' => false,
                'error' => __('mobile_api::mobile.tenant.tenant_inactive'),
            ];
        }

        return [
            'valid' => true,
            'tenant' => $this->formatTenantResponse($tenant),
        ];
    }

    /**
     * Format tenant data for API response.
     */
    protected function formatTenantResponse(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'domain' => $tenant->domain,
            'logo_url' => $tenant->getLogoUrl(),
            'primary_color' => $tenant->primary_color,
            'secondary_color' => $tenant->secondary_color,
            'country' => $tenant->country,
            'currency' => $tenant->currency,
            'timezone' => $tenant->timezone,

            // Status fields the mobile splash screen branches on before login.
            // The same kill-switch is enforced by ResolveTenantFromHeader on
            // every subsequent call (HTTP 403 + error_code: MOBILE_APP_SUSPENDED)
            // — these surfaced flags just let the client render a tailored
            // "service unavailable" screen instead of getting that error on
            // the next request after the user enters their clinic code.
            'is_active' => $tenant->isActive(),
            'mobile_app_enabled' => $tenant->isMobileAppEnabled(),
        ];
    }

    /**
     * Get the appropriate error message for an invalid code.
     */
    protected function getCodeErrorMessage(TenantAppCode $appCode): string
    {
        return $this->getCodeErrorData($appCode)['message'];
    }

    /**
     * Get error code and message for an invalid app code.
     */
    protected function getCodeErrorData(TenantAppCode $appCode): array
    {
        if ($appCode->isExpired()) {
            return [
                'code' => 'CODE_EXPIRED',
                'message' => __('mobile_api::mobile.tenant.code_expired'),
            ];
        }

        if ($appCode->isMaxedOut()) {
            return [
                'code' => 'CODE_USAGE_EXCEEDED',
                'message' => __('mobile_api::mobile.tenant.code_usage_exceeded'),
            ];
        }

        if (! $appCode->is_active) {
            return [
                'code' => 'CODE_INACTIVE',
                'message' => __('mobile_api::mobile.tenant.invalid_code'),
            ];
        }

        return [
            'code' => 'INVALID_CODE',
            'message' => __('mobile_api::mobile.tenant.invalid_code'),
        ];
    }

    /**
     * Create a new app code for a tenant.
     */
    public function createAppCode(
        Tenant $tenant,
        string $type = 'default',
        ?int $maxUses = null,
        ?\DateTimeInterface $expiresAt = null,
        ?int $createdBy = null
    ): TenantAppCode {
        return TenantAppCode::create([
            'tenant_id' => $tenant->id,
            'type' => $type,
            'max_uses' => $maxUses,
            'expires_at' => $expiresAt,
            'created_by' => $createdBy,
            'is_active' => true,
        ]);
    }

    /**
     * Get or create the default app code for a tenant.
     */
    public function getOrCreateDefaultAppCode(Tenant $tenant): TenantAppCode
    {
        return $tenant->getOrCreatePrimaryAppCode();
    }
}
