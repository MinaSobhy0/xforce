<?php

namespace XLinic\Framework\Core\Module;

use Illuminate\Support\Facades\File;
use Modules\Core\Models\TenantModule;
use XLinic\Framework\Core\Tenancy\TenantManager;

class ModuleManager
{
    protected array $discoveredModules = [];
    protected bool $booted = false;

    /**
     * Track modules being activated in current chain to prevent infinite loops.
     */
    protected array $activatingModules = [];

    public function __construct(
        protected ModuleRegistry $registry,
        protected DependencyResolver $dependencyResolver,
        protected TenantManager $tenantManager
    ) {
    }

    /**
     * Discover modules from the modules directory.
     * Supports both legacy *Manifest.php files and new module.json format.
     */
    public function discoverModules(string $path): void
    {
        if (!File::isDirectory($path)) {
            return;
        }

        $directories = File::directories($path);

        foreach ($directories as $moduleDirectory) {
            $moduleName = basename($moduleDirectory);

            // Try legacy Manifest.php first
            $manifestPath = $moduleDirectory . '/' . $moduleName . 'Manifest.php';
            if (File::exists($manifestPath)) {
                $manifestClass = "Modules\\{$moduleName}\\{$moduleName}Manifest";
                if (class_exists($manifestClass)) {
                    $manifest = new $manifestClass();
                    $this->discoveredModules[$manifest->code] = $manifest;
                    continue;
                }
            }

            // Try module.json format
            $jsonPath = $moduleDirectory . '/module.json';
            if (File::exists($jsonPath)) {
                $config = json_decode(File::get($jsonPath), true);
                if ($config) {
                    $manifest = new JsonModuleManifest($config, $moduleDirectory);
                    $this->discoveredModules[$manifest->code] = $manifest;
                }
            }
        }
    }

    /**
     * Boot all modules.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        // Discover modules
        $this->discoverModules(base_path('modules'));

        // Resolve dependencies and boot in correct order
        $orderedModules = $this->dependencyResolver->resolve($this->discoveredModules);

        foreach ($orderedModules as $manifest) {
            $this->registry->register($manifest);

            if ($this->registry->isActive($manifest->code)) {
                $this->bootModule($manifest);
            }
        }

        $this->booted = true;
    }

    /**
     * Boot a specific module.
     */
    public function bootModule(ModuleManifest $manifest): void
    {
        // Handle JsonModuleManifest - register all providers
        if ($manifest instanceof JsonModuleManifest) {
            foreach ($manifest->getServiceProviders() as $providerClass) {
                if (class_exists($providerClass)) {
                    app()->register($providerClass);
                }
            }
        } else {
            // Register single service provider for legacy manifests
            $serviceProviderClass = $manifest->getServiceProviderClass();
            if (class_exists($serviceProviderClass)) {
                app()->register($serviceProviderClass);
            }
        }

        // Register models
        foreach ($manifest->models as $modelClass) {
            if (class_exists($modelClass)) {
                app(\XLinic\Framework\Core\Model\ModelRegistry::class)->registerModel($modelClass);
            }
        }

        // Register extensions
        foreach ($manifest->extensions as $type => $extensions) {
            foreach ($extensions as $target => $extensionClass) {
                if (class_exists($extensionClass)) {
                    app(\XLinic\Framework\Core\View\ViewExtensionManager::class)
                        ->registerExtension($type, $target, $extensionClass);
                }
            }
        }

        // Only register permissions/navigation/settings for legacy manifests
        // JsonModuleManifest handles these differently (stored in module.json)
        if (!$manifest instanceof JsonModuleManifest) {
            // Register permissions
            app(\XLinic\Framework\Core\Security\PermissionRegistry::class)
                ->registerFromManifest($manifest);

            // Register navigation items
            app(\XLinic\Framework\Core\Navigation\NavigationRegistry::class)
                ->registerFromManifest($manifest);

            // Register settings
            app(\XLinic\Framework\Core\Settings\SettingsRegistry::class)
                ->registerFromManifest($manifest);
        }
    }

    /**
     * Activate a module for the current tenant.
     */
    public function activateModule(string $code): bool
    {
        $manifest = $this->registry->get($code);

        if (!$manifest) {
            throw new \InvalidArgumentException("Module not found: {$code}");
        }

        // Already active, nothing to do
        if ($this->registry->isActive($code)) {
            return true;
        }

        // Check plan allows module
        if (!$this->registry->isAllowed($code)) {
            return false;
        }

        // Store activation in database
        $this->storeModuleActivation($code, true);

        // Clear cache
        $this->registry->clearCache();

        // Boot the module if not already booted
        $this->bootModule($manifest);

        return true;
    }

