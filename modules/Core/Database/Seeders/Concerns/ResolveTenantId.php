<?php

namespace Modules\Core\Database\Seeders\Concerns;

use Illuminate\Support\Facades\DB;

trait ResolveTenantId
{
    /**
     * The resolved tenant ID.
     */
    protected ?string $resolvedTenantId = null;

    /**
     * Whether the search_path has been set for seeding.
     */
    protected bool $searchPathSet = false;

    /**
     * Resolve the tenant ID from various sources.
     * Works both when running through TenantService and manually via artisan.
     */
    protected function resolveTenantId(): ?string
    {
        if ($this->resolvedTenantId !== null) {
            return $this->resolvedTenantId;
        }

        // 1. Try TenantManager first (set by TenantService)
        try {
            $tenantManager = app(\XLinic\Framework\Core\Tenancy\TenantManager::class);
            if ($tenantManager->current()) {
                $this->resolvedTenantId = $tenantManager->current()->id;
                $this->searchPathSet = true; // TenantService already set it
                return $this->resolvedTenantId;
            }
        } catch (\Exception $e) {
            // Ignore - not bound or no current tenant
        }

        // 2. Try currentTenant binding (set by TenantService)
        try {
            if ($tenant = app('currentTenant')) {
                $this->resolvedTenantId = $tenant->id;
                $this->searchPathSet = true; // TenantService already set it
                return $this->resolvedTenantId;
            }
        } catch (\Exception $e) {
            // Ignore - not bound
        }

        // 3. Try to resolve from the database search_path
        // This works when running manually with --database=tenant after setting search_path
        foreach (['tenant', 'pgsql'] as $conn) {
            try {
                $result = DB::connection($conn)->select('SHOW search_path');
                $searchPath = $result[0]->search_path ?? 'public';

                // Skip if search_path is just 'public'
                if ($searchPath === 'public' || $searchPath === '"public"') {
                    continue;
                }

                // Match tenant schema patterns: tenant_slug, tenant-slug, "tenant_slug"
                if (preg_match('/^"?([^",\s]+)"?/', $searchPath, $schemaMatch)) {
                    $schemaName = $schemaMatch[1]; // e.g., 'tenant_jon'

                    // First, try to find by database_name (most reliable)
                    $sql = "SELECT id FROM public.tenants WHERE database_name = ? LIMIT 1";
                    $tenantResult = DB::connection('pgsql')->select($sql, [$schemaName]);

                    if (!empty($tenantResult)) {
                        $this->resolvedTenantId = $tenantResult[0]->id;
                        $this->searchPathSet = true; // Already set
                        return $this->resolvedTenantId;
                    }

                    // Fallback: try to extract slug from schema name
                    if (preg_match('/tenant[_-](.+)/', $schemaName, $slugMatch)) {
                        $schemaSlug = $slugMatch[1]; // e.g., 'jon' from 'tenant_jon'

                        $sql = "SELECT id FROM public.tenants WHERE slug = ? OR slug = ? LIMIT 1";
                        $slugVariants = [
                            $schemaSlug,
                            str_replace('_', '-', $schemaSlug),
                        ];

                        $tenantResult = DB::connection('pgsql')->select($sql, $slugVariants);

                        if (!empty($tenantResult)) {
                            $this->resolvedTenantId = $tenantResult[0]->id;
                            $this->searchPathSet = true; // Already set
                            return $this->resolvedTenantId;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Continue to next connection
            }
        }

        // 4. Try to get tenant from environment (for testing/manual runs)
        if ($tenantId = env('TENANT_ID')) {
            // When running manually with TENANT_ID, we need to set the search_path
            $this->ensureSearchPathSet($tenantId);
            $this->resolvedTenantId = $tenantId;
            return $tenantId;
        }

        return null;
    }

    /**
     * Ensure the database search_path is set for the given tenant.
     */
    protected function ensureSearchPathSet(string $tenantId): void
    {
        if ($this->searchPathSet) {
            return;
        }

        // Look up the tenant's database_name
        $tenant = DB::connection('pgsql')
            ->table('public.tenants')
            ->where('id', $tenantId)
            ->first();

        if ($tenant && $tenant->database_name) {
            $schemaName = $tenant->database_name;

            // Set search_path on both pgsql and tenant connections
            DB::connection('pgsql')->statement("SET search_path TO \"{$schemaName}\"");

            try {
                DB::connection('tenant')->statement("SET search_path TO \"{$schemaName}\"");
            } catch (\Exception $e) {
                // Tenant connection might not be configured
            }

            $this->searchPathSet = true;
        }
    }

    /**
     * Resolve tenant ID or throw an exception if not found.
     * Use this when tenant_id is required.
     */
    protected function requireTenantId(): string
    {
        $tenantId = $this->resolveTenantId();

        if (!$tenantId) {
            throw new \RuntimeException(
                'Cannot resolve tenant ID. This seeder must be run through TenantService::runTenantSeeders() ' .
                'or with TENANT_ID environment variable set. ' .
                'Example: TENANT_ID=your-tenant-id php artisan db:seed --class=YourSeeder'
            );
        }

        return $tenantId;
    }
}
