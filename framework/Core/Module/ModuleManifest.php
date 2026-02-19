<?php

namespace XLinic\Framework\Core\Module;

abstract class ModuleManifest
{
    /**
     * Unique module identifier.
     */
    public string $code;

    /**
     * Human-readable name (translatable).
     */
    public array $name = [];

    /**
     * Module description (translatable).
     */
    public array $description = [];

    /**
     * Module category.
     */
    public string $category = 'other';

    /**
     * Icon (Heroicon name).
     */
    public string $icon = 'heroicon-o-cube';

    /**
     * Version.
     */
    public string $version = '1.0.0';

    /**
     * Required dependencies.
     */
    public array $dependencies = [];

    /**
     * Optional dependencies.
     */
    public array $optionalDependencies = [];

    /**
     * Models registered by this module.
     */
    public array $models = [];

    /**
     * Extensions to other modules.
     */
    public array $extensions = [
        'model' => [],
        'form' => [],
        'table' => [],
        'dashboard' => [],
    ];

    /**
     * Permissions this module introduces.
     */
    public array $permissions = [];

    /**
     * Default permission assignments.
     */
    public array $defaultRolePermissions = [];

    /**
     * Record-level access policies.
     */
    public array $recordPolicies = [];

    /**
     * Navigation items this module adds.
     */
    public array $navigation = [];

    /**
     * Settings this module contributes.
     */
    public array $settings = [];

    /**
     * Auto-numbering sequences.
     */
    public array $sequences = [];

    /**
     * Scheduled actions.
     */
    public array $scheduledActions = [];

    /**
     * Events this module emits.
     */
    public array $events = [];

    /**
     * Event listeners.
     */
    public array $listeners = [];

    /**
     * Get the service provider class for this module.
     */
    public function getServiceProviderClass(): string
    {
        $className = str_replace('Manifest', 'ServiceProvider', static::class);
        return $className;
    }

    /**
     * Get the module path.
     */
    public function getModulePath(): string
    {
        $reflection = new \ReflectionClass($this);
        return dirname($reflection->getFileName());
    }

    /**
     * Check if this is a core module that cannot be deactivated.
     */
    public function isCore(): bool
    {
        return in_array($this->code, ['core', 'auth']);
    }

    /**
     * Get the module name in the current locale.
     */
    public function getName(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        return $this->name[$locale] ?? $this->name['en'] ?? $this->code;
    }

    /**
     * Get the module description in the current locale.
     */
    public function getDescription(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        return $this->description[$locale] ?? $this->description['en'] ?? '';
    }
}