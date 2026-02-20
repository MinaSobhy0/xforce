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
            $subdomain = count($parts) >= 3 ? $parts[0] : 'unknown';

            // If it's an excluded subdomain (sys, www, etc.), redirect to clinic owner portal
            $excludedSubdomains = ['sys', 'www', 'api', 'admin', 'app', 'mail', 'smtp', 'ftp'];

            if (in_array($subdomain, $excludedSubdomains)) {
                // Redirect to clinic owner portal
                return redirect('https://sys.x-linic.com/admin');
            }

            // Otherwise, tenant not found - show 404
            abort(404, "Clinic not found: {$subdomain}");
        }

        return $next($request);
    }
}
