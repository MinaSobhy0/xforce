<?php

namespace XLinic\Framework\Core\Module;

use Illuminate\Support\Facades\File;
use XLinic\Framework\Core\Tenancy\TenantManager;

class ModuleManager
{
    protected array $discoveredModules = [];
    protected bool $booted = false;

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

        if (!$this->canActivate($code)) {
            return false;
        }

        // Store activation in database
        $this->storeModuleActivation($code, true);

        // Clear cache
        $this->registry->clearCache();

        // Boot the module if not already booted
        if (!$this->registry->isActive($code)) {
            $this->bootModule($manifest);
        }

        return true;
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
     */
    protected function storeModuleActivation(string $code, bool $active): void
    {
        // This would store in tenant_modules table in tenant context
        // For now, this is a placeholder
    }
}