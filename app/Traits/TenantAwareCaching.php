<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

/**
 * Trait TenantAwareCaching
 *
 * SECURITY: Provides tenant-aware caching methods to prevent cross-tenant
 * cache pollution. Use this trait when you need explicit control over
 * tenant-specific cache keys.
 */
trait TenantAwareCaching
{
    /**
     * Get a tenant-scoped cache key.
     * SECURITY: Always use this for tenant-specific cache operations.
     */
    protected function tenantCacheKey(string $key): string
    {
        $tenantId = $this->getCurrentTenantIdForCache();

        if ($tenantId === null) {
            // No tenant context - use a central prefix
            return 'central:' . $key;
        }

        return "tenant:{$tenantId}:{$key}";
    }

    /**
     * Get a value from tenant-scoped cache.
     */
    protected function tenantCacheGet(string $key, mixed $default = null): mixed
    {
        return Cache::get($this->tenantCacheKey($key), $default);
    }

    /**
     * Store a value in tenant-scoped cache.
     */
    protected function tenantCachePut(string $key, mixed $value, $ttl = null): bool
    {
        return Cache::put($this->tenantCacheKey($key), $value, $ttl);
    }

    /**
     * Remember a value in tenant-scoped cache.
     */
    protected function tenantCacheRemember(string $key, $ttl, \Closure $callback): mixed
    {
        return Cache::remember($this->tenantCacheKey($key), $ttl, $callback);
    }

    /**
     * Forget a value from tenant-scoped cache.
     */
    protected function tenantCacheForget(string $key): bool
    {
        return Cache::forget($this->tenantCacheKey($key));
    }

    /**
     * Clear all tenant-scoped cache (use with caution).
     * Note: This only works with cache drivers that support tags.
     */
    protected function tenantCacheFlush(): bool
    {
        $tenantId = $this->getCurrentTenantIdForCache();

        if ($tenantId === null) {
            return false;
        }

        // Try to use tags if supported
        try {
            Cache::tags(["tenant:{$tenantId}"])->flush();
            return true;
        } catch (\BadMethodCallException $e) {
            // Tags not supported by current cache driver
            // Cannot bulk flush, would need to track individual keys
            return false;
        }
    }

    /**
     * Get the current tenant ID for cache operations.
     */
    protected function getCurrentTenantIdForCache(): ?int
    {
        // Try TenantManager first
        if (app()->bound(\XLinic\Framework\Core\Tenancy\TenantManager::class)) {
            $tenantManager = app(\XLinic\Framework\Core\Tenancy\TenantManager::class);
            return $tenantManager->getCurrentTenantId();
        }

        // Try currentTenant instance
        if (app()->bound('currentTenant')) {
            $tenant = app('currentTenant');
            return $tenant?->id;
        }

        // Try from authenticated user
        if (auth()->check() && isset(auth()->user()->tenant_id)) {
            return auth()->user()->tenant_id;
        }

        return null;
    }
}
