<?php

namespace XLinic\Framework\Core\Module;

/**
 * Module manifest created from module.json file.
 * This allows modules to be defined via JSON configuration
 * instead of PHP Manifest classes.
 */
class JsonModuleManifest extends ModuleManifest
{
    protected string $modulePath;
    protected array $providers = [];

    /**
     * Roles defined by this module.
     */
    public array $roles = [];

    public function __construct(array $config, string $modulePath)
    {
        $this->modulePath = $modulePath;

        // Map module.json fields to manifest properties
        // Support both "code" and "alias" for backwards compatibility
        // Normalize to lowercase for consistent dependency resolution
        $this->code = strtolower($config['code'] ?? $config['alias'] ?? $config['name'] ?? basename($modulePath));
        $this->name = [
            'en' => $config['name'] ?? basename($modulePath),
            'ar' => $config['name'] ?? basename($modulePath),
        ];
        $this->description = [
            'en' => $config['description'] ?? '',
            'ar' => $config['description'] ?? '',
        ];
        $this->version = $config['version'] ?? '1.0.0';
        // Normalize dependencies to lowercase
        $this->dependencies = array_map('strtolower', $config['dependencies'] ?? []);
        $this->providers = $config['providers'] ?? [];

        // Additional properties from module.json
        $this->permissions = $config['permissions'] ?? [];
        $this->roles = $config['roles'] ?? [];
        $this->navigation = $config['navigation'] ?? [];
        $this->settings = $config['settings'] ?? [];

        // Set icon if provided
        if (isset($config['icon'])) {
            $this->icon = $config['icon'];
        }

        // Set category if provided
        if (isset($config['category'])) {
            $this->category = $config['category'];
        }
    }

    /**
     * Get the service provider class for this module.
     */
    public function getServiceProviderClass(): string
    {
        // Use the first provider from module.json
        if (!empty($this->providers)) {
            return $this->providers[0];
        }

        // Fallback to convention-based naming
        $moduleName = basename($this->modulePath);
        return "Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider";
    }

    /**
     * Get all service providers for this module.
     */
    public function getServiceProviders(): array
    {
        return $this->providers;
    }

    /**
     * Get the module path.
     */
    public function getModulePath(): string
    {
        return $this->modulePath;
    }

    /**
     * Check if this is a core module that cannot be deactivated.
     */
    public function isCore(): bool
    {
        return in_array($this->code, ['core', 'auth']);
    }

    /**
     * Check if this is a tenant module (vs platform module).
     */
    public function isTenantModule(): bool
    {
        return true; // All module.json modules are tenant modules
    }
}
