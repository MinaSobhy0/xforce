<?php

namespace XLinic\Framework\Core\Module;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Models\Tenant;
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
        $code = strtolower($code);

        // Module must be registered
        if (!isset($this->modules[$code])) {
            return false;
        }

        // Core modules are always active
        $manifest = $this->modules[$code];
        if ($manifest->isCore()) {
            return true;
        }

        // Check if in active modules list
        $activeModules = $this->getActive();

        return in_array($code, $activeModules);
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

        // Get core modules (always active)
        $coreModules = collect($this->modules)
            ->filter(fn(ModuleManifest $m) => $m->isCore())
            ->keys()
            ->map(fn($code) => strtolower($code))
            ->all();

        return Cache::tags(['tenant:' . $tenantId, 'modules'])
            ->remember("active_modules:{$tenantId}", 3600, function () use ($tenantId, $coreModules) {
                // Source of truth: tenants.features JSONB. That's the
                // column the SuperAdmin "Manage Modules" UI writes to,
                // and every other module gate — Tenant::hasFeature(),
                // ChecksResourcePermissions, ChecksPageModuleAccess,
                // ChecksTenantModuleAccess — already reads from it.
                // The legacy tenant_modules table was the original
                // design but nothing populates it anymore, so reading
                // it here caused every isActive(...) check in this
                // registry to silently return false even for modules
                // the tenant clearly had enabled in the UI.
                $tenant = $this->tenantManager->current()
                    ?? Tenant::find($tenantId);

                $features = collect($tenant?->features ?? [])
                    ->map(fn ($code) => strtolower((string) $code))
                    ->all();

                return array_values(array_unique(array_merge($coreModules, $features)));
            });
    }
}