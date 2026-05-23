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

        // Mobile-app-specific kill switch. The tenant can be fully active
        // but the platform admin can still freeze just the mobile surface
        // (toggled from the SuperAdmin tenant view).
        if (! $tenant->isMobileAppEnabled()) {
            return response()->json([
                'success' => false,
                'message' => __('mobile_api::mobile.tenant.mobile_app_suspended'),
                'error_code' => 'MOBILE_APP_SUSPENDED',
            ], 403);
        }

        // SECURITY: Validate schema name before using in SQL to prevent injection
        $schemaName = $tenant->database_name;
        if (!$this->validateSchemaName($schemaName)) {
            \Illuminate\Support\Facades\Log::warning('Invalid tenant schema name', [
                'tenant_id' => $tenant->id,
                'schema_name' => $schemaName,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid tenant configuration',
            ], 500);
        }

        // SECURITY: Use quoted identifier to prevent SQL injection
        $quotedSchema = '"' . str_replace('"', '""', $schemaName) . '"';

        // Set the search path for the tenant schema on BOTH connections
        // Note: PersonalAccessToken uses fully qualified table name (public.personal_access_tokens)
        // so it works correctly even when search_path is set to tenant schema
        try {
            \DB::statement("SET search_path TO {$quotedSchema}");
            \DB::connection('tenant')->statement("SET search_path TO {$quotedSchema}");
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

    /**
     * SECURITY: Validate schema name to prevent SQL injection.
     */
    protected function validateSchemaName(string $schemaName): bool
    {
        // Schema names must be lowercase alphanumeric with underscores
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $schemaName)) {
            return false;
        }

        // Max length for PostgreSQL identifiers
        if (strlen($schemaName) > 63) {
            return false;
        }

        // Reserved schema names that shouldn't be used as tenant schemas
        $reserved = ['public', 'pg_catalog', 'information_schema', 'pg_toast', 'pg_temp'];
        if (in_array(strtolower($schemaName), $reserved)) {
            return false;
        }

        return true;
    }
}
