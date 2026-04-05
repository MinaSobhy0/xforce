<?php

namespace Modules\Website\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Tenant;
use Modules\Core\Models\TenantStatus;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check if tenant has Website module enabled and should render
 * their custom website instead of the platform landing page.
 */
class WebsiteModuleMiddleware
{
    /**
     * Subdomains that should not be treated as tenant subdomains.
     */
    protected array $excludedSubdomains = [];

    public function __construct()
    {
        $this->excludedSubdomains = config('website.excluded_subdomains', [
            'www',
            'sys',
            'api',
            'admin',
            'platform',
            'mail',
            'smtp',
            'ftp',
        ]);
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = $this->extractSubdomain($request);

        // If no subdomain or excluded subdomain, show platform landing
        if (!$subdomain || in_array($subdomain, $this->excludedSubdomains)) {
            $request->attributes->set('render_website', false);
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

        // No tenant found - show platform landing
        if (!$tenant) {
            $request->attributes->set('render_website', false);
            return $next($request);
        }

        // Check if tenant has Website module enabled
        if (!$this->tenantHasWebsiteModule($tenant)) {
            $request->attributes->set('render_website', false);
            return $next($request);
        }

        // Tenant has Website module enabled
        $request->attributes->set('render_website', true);
        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('website_tenant_slug', $subdomain);

        // Switch to tenant schema for loading website content
        $this->switchToTenantSchema($tenant);

        return $next($request);
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
     * Check if tenant has the Website module enabled.
     */
    protected function tenantHasWebsiteModule(Tenant $tenant): bool
    {
        // Check via tenant's features array (authoritative source)
        return $tenant->hasFeature('website');
    }

    /**
     * Switch to tenant's PostgreSQL schema for content access.
     */
    protected function switchToTenantSchema(Tenant $tenant): void
    {
        $schemaName = $tenant->database_name;

        if (!$schemaName || !$this->schemaExists($schemaName)) {
            return;
        }

        // Configure the default pgsql connection to use tenant's schema
        Config::set('database.connections.pgsql.search_path', $schemaName);
        DB::purge('pgsql');
        DB::reconnect('pgsql');

        // Set search_path
        $quotedSchema = '"' . str_replace('"', '""', $schemaName) . '"';
        DB::statement("SET search_path TO {$quotedSchema}");

        // Store tenant in app container
        app()->instance('currentTenant', $tenant);
        app()->instance('tenant_schema', $schemaName);
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
}