    /**
     * Activate a module along with all its dependencies.
     * Dependencies are activated recursively before the main module.
     *
     * @param string $code Module code to activate
     * @param string|null $userId User ID performing the activation
     * @return array Array of module codes that were activated
     * @throws \InvalidArgumentException If module not found
     * @throws \RuntimeException If circular dependency detected
     */
    public function activateWithDependencies(string $code, ?string $userId = null): array
    {
        $code = strtolower($code);
        $activatedModules = [];

        // Prevent circular dependency loops
        if (in_array($code, $this->activatingModules)) {
            throw new \RuntimeException("Circular dependency detected while activating module: {$code}");
        }

        $manifest = $this->registry->get($code);

        if (!$manifest) {
            throw new \InvalidArgumentException("Module not found: {$code}");
        }

        // Already active, nothing to do
        if ($this->registry->isActive($code)) {
            return $activatedModules;
        }

        // Check plan allows module
        if (!$this->registry->isAllowed($code)) {
            return $activatedModules;
        }

        // Mark as being activated to prevent loops
        $this->activatingModules[] = $code;

        try {
            // First, recursively activate all dependencies
            foreach ($manifest->dependencies as $dependency) {
                $dependency = strtolower($dependency);
                $dependencyManifest = $this->registry->get($dependency);

                if (!$dependencyManifest) {
                    continue; // Skip missing dependencies
                }

                if (!$this->registry->isActive($dependency)) {
                    // Recursively activate the dependency
                    $dependencyActivated = $this->activateWithDependencies($dependency, $userId);
                    $activatedModules = array_merge($activatedModules, $dependencyActivated);
                }
            }

            // Now activate the main module
            $this->storeModuleActivation($code, true, $userId);
            $activatedModules[] = $code;

            // Clear cache after all activations
            $this->registry->clearCache();

            // Boot the module
            $this->bootModule($manifest);

        } finally {
            // Remove from activating list
            $this->activatingModules = array_filter(
                $this->activatingModules,
                fn($m) => $m !== $code
            );
        }

        return $activatedModules;
    }

    /**
     * Get all dependencies for a module (recursively).
     *
     * @param string $code Module code
     * @return array Array of dependency module codes
     */
    public function getAllDependencies(string $code): array
    {
        $code = strtolower($code);
        $manifest = $this->registry->get($code);

        if (!$manifest) {
            return [];
        }

        $dependencies = [];
        $visited = [];

        $this->collectDependencies($manifest, $dependencies, $visited);

        return array_unique($dependencies);
    }

    /**
     * Recursively collect all dependencies for a module.
     */
    protected function collectDependencies(ModuleManifest $manifest, array &$dependencies, array &$visited): void
    {
        foreach ($manifest->dependencies as $depCode) {
            $depCode = strtolower($depCode);

            if (in_array($depCode, $visited)) {
                continue;
            }

            $visited[] = $depCode;
            $dependencies[] = $depCode;

            $depManifest = $this->registry->get($depCode);
            if ($depManifest) {
                $this->collectDependencies($depManifest, $dependencies, $visited);
            }
        }
    }

    /**
     * Get modules that will be automatically enabled when enabling a module.
     * Returns only the dependencies that are currently inactive.
     *
     * @param string $code Module code
     * @return array Array of inactive dependency module codes that will be enabled
     */
    public function getInactiveDependencies(string $code): array
    {
        $allDeps = $this->getAllDependencies($code);

        return array_filter($allDeps, fn($dep) => !$this->registry->isActive($dep));
    }

    /**
     * Deactivate a module for the current tenant.
     */
    public function deactivateModule(string $code): bool
    {
        $manifest = $this->registry->get($code);

        if (!$manifest) {
            throw new \InvalidArgumentException("Module not found: {$code}");
        }

        if (!$this->canDeactivate($code)) {
            return false;
        }

        // Store deactivation in database
        $this->storeModuleActivation($code, false);

        // Clear cache
        $this->registry->clearCache();

        return true;
    }

    /**
     * Check if a module can be activated.
     */
    public function canActivate(string $code): bool
    {
        $manifest = $this->registry->get($code);

        if (!$manifest || $this->registry->isActive($code)) {
            return false;
        }

        // Check plan allows module
        if (!$this->registry->isAllowed($code)) {
            return false;
        }

        // Check all dependencies are active
        foreach ($manifest->dependencies as $dependency) {
            if (!$this->registry->isActive($dependency)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a module can be deactivated.
     */
    public function canDeactivate(string $code): bool
    {
        $manifest = $this->registry->get($code);

        if (!$manifest || !$this->registry->isActive($code)) {
            return false;
        }

        // Core modules cannot be deactivated
        if ($manifest->isCore()) {
            return false;
        }

        // Check no active modules depend on this one
        foreach ($this->registry->getAll() as $otherCode => $otherManifest) {
            if ($this->registry->isActive($otherCode) &&
                in_array($code, $otherManifest->dependencies)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Store module activation state in database.
     *
     * @param string $code Module code
     * @param bool $active Activation state
     * @param string|null $userId User ID performing the action
     */
    protected function storeModuleActivation(string $code, bool $active, ?string $userId = null): void
    {
        $tenant = $this->tenantManager->current();

        if (!$tenant) {
            // No tenant context, can't store activation
            return;
        }

        $tenantModule = TenantModule::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('module_code', strtolower($code))
            ->first();

        if ($tenantModule) {
            if ($active) {
                $tenantModule->activate($userId);
            } else {
                $tenantModule->deactivate();
            }
        } else {
            // Create new tenant module record
            TenantModule::create([
                'tenant_id' => $tenant->id,
                'module_code' => strtolower($code),
                'is_active' => $active,
                'activated_at' => $active ? now() : null,
                'activated_by' => $active ? $userId : null,
            ]);
        }
    }

    /**
     * Get all registered modules (discovered from disk).
     */
    public function getDiscoveredModules(): array
    {
        return $this->discoveredModules;
    }
}