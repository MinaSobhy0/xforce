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
        $tenant = $this->tenantManager->current();
        $tenantId = $tenant?->id ?? 'system';

        if (!isset($this->activeModulesCache[$tenantId])) {
            $this->activeModulesCache[$tenantId] = $this->loadActiveModules($tenantId);
        }

        return in_array($code, $this->activeModulesCache[$tenantId]);
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
                // This would query the tenant_modules table in tenant context
                // For now, return core modules as always active
                $coreModules = array_filter(
                    array_keys($this->modules),
                    fn($code) => $this->modules[$code]->isCore()
                );

                // Add modules that are activated for this tenant
                // This would be loaded from database in real implementation
                return $coreModules;
            });
    }
}