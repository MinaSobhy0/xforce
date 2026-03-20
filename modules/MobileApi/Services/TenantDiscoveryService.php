<?php

namespace Modules\MobileApi\Services;

use App\Models\TenantAppCode;
use Modules\Core\Models\Tenant;
use Illuminate\Support\Facades\DB;

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

        if (!$appCode) {
            return null;
        }

        if (!$appCode->isValid()) {
            return [
                'valid' => false,
                'error' => $this->getCodeErrorMessage($appCode),
            ];
        }

        $tenant = $appCode->tenant;

        if (!$tenant || !$tenant->isActive()) {
            return [
                'valid' => false,
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

        if (!$tenant) {
            return null;
        }

        if (!$tenant->isActive()) {
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

        if (!$tenant) {
            return null;
        }

        if (!$tenant->isActive()) {
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
        ];
    }

    /**
     * Get the appropriate error message for an invalid code.
     */
    protected function getCodeErrorMessage(TenantAppCode $appCode): string
    {
        if ($appCode->isExpired()) {
            return __('mobile_api::mobile.tenant.code_expired');
        }

        if ($appCode->isMaxedOut()) {
            return __('mobile_api::mobile.tenant.code_usage_exceeded');
        }

        return __('mobile_api::mobile.tenant.invalid_code');
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
