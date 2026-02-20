<?php

namespace XLinic\Framework\Core\Module;

use Illuminate\Support\Facades\Cache;
use XLinic\Framework\Core\Tenancy\TenantManager;

class ModuleRegistry
{
    /**
     * All registered modules.
     *
     * @var array<string, ModuleManifest>
     */
    protected array $modules = [];

    /**
     * Cache of active modules per tenant.
     *
     * @var array<string, array<string>>
     */
    protected array $activeModulesCache = [];

    public function __construct(
        protected TenantManager $tenantManager
    ) {
    }

    /**
     * Register a module.
     */
    public function register(ModuleManifest $manifest): void
    {
        $this->modules[$manifest->code] = $manifest;
    }

    /**
     * Check if a module is active for the current tenant.
     */
    public function isActive(string $code): bool
    {
        // TODO: Implement proper tenant module activation from database
        // For now, all registered modules are considered active
        // This ensures translations and providers load correctly
        // When tenant_modules system is implemented, this will check:
        // 1. If module is core (always active)
        // 2. If module is explicitly activated for this tenant in database
        return isset($this->modules[$code]);
    }

    /**
     * Check if a module is allowed by the current tenant's plan.
     */
    public function isAllowed(string $code): bool
    {
        $tenant = $this->tenantManager->current();

        if (!$tenant) {
            return true; // System context allows all modules
        }

        // Core modules are always allowed
        if ($this->get($code)?->isCore()) {
            return true;
        }

        // Check subscription plan allows this module
        return Cache::tags(['tenant:' . $tenant->id, 'modules'])
            ->remember("module_allowed:{$tenant->id}:{$code}", 3600, function () use ($tenant, $code) {
                return $tenant->subscriptionPlan?->modules()->where('code', $code)->exists() ?? false;
            });
    }

    /**
     * Get all active module codes for the current tenant.
     */
    public function getActive(): array
    {
        $tenant = $this->tenantManager->current();
        $tenantId = $tenant?->id ?? 'system';

        if (!isset($this->activeModulesCache[$tenantId])) {
            $this->activeModulesCache[$tenantId] = $this->loadActiveModules($tenantId);
        }

        return $this->activeModulesCache[$tenantId];
    }

    /**
     * Get all registered modules.
     */
    public function getAll(): array
    {
        return $this->modules;
    }

    /**
     * Get a specific module manifest.
     */
    public function get(string $code): ?ModuleManifest
    {
        return $this->modules[$code] ?? null;
    }

    /**
     * Clear the active modules cache for a tenant.
     */
    public function clearCache(?string $tenantId = null): void
    {
        $tenantId = $tenantId ?? ($this->tenantManager->current()?->id ?? 'system');
        unset($this->activeModulesCache[$tenantId]);

        Cache::tags(['tenant:' . $tenantId, 'modules'])->flush();
    }

    /**
     * Load active modules from database/cache.
     */
    protected function loadActiveModules(string $tenantId): array
    {
        if ($tenantId === 'system') {
            // System context has all modules active
            return array_keys($this->modules);
        }

        return Cache::tags(['tenant:' . $tenantId, 'modules'])
            ->remember("active_modules:{$tenantId}", 3600, function () use ($tenantId) {
                // TODO: Query the tenant_modules table for active modules
                // For now, return all modules as active to ensure translations load
                // This will be replaced with proper database lookup when
                // tenant_modules activation system is implemented
                return array_keys($this->modules);
            });
    }
}