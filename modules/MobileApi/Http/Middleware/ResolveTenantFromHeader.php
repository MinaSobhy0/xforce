<?php

namespace Modules\MobileApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Models\Tenant;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantFromHeader
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantSlug = $request->header('X-Tenant-Slug');

        if (!$tenantSlug) {
            return response()->json([
                'success' => false,
                'message' => __('mobile_api::mobile.tenant.tenant_not_found'),
                'error' => 'X-Tenant-Slug header is required',
            ], 400);
        }

        $tenant = Tenant::where('slug', $tenantSlug)
            ->orWhere('domain', $tenantSlug)
            ->first();

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => __('mobile_api::mobile.tenant.tenant_not_found'),
            ], 404);
        }

        if (!$tenant->isActive()) {
            return response()->json([
                'success' => false,
                'message' => __('mobile_api::mobile.tenant.tenant_inactive'),
            ], 403);
        }

        // Set the search path for the tenant schema on BOTH connections
        // Note: PersonalAccessToken uses fully qualified table name (public.personal_access_tokens)
        // so it works correctly even when search_path is set to tenant schema
        try {
            \DB::statement("SET search_path TO \"{$tenant->database_name}\"");
            \DB::connection('tenant')->statement("SET search_path TO \"{$tenant->database_name}\"");
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant database not available',
            ], 503);
        }

        // Bind the tenant to the container
        app()->instance('currentTenant', $tenant);
        $request->attributes->set('tenant', $tenant);

        // Configure the tenant database connection
        config([
            'database.connections.tenant.search_path' => $tenant->database_name,
        ]);

        return $next($request);
    }

    /**
     * Handle tasks after the response has been sent.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Reset search path to public
        try {
            \DB::statement("SET search_path TO public");
        } catch (\Exception $e) {
            // Ignore errors on terminate
        }
    }
}
