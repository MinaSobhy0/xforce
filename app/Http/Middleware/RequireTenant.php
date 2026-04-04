<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use XLinic\Framework\Core\Tenancy\TenantManager;

/**
 * Middleware to ensure a tenant is identified.
 *
 * Used by TenantPanelProvider to prevent access from sys subdomain
 * or any subdomain where a tenant was not found.
 */
class RequireTenant
{
    public function __construct(
        protected TenantManager $tenantManager
    ) {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenantManager->current();

        if (!$tenant) {
            // Extract subdomain for logging/error message
            $host = $request->getHost();
            $parts = explode('.', $host);
            $subdomain = count($parts) >= 3 ? $parts[0] : null;

            // If it's an excluded subdomain (sys, www, etc.), redirect to clinic owner portal
            $excludedSubdomains = ['sys', 'www', 'api', 'admin', 'app', 'mail', 'smtp', 'ftp'];

            if ($subdomain && in_array($subdomain, $excludedSubdomains)) {
                // Redirect to clinic owner portal
                return redirect('https://sys.xforcehr.com/admin');
            }

            // If accessing via IP (no subdomain), redirect to tenant selection or show helpful message
            if (!$subdomain) {
                // Check if there's a session tenant that failed to load
                $sessionTenantSlug = session('_tenant_slug');
                if ($sessionTenantSlug) {
                    // Clear invalid session and show error
                    session()->forget('_tenant_slug');
                }

                // If there's only one active tenant, auto-select it
                $activeTenants = \Modules\Core\Models\Tenant::where('status', \Modules\Core\Models\TenantStatus::ACTIVE)->get();
                if ($activeTenants->count() === 1) {
                    $singleTenant = $activeTenants->first();
                    session(['_tenant_slug' => $singleTenant->slug]);
                    return redirect($request->fullUrl());
                }

                // Multiple tenants or none - show selection page or error
                if ($activeTenants->isEmpty()) {
                    abort(503, 'No active clinics found.');
                }

                // Redirect to tenant selection with list
                return response()->view('errors.select-tenant', [
                    'tenants' => $activeTenants,
                    'currentUrl' => $request->fullUrl(),
                ], 200);
            }

            // Otherwise, tenant not found - show 404
            abort(404, "Clinic not found: {$subdomain}");
        }

        return $next($request);
    }
}
