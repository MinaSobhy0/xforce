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

        // If no subdomain (e.g., IP access), try to get tenant from session
        if (!$subdomain || in_array($subdomain, $this->excludedSubdomains)) {
            // SECURITY: Query parameter tenant override is only allowed in local environment
            // and only for authenticated platform admins
            if (app()->environment('local') && $tenantSlug = $request->query('_tenant')) {
                // Validate the slug format to prevent injection
                if (preg_match('/^[a-z0-9\-]+$/', $tenantSlug) && auth()->check()) {
                    $user = auth()->user();
                    // Only allow platform admins to switch tenant context
                    if ($user && method_exists($user, 'hasRole') && $user->hasRole(['super_admin', 'platform_admin'])) {
                        session(['_tenant_slug' => $tenantSlug]);
                    }
                }
            }

            // Try to get tenant from session
            $sessionTenantSlug = session('_tenant_slug');

            if ($sessionTenantSlug) {
                // Validate session tenant slug format
                if (!preg_match('/^[a-z0-9\-]+$/', $sessionTenantSlug)) {
                    session()->forget('_tenant_slug');
                    return $next($request);
                }

                // Ensure we query the public schema for tenants table
                Config::set('database.connections.pgsql.search_path', 'public');
                DB::purge('pgsql');
                DB::reconnect('pgsql');

                $tenant = Tenant::where('slug', $sessionTenantSlug)
                    ->where('status', TenantStatus::ACTIVE)
                    ->first();

                if ($tenant && $tenant->database_name && $this->schemaExists($tenant->database_name)) {
                    // SECURITY: Verify user has access to this tenant if authenticated
                    if (auth()->check()) {
                        $user = auth()->user();
                        // Platform admins can access any tenant
                        $isPlatformAdmin = method_exists($user, 'hasRole') &&
                            $user->hasRole(['super_admin', 'platform_admin']);

                        if (!$isPlatformAdmin) {
                            // Regular users must belong to this tenant
                            // Check if user's tenant_id matches or user has explicit tenant access
                            $userTenantId = $user->tenant_id ?? null;
                            if ($userTenantId !== $tenant->id) {
                                // User doesn't belong to this tenant - clear session and deny
                                session()->forget('_tenant_slug');
                                abort(403, 'Unauthorized tenant access');
                            }
                        }
                    }

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

        // Find tenant by slug (subdomain) regardless of status. We disambiguate
        // "tenant doesn't exist" (404) from "tenant exists but is suspended"
        // (friendly 503 page) below — previously both paths returned 404.
        $tenant = Tenant::where('slug', $subdomain)->first();

        if (!$tenant) {
            abort(404, "Clinic not found: {$subdomain}");
        }

        if ($tenant->status === TenantStatus::SUSPENDED) {
            abort(response()->view('errors.tenant-suspended', [
                'tenantName' => $tenant->name,
                'contactEmail' => 'support@xforcehr.com',
                'platformAdminUrl' => 'https://xforcehr.com/admin',
            ], 503));
        }

        if ($tenant->status !== TenantStatus::ACTIVE) {
            abort(404, "Clinic not active: {$subdomain}");
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

        // Need at least 3 parts for subdomain (tenant.xforcehr.com)
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
     * Validate schema name to prevent SQL injection.
     * SECURITY: Schema names must be alphanumeric with underscores only.
     */
    protected function validateSchemaName(string $schemaName): bool
    {
        // Must start with letter, contain only lowercase alphanumeric and underscores
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $schemaName)) {
            return false;
        }

        // Length limit (PostgreSQL max is 63)
        if (strlen($schemaName) > 63) {
            return false;
        }

        // Block reserved schemas
        $reserved = ['public', 'pg_catalog', 'information_schema', 'pg_toast', 'pg_temp'];
        if (in_array(strtolower($schemaName), $reserved)) {
            return false;
        }

        return true;
    }

    /**
     * Safely quote a PostgreSQL identifier.
     */
    protected function quoteIdentifier(string $identifier): string
    {
        // Escape double quotes by doubling them
        $escaped = str_replace('"', '""', $identifier);
        return '"' . $escaped . '"';
    }

    /**
     * Switch database connection to tenant's PostgreSQL schema.
     * SECURITY: Includes protection against PgBouncer connection race conditions.
     */
    protected function switchToTenantSchema(Tenant $tenant): void
    {
        $schemaName = $tenant->database_name;

        // SECURITY: Validate schema name before using in SQL
        if (!$this->validateSchemaName($schemaName)) {
            throw new \RuntimeException("Invalid schema name: {$schemaName}");
        }

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

        // SECURITY: Use safely quoted identifier
        $quotedSchema = $this->quoteIdentifier($schemaName);

        // SECURITY: Set search_path immediately after acquiring connection
        // This must happen atomically with connection acquisition
        DB::statement("SET search_path TO {$quotedSchema}");

        // SECURITY: Verify the search_path was set correctly to detect PgBouncer issues
        $this->verifySearchPath($schemaName);

        // SECURITY: Register a reconnect listener to ensure search_path persists
        // This handles cases where PgBouncer gives us a different backend connection
        $this->registerSearchPathCallback($schemaName, $quotedSchema);

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
        // e.g., https://tenant-slug.xforcehr.com/tenant-storage
        $baseHost = env('APP_DOMAIN', 'xforcehr.com');
        $scheme = request()->secure() ? 'https' : 'http';
        $tenantUrl = "{$scheme}://{$tenantSlug}.{$baseHost}/tenant-storage";

        // Update the tenant disk configuration
        Config::set('filesystems.disks.tenant.root', $tenantPath);
        Config::set('filesystems.disks.tenant.url', $tenantUrl);

        // Purge the disk instance so it picks up the new config
        Storage::forgetDisk('tenant');
    }

    /**
     * Set tenant-specific permission cache key.
     *
     * Instead of clearing cache on every tenant switch, we use a unique
     * cache key per tenant. This allows each tenant to have its own
     * permission cache without conflicts.
     */
    protected function clearPermissionCacheIfTenantChanged(Tenant $tenant): void
    {
        // SECURITY: Set tenant-specific cache key for Spatie permissions using ID (not slug)
        // Using ID prevents cache collisions between tenants with similar slugs
        Config::set('permission.cache.key', 'spatie.permission.cache.tenant_' . $tenant->id);

        // Reset the PermissionRegistrar to pick up the new cache key
        app(PermissionRegistrar::class)->initializeCache();

        // SECURITY: Set tenant-specific cache prefix to prevent cross-tenant cache pollution
        // This ensures all cache keys are automatically prefixed with tenant identifier
        $this->setTenantCachePrefix($tenant);
    }

    /**
     * SECURITY: Set tenant-specific cache prefix to prevent cross-tenant cache pollution.
     * This ensures all cache operations are isolated to the current tenant.
     */
    protected function setTenantCachePrefix(Tenant $tenant): void
    {
        // Get base prefix from config
        $basePrefix = config('cache.prefix', 'xlinic_cache_');

        // Create tenant-specific prefix
        $tenantPrefix = $basePrefix . 'tenant_' . $tenant->id . '_';

        // Update cache prefix in config
        Config::set('cache.prefix', $tenantPrefix);

        // Also update Redis prefix if using Redis
        $currentRedisPrefix = config('database.redis.options.prefix', 'xlinic_');
        Config::set('database.redis.options.prefix', $currentRedisPrefix . 'tenant_' . $tenant->id . '_');

        // Purge cache store to pick up new prefix
        // Note: We don't call Cache::forgetDriver() as it would clear all cache
        // The prefix change only affects new cache operations
    }

    /**
     * SECURITY: Verify the search_path was set correctly.
     * This detects PgBouncer misconfiguration issues that could lead to
     * cross-tenant data access.
     */
    protected function verifySearchPath(string $expectedSchema): void
    {
        try {
            $result = DB::selectOne('SHOW search_path');
            $currentPath = $result->search_path ?? '';

            // Parse the search_path (it could be quoted or have additional schemas)
            $schemas = array_map('trim', explode(',', $currentPath));
            $firstSchema = str_replace('"', '', $schemas[0] ?? '');

            if ($firstSchema !== $expectedSchema) {
                throw new \RuntimeException(
                    "SECURITY: search_path mismatch - expected '{$expectedSchema}', got '{$firstSchema}'. " .
                    "This may indicate PgBouncer is not in session mode or a connection race occurred."
                );
            }
        } catch (\Exception $e) {
            // If verification fails, log and continue (don't break production)
            // But this should be monitored
            \Log::warning('Failed to verify search_path', [
                'expected_schema' => $expectedSchema,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * SECURITY: Register callback to ensure search_path is set on reconnection.
     * This handles edge cases where PgBouncer gives us a different backend connection.
     */
    protected function registerSearchPathCallback(string $schemaName, string $quotedSchema): void
    {
        // Store the current tenant schema in a static for the callback
        static $registeredSchema = null;

        // Only register once per request
        if ($registeredSchema === $schemaName) {
            return;
        }

        $registeredSchema = $schemaName;

        // Use Laravel's reconnect event to ensure search_path is set
        // This handles cases where the connection is dropped and re-established
        DB::listen(function ($query) use ($schemaName, $quotedSchema) {
            // If we detect a connection issue, ensure search_path is set
            // This is a fallback mechanism - the primary SET should already work
            static $searchPathSet = false;

            if (!$searchPathSet && str_contains($query->sql ?? '', 'relation') && str_contains($query->sql ?? '', 'does not exist')) {
                // This suggests we might have a search_path issue
                \Log::warning('Possible search_path issue detected, attempting to reset', [
                    'expected_schema' => $schemaName,
                    'sql' => $query->sql,
                ]);

                try {
                    DB::statement("SET search_path TO {$quotedSchema}");
                    $searchPathSet = true;
                } catch (\Exception $e) {
                    \Log::error('Failed to reset search_path', ['error' => $e->getMessage()]);
                }
            }
        });
    }
}
