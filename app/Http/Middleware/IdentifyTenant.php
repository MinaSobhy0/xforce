<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Tenant;
use Modules\Core\Models\TenantStatus;
use Spatie\Permission\PermissionRegistrar;
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
                    ->where('status', TenantStatus::ACTIVE)
                    ->first();

                if ($tenant && $tenant->database_name && $this->schemaExists($tenant->database_name)) {
                    $this->switchToTenantSchema($tenant);
                    $request->attributes->set('tenant', $tenant);
                    app()->instance('currentTenant', $tenant);

                    $tenantManager = app(\XLinic\Framework\Core\Tenancy\TenantManager::class);
                    $tenantManager->setCurrentTenant($tenant);

                    // Only clear cache if tenant changed (not on every request)
                    $this->clearPermissionCacheIfTenantChanged($tenant);

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
            ->where('status', TenantStatus::ACTIVE)
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

        // Only clear cache if tenant changed (not on every request)
        $this->clearPermissionCacheIfTenantChanged($tenant);

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

        // Configure the default pgsql connection to use tenant's schema
        // Using 'options' parameter which is passed to PostgreSQL as connection string options
        // Format: -c search_path=schema_name (this sets the search_path at connection time)
        Config::set('database.connections.pgsql.search_path', $schemaName);
        Config::set('database.connections.pgsql.options', [
            \PDO::ATTR_PERSISTENT => env('DB_PERSISTENT', false),
            \PDO::ATTR_EMULATE_PREPARES => env('DB_PGBOUNCER', true),
        ]);

        // Purge and reconnect the default connection with new search_path
        DB::purge('pgsql');
        DB::reconnect('pgsql');

        // With PgBouncer in transaction mode, we must SET search_path in each transaction
        // Use SET LOCAL to ensure it applies to the current transaction
        DB::statement("SET search_path TO \"{$schemaName}\"");

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
            // PgBouncer compatibility: emulate prepares to avoid "prepared statement does not exist" errors
            'options' => [
                \PDO::ATTR_PERSISTENT => env('DB_PERSISTENT', false),
                \PDO::ATTR_EMULATE_PREPARES => env('DB_PGBOUNCER', true),
            ],
        ]);

        // Purge any cached tenant connection
        DB::purge('tenant');

        // Store schema name in a global for use by callbacks and other code
        app()->instance('tenant_schema', $schemaName);

        // Configure tenant-specific file storage
        $this->configureTenantStorage($tenant->slug);
    }

    /**
     * Configure tenant-specific file storage disk.
     */
    protected function configureTenantStorage(string $tenantSlug): void
    {
        $tenantPath = storage_path('app/tenants/' . $tenantSlug);

        // Ensure the tenant directory exists
        if (!is_dir($tenantPath)) {
            mkdir($tenantPath, 0755, true);
        }

        // Build tenant URL using the tenant's subdomain
        // e.g., https://tenant-slug.x-linic.com/tenant-storage
        $baseHost = env('APP_DOMAIN', 'x-linic.com');
        $scheme = request()->secure() ? 'https' : 'http';
        $tenantUrl = "{$scheme}://{$tenantSlug}.{$baseHost}/tenant-storage";

        // Update the tenant disk configuration
        Config::set('filesystems.disks.tenant.root', $tenantPath);
        Config::set('filesystems.disks.tenant.url', $tenantUrl);

        // Purge the disk instance so it picks up the new config
        Storage::forgetDisk('tenant');
    }

    /**
     * Clear Spatie permission cache only if tenant has changed.
     *
     * Since permissions are stored per-tenant schema, we need to clear the cache
     * when switching tenants to ensure correct permissions are loaded.
     * We DON'T want to clear on every request as it breaks SPA navigation performance.
     */
    protected function clearPermissionCacheIfTenantChanged(Tenant $tenant): void
    {
        $currentTenantId = session('_permission_cache_tenant_id');

        // Only clear cache if this is a different tenant than before
        if ($currentTenantId !== $tenant->id) {
            $this->clearPermissionCache();
            session(['_permission_cache_tenant_id' => $tenant->id]);
        }
    }

    /**
     * Clear Spatie permission cache.
     *
     * Since permissions are stored per-tenant schema, we need to clear the cache
     * on each tenant switch to ensure correct permissions are loaded.
     */
    protected function clearPermissionCache(): void
    {
        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Exception $e) {
            // Silently ignore if PermissionRegistrar not available
        }
    }
}
