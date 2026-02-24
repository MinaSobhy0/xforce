<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Modules\Core\Models\Tenant;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    /**
     * Subdomains that should not be treated as tenant subdomains.
     */
    protected array $excludedSubdomains = [
        'sys',
        'www',
        'api',
        'admin',
        'app',
        'mail',
        'smtp',
        'ftp',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = $this->extractSubdomain($request);

        // If no subdomain (e.g., IP access), try to get tenant from session or query param
        if (!$subdomain || in_array($subdomain, $this->excludedSubdomains)) {
            // Check for tenant in query parameter (for development)
            if ($tenantSlug = $request->query('_tenant')) {
                session(['_tenant_slug' => $tenantSlug]);
            }

            // Try to get tenant from session
            $sessionTenantSlug = session('_tenant_slug');

            if ($sessionTenantSlug) {
                // Ensure we query the public schema for tenants table
                Config::set('database.connections.pgsql.search_path', 'public');
                DB::purge('pgsql');
                DB::reconnect('pgsql');

                $tenant = Tenant::where('slug', $sessionTenantSlug)
                    ->where('status', 'active')
                    ->first();

                if ($tenant && $tenant->database_name && $this->schemaExists($tenant->database_name)) {
                    $this->switchToTenantSchema($tenant);
                    $request->attributes->set('tenant', $tenant);
                    app()->instance('currentTenant', $tenant);

                    $tenantManager = app(\XLinic\Framework\Core\Tenancy\TenantManager::class);
                    $tenantManager->setCurrentTenant($tenant);

                    return $next($request);
                }
            }

            return $next($request);
        }

        // Ensure we query the public schema for tenants table
        Config::set('database.connections.pgsql.search_path', 'public');
        DB::purge('pgsql');
        DB::reconnect('pgsql');

        // Find tenant by slug (subdomain)
        $tenant = Tenant::where('slug', $subdomain)
            ->where('status', 'active')
            ->first();

        if (!$tenant) {
            abort(404, "Clinic not found: {$subdomain}");
        }

        // Check if tenant schema is provisioned
        if (!$tenant->database_name || !$this->schemaExists($tenant->database_name)) {
            abort(503, "Clinic database not provisioned. Please contact support.");
        }

        // Switch to tenant's PostgreSQL schema
        $this->switchToTenantSchema($tenant);

        // Store tenant in request and TenantManager for later use
        $request->attributes->set('tenant', $tenant);
        app()->instance('currentTenant', $tenant);

        // Set tenant in TenantManager (used by HasTenancy trait)
        $tenantManager = app(\XLinic\Framework\Core\Tenancy\TenantManager::class);
        $tenantManager->setCurrentTenant($tenant);

        $response = $next($request);

        return $response;
    }

    /**
     * Extract subdomain from the request host.
     */
    protected function extractSubdomain(Request $request): ?string
    {
        $host = $request->getHost();
        $parts = explode('.', $host);

        // Need at least 3 parts for subdomain (tenant.x-linic.com)
        if (count($parts) < 3) {
            return null;
        }

        $subdomain = $parts[0];

        // Validate subdomain format
        if (!preg_match('/^[a-z0-9\-]+$/', $subdomain)) {
            return null;
        }

        return $subdomain;
    }

    /**
     * Check if PostgreSQL schema exists.
     */
    protected function schemaExists(string $schemaName): bool
    {
        try {
            $result = DB::select(
                "SELECT schema_name FROM information_schema.schemata WHERE schema_name = ?",
                [$schemaName]
            );
            return count($result) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Switch database connection to tenant's PostgreSQL schema.
     */
    protected function switchToTenantSchema(Tenant $tenant): void
    {
        $schemaName = $tenant->database_name;

        // Switching to tenant schema

        // Configure the default pgsql connection to use tenant's schema
        // This persists across the request even with PgBouncer
        Config::set('database.connections.pgsql.search_path', $schemaName);

        // Purge and reconnect the default connection with new search_path
        DB::purge('pgsql');
        DB::reconnect('pgsql');

        // Verify the search_path is set correctly (silent verification)
        DB::select('SHOW search_path');

        // Also configure a named tenant connection for explicit use
        Config::set('database.connections.tenant', [
            'driver' => 'pgsql',
            'host' => config('database.connections.pgsql.host'),
            'port' => config('database.connections.pgsql.port'),
            'database' => config('database.connections.pgsql.database'),
            'username' => config('database.connections.pgsql.username'),
            'password' => config('database.connections.pgsql.password'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => $schemaName,
            'sslmode' => 'prefer',
        ]);

        // Purge any cached tenant connection
        DB::purge('tenant');
    }
}
