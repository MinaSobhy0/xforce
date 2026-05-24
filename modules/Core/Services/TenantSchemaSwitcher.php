<?php

namespace Modules\Core\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Tenant;
use Spatie\Permission\PermissionRegistrar;

/**
 * Switches the active database connection to a tenant's PostgreSQL schema and
 * wires the surrounding per-tenant context (TenantManager, permission cache
 * scope, tenant storage disk, currentTenant binding).
 *
 * Extracted from IdentifyTenant so that non-HTTP entry points — Meta webhooks,
 * queue jobs that receive a raw tenant id, console commands looping tenants —
 * can switch schema without duplicating the PgBouncer-safe ritual.
 *
 * IdentifyTenant continues to own its own copy for now; this service is the
 * shared path for everything else. A follow-up can dedupe once both have been
 * exercised in production.
 */
class TenantSchemaSwitcher
{
    public function switchTo(Tenant $tenant): void
    {
        $schemaName = (string) $tenant->database_name;

        if (! $this->validateSchemaName($schemaName)) {
            throw new \RuntimeException("Invalid schema name: {$schemaName}");
        }

        Config::set('database.connections.pgsql.search_path', $schemaName);
        Config::set('database.connections.pgsql.options', [
            \PDO::ATTR_PERSISTENT => env('DB_PERSISTENT', false),
            \PDO::ATTR_EMULATE_PREPARES => env('DB_PGBOUNCER', true),
        ]);

        DB::purge('pgsql');
        DB::reconnect('pgsql');

        $quotedSchema = $this->quoteIdentifier($schemaName);
        DB::statement("SET search_path TO {$quotedSchema}");

        $this->verifySearchPath($schemaName);

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
            'options' => [
                \PDO::ATTR_PERSISTENT => env('DB_PERSISTENT', false),
                \PDO::ATTR_EMULATE_PREPARES => env('DB_PGBOUNCER', true),
            ],
        ]);

        DB::purge('tenant');

        app()->instance('tenant_schema', $schemaName);
        app()->instance('currentTenant', $tenant);

        if (app()->bound(\XLinic\Framework\Core\Tenancy\TenantManager::class)) {
            app(\XLinic\Framework\Core\Tenancy\TenantManager::class)->setCurrentTenant($tenant);
        }

        $this->configureTenantStorage($tenant->slug);
        $this->scopePermissionCache($tenant);
    }

    public function schemaExists(string $schemaName): bool
    {
        try {
            $result = DB::connection('central')->select(
                'SELECT schema_name FROM information_schema.schemata WHERE schema_name = ?',
                [$schemaName]
            );

            return count($result) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function validateSchemaName(string $schemaName): bool
    {
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $schemaName)) {
            return false;
        }
        if (strlen($schemaName) > 63) {
            return false;
        }
        $reserved = ['public', 'pg_catalog', 'information_schema', 'pg_toast', 'pg_temp'];

        return ! in_array(strtolower($schemaName), $reserved, true);
    }

    protected function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    protected function verifySearchPath(string $expectedSchema): void
    {
        try {
            $result = DB::selectOne('SHOW search_path');
            $currentPath = $result->search_path ?? '';
            $first = str_replace('"', '', trim(explode(',', $currentPath)[0] ?? ''));

            if ($first !== $expectedSchema) {
                throw new \RuntimeException(
                    "search_path mismatch — expected '{$expectedSchema}', got '{$first}'. "
                    . 'PgBouncer may not be in session mode.'
                );
            }
        } catch (\Throwable $e) {
            Log::warning('tenant.schema_switch.verify_failed', [
                'expected_schema' => $expectedSchema,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function configureTenantStorage(string $tenantSlug): void
    {
        $tenantPath = storage_path('app/tenants/' . $tenantSlug);
        if (! is_dir($tenantPath)) {
            mkdir($tenantPath, 0755, true);
        }

        $baseHost = env('APP_DOMAIN', 'xforcehr.com');
        $scheme = request()?->secure() ? 'https' : 'http';
        $tenantUrl = "{$scheme}://{$tenantSlug}.{$baseHost}/tenant-storage";

        Config::set('filesystems.disks.tenant.root', $tenantPath);
        Config::set('filesystems.disks.tenant.url', $tenantUrl);

        Storage::forgetDisk('tenant');
    }

    protected function scopePermissionCache(Tenant $tenant): void
    {
        Config::set('permission.cache.key', 'spatie.permission.cache.tenant_' . $tenant->id);
        app(PermissionRegistrar::class)->initializeCache();

        $basePrefix = config('cache.prefix', 'xlinic_cache_');
        Config::set('cache.prefix', $basePrefix . 'tenant_' . $tenant->id . '_');

        $currentRedisPrefix = config('database.redis.options.prefix', 'xlinic_');
        Config::set('database.redis.options.prefix', $currentRedisPrefix . 'tenant_' . $tenant->id . '_');
    }
}
