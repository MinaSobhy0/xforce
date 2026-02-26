<?php

namespace App\Traits;

use Modules\Core\Models\Tenant;

/**
 * Trait for Filament resources to check tenant module access.
 *
 * Usage in your resource:
 *   use App\Traits\ChecksTenantModuleAccess;
 *
 *   class InvoiceResource extends Resource
 *   {
 *       use ChecksTenantModuleAccess;
 *
 *       protected static ?string $moduleCode = 'billing';
 *   }
 *
 * The resource will automatically hide from navigation and deny access
 * if the tenant doesn't have the module in their features.
 */
trait ChecksTenantModuleAccess
{
    /**
     * Check if the current user/tenant can access this resource.
     */
    public static function canAccess(): bool
    {
        // Check parent canAccess if exists
        if (method_exists(parent::class, 'canAccess') && !parent::canAccess()) {
            return false;
        }

        // Get the module code - must be defined in the resource
        $moduleCode = static::$moduleCode ?? null;

        // If no module code defined, allow access (core resources)
        if (!$moduleCode) {
            return true;
        }

        // Core modules are always accessible
        if (in_array($moduleCode, ['core', 'auth'])) {
            return true;
        }

        // Get current tenant
        $tenant = static::getCurrentTenant();

        if (!$tenant) {
            return false;
        }

        return static::tenantHasModuleAccess($tenant, $moduleCode);
    }

    /**
     * Get the current tenant.
     */
    protected static function getCurrentTenant(): ?Tenant
    {
        // Try from app container
        if (app()->has('currentTenant')) {
            return app('currentTenant');
        }

        // Try from request attributes
        $tenant = request()->attributes->get('tenant');
        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        // Try from session (only if numeric ID - INT primary keys)
        $tenantId = session('tenant_id');
        if ($tenantId && is_numeric($tenantId)) {
            return Tenant::find((int) $tenantId);
        }

        return null;
    }

    /**
     * Check if tenant has access to a module.
     *
     * The tenant's `features` array is the ONLY source of truth for module access.
     * Plan's included_module_codes are only used to initialize features, not for runtime checks.
     */
    protected static function tenantHasModuleAccess(Tenant $tenant, string $moduleCode): bool
    {
        // Features array is the authoritative source for module access
        return $tenant->hasFeature($moduleCode);
    }

    /**
     * Determine if this resource should be registered.
     * This prevents the resource from appearing in navigation entirely.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
