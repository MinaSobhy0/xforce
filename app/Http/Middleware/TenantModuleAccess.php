<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Modules\Core\Models\Tenant;

/**
 * Middleware to check if tenant has access to specific modules.
 *
 * Usage in routes:
 *   ->middleware('tenant.module:billing')
 *   ->middleware('tenant.module:billing,inventory')
 *
 * Usage in Filament:
 *   protected static ?string $moduleCode = 'billing';
 */
class TenantModuleAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$modules  Module codes to check
     */
    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        // Skip check if no modules specified
        if (empty($modules)) {
            return $next($request);
        }

        // Get current tenant
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            // No tenant context - allow request (handled by other middleware)
            return $next($request);
        }

        // Check each required module
        foreach ($modules as $moduleCode) {
            if (!$this->tenantHasModuleAccess($tenant, $moduleCode)) {
                return $this->denyAccess($request, $moduleCode);
            }
        }

        return $next($request);
    }

    /**
     * Get the current tenant from context.
     */
    protected function getCurrentTenant(): ?Tenant
    {
        // Try getting from request attributes (set by IdentifyTenant middleware)
        $tenant = request()->attributes->get('tenant');

        if ($tenant) {
            return $tenant;
        }

        // Try getting from session (only if numeric ID - INT primary keys)
        $tenantId = session('tenant_id');
        if ($tenantId && is_numeric($tenantId)) {
            return Tenant::find((int) $tenantId);
        }

        // Try getting from authenticated user
        $user = auth()->user();
        if ($user && method_exists($user, 'tenant')) {
            return $user->tenant;
        }

        return null;
    }

    /**
     * Check if tenant has access to a module.
     *
     * The tenant's `features` array is the ONLY source of truth for module access.
     * Plan's included_module_codes are only used to initialize features, not for runtime checks.
     */
    protected function tenantHasModuleAccess(Tenant $tenant, string $moduleCode): bool
    {
        // Core modules are always accessible
        $coreModules = ['core', 'auth'];
        if (in_array($moduleCode, $coreModules)) {
            return true;
        }

        // Check if module is globally enabled (via nwidart/laravel-modules)
        if (!$this->isModuleEnabled($moduleCode)) {
            return false;
        }

        // Features array is the authoritative source for module access
        return $tenant->hasFeature($moduleCode);
    }

    /**
     * Check if module is enabled globally.
     */
    protected function isModuleEnabled(string $moduleCode): bool
    {
        if (class_exists(\Nwidart\Modules\Facades\Module::class)) {
            $module = \Nwidart\Modules\Facades\Module::find(ucfirst($moduleCode));
            return $module && $module->isEnabled();
        }

        // Fallback: check if module directory exists
        return is_dir(base_path("modules/" . ucfirst($moduleCode)));
    }

    /**
     * Check if tenant has module via addon subscription.
     */
    protected function hasModuleAddon(Tenant $tenant, string $moduleCode): bool
    {
        try {
            return $tenant->activeAddOns()
                ->where('type', 'module')
                ->where('code', $moduleCode)
                ->exists();
        } catch (\Exception $e) {
            // Table may not exist yet
            return false;
        }
    }

    /**
     * Deny access to the module.
     */
    protected function denyAccess(Request $request, string $moduleCode): Response
    {
        $moduleName = ucfirst(str_replace('_', ' ', $moduleCode));

        // API request - return JSON
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => __('You do not have access to the :module module. Please upgrade your subscription.', ['module' => $moduleName]),
                'error_code' => 'MODULE_ACCESS_DENIED',
                'module' => $moduleCode,
            ], 403);
        }

        // Web request - redirect with error
        return redirect()
            ->route('filament.tenant.pages.dashboard')
            ->with('error', __('You do not have access to the :module module. Please upgrade your subscription.', ['module' => $moduleName]));
    }
}
