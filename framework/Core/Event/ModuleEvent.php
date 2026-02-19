<?php

namespace XLinic\Framework\Core\Event;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use XLinic\Framework\Core\Tenancy\TenantManager;
use XLinic\Framework\Core\Module\ModuleManager;

/**
 * Base Module Event
 *
 * Abstract base class for all module-related events in the framework.
 * Provides common functionality for module lifecycle events, tenant
 * context, and module state management.
 *
 * @package XLinic\Framework\Core\Event
 */
abstract class ModuleEvent
{
    use Dispatchable, SerializesModels;

    /**
     * Module event types
     */
    public const TYPE_INSTALLING = 'installing';
    public const TYPE_INSTALLED = 'installed';
    public const TYPE_ENABLING = 'enabling';
    public const TYPE_ENABLED = 'enabled';
    public const TYPE_DISABLING = 'disabling';
    public const TYPE_DISABLED = 'disabled';
    public const TYPE_UNINSTALLING = 'uninstalling';
    public const TYPE_UNINSTALLED = 'uninstalled';
    public const TYPE_UPDATING = 'updating';
    public const TYPE_UPDATED = 'updated';
    public const TYPE_CONFIGURING = 'configuring';
    public const TYPE_CONFIGURED = 'configured';

    /**
     * The module identifier
     */
    public string $moduleId;

    /**
     * The module instance
     */
    public ?object $module = null;

    /**
     * The event type
     */
    public string $eventType;

    /**
     * The tenant context
     */
    public ?object $tenant = null;

    /**
     * The user who triggered the event
     */
    public ?object $user = null;

    /**
     * Event timestamp
     */
    public \DateTime $timestamp;

    /**
     * Additional event data
     */
    public array $data = [];

    /**
     * Module version information
     */
    public array $versionInfo = [];

    /**
     * Configuration changes (for config events)
     */
    public array $configChanges = [];

    /**
     * Event metadata
     */
    public array $metadata = [];

    /**
     * Create a new module event instance
     */
    public function __construct(
        string $moduleId,
        string $eventType,
        ?object $module = null,
        array $data = [],
        ?object $user = null,
        array $metadata = []
    ) {
        $this->moduleId = $moduleId;
        $this->eventType = $eventType;
        $this->module = $module;
        $this->data = $data;
        $this->user = $user ?: auth()->user();
        $this->metadata = $metadata;
        $this->timestamp = new \DateTime();

        // Capture tenant context
        $this->captureTenantContext();

        // Load module if not provided
        if (!$this->module) {
            $this->loadModule();
        }

        // Capture version information
        $this->captureVersionInfo();

        // Add default metadata
        $this->addDefaultMetadata();
    }

    /**
     * Get the module identifier
     */
    public function getModuleId(): string
    {
        return $this->moduleId;
    }

    /**
     * Get the module instance
     */
    public function getModule(): ?object
    {
        return $this->module;
    }

    /**
     * Get the event type
     */
    public function getEventType(): string
    {
        return $this->eventType;
    }

    /**
     * Get the tenant context
     */
    public function getTenant(): ?object
    {
        return $this->tenant;
    }

    /**
     * Get the user who triggered the event
     */
    public function getUser(): ?object
    {
        return $this->user;
    }

    /**
     * Get event timestamp
     */
    public function getTimestamp(): \DateTime
    {
        return $this->timestamp;
    }

    /**
     * Get event data
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get specific data value
     */
    public function getDataValue(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Set data value
     */
    public function setDataValue(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * Get version information
     */
    public function getVersionInfo(): array
    {
        return $this->versionInfo;
    }

    /**
     * Get current version
     */
    public function getCurrentVersion(): ?string
    {
        return $this->versionInfo['current'] ?? null;
    }

    /**
     * Get previous version
     */
    public function getPreviousVersion(): ?string
    {
        return $this->versionInfo['previous'] ?? null;
    }

    /**
     * Get target version
     */
    public function getTargetVersion(): ?string
    {
        return $this->versionInfo['target'] ?? null;
    }

    /**
     * Get configuration changes
     */
    public function getConfigChanges(): array
    {
        return $this->configChanges;
    }

    /**
     * Set configuration changes
     */
    public function setConfigChanges(array $changes): self
    {
        $this->configChanges = $changes;
        return $this;
    }

    /**
     * Get event metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get specific metadata value
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Set metadata value
     */
    public function setMetadataValue(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Add metadata
     */
    public function addMetadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }

    /**
     * Get module name
     */
    public function getModuleName(): string
    {
        return $this->module?->name ?? $this->moduleId;
    }

    /**
     * Get module version
     */
    public function getModuleVersion(): ?string
    {
        return $this->module?->version ?? null;
    }

    /**
     * Get module status
     */
    public function getModuleStatus(): ?string
    {
        return $this->module?->status ?? null;
    }

    /**
     * Check if this is an installation event
     */
    public function isInstallationEvent(): bool
    {
        return in_array($this->eventType, [self::TYPE_INSTALLING, self::TYPE_INSTALLED]);
    }

    /**
     * Check if this is an uninstallation event
     */
    public function isUninstallationEvent(): bool
    {
        return in_array($this->eventType, [self::TYPE_UNINSTALLING, self::TYPE_UNINSTALLED]);
    }

    /**
     * Check if this is an enable/disable event
     */
    public function isStateChangeEvent(): bool
    {
        return in_array($this->eventType, [
            self::TYPE_ENABLING,
            self::TYPE_ENABLED,
            self::TYPE_DISABLING,
            self::TYPE_DISABLED
        ]);
    }

    /**
     * Check if this is an update event
     */
    public function isUpdateEvent(): bool
    {
        return in_array($this->eventType, [self::TYPE_UPDATING, self::TYPE_UPDATED]);
    }

    /**
     * Check if this is a configuration event
     */
    public function isConfigurationEvent(): bool
    {
        return in_array($this->eventType, [self::TYPE_CONFIGURING, self::TYPE_CONFIGURED]);
    }

    /**
     * Check if this is a "before" event
     */
    public function isBeforeEvent(): bool
    {
        return in_array($this->eventType, [
            self::TYPE_INSTALLING,
            self::TYPE_ENABLING,
            self::TYPE_DISABLING,
            self::TYPE_UNINSTALLING,
            self::TYPE_UPDATING,
            self::TYPE_CONFIGURING
        ]);
    }

    /**
     * Check if this is an "after" event
     */
    public function isAfterEvent(): bool
    {
        return in_array($this->eventType, [
            self::TYPE_INSTALLED,
            self::TYPE_ENABLED,
            self::TYPE_DISABLED,
            self::TYPE_UNINSTALLED,
            self::TYPE_UPDATED,
            self::TYPE_CONFIGURED
        ]);
    }

    /**
     * Get event summary for logging
     */
    public function getSummary(): string
    {
        $moduleName = $this->getModuleName();
        $eventType = $this->getEventType();

        return "Module '{$moduleName}' {$eventType}";
    }

    /**
     * Get event context for logging
     */
    public function getContext(): array
    {
        return [
            'event_type' => $this->eventType,
            'module_id' => $this->moduleId,
            'module_name' => $this->getModuleName(),
            'module_version' => $this->getModuleVersion(),
            'module_status' => $this->getModuleStatus(),
            'tenant_id' => $this->tenant?->id,
            'user_id' => $this->user?->id,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'version_info' => $this->versionInfo,
            'config_changes' => $this->configChanges,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Convert event to array
     */
    public function toArray(): array
    {
        return [
            'event_type' => $this->eventType,
            'module' => [
                'id' => $this->moduleId,
                'name' => $this->getModuleName(),
                'version' => $this->getModuleVersion(),
                'status' => $this->getModuleStatus(),
            ],
            'tenant' => $this->tenant ? [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name ?? null,
            ] : null,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name ?? null,
                'email' => $this->user->email ?? null,
            ] : null,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'data' => $this->data,
            'version_info' => $this->versionInfo,
            'config_changes' => $this->configChanges,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Capture tenant context
     */
    protected function captureTenantContext(): void
    {
        try {
            $tenantManager = app(TenantManager::class);
            $this->tenant = $tenantManager->getCurrentTenant();
        } catch (\Exception $e) {
            // Tenant manager not available or no current tenant
            $this->tenant = null;
        }
    }

    /**
     * Load module instance
     */
    protected function loadModule(): void
    {
        try {
            $moduleManager = app(ModuleManager::class);
            $this->module = $moduleManager->getModule($this->moduleId);
        } catch (\Exception $e) {
            // Module manager not available or module not found
            $this->module = null;
        }
    }

    /**
     * Capture version information
     */
    protected function captureVersionInfo(): void
    {
        if ($this->module) {
            $this->versionInfo = [
                'current' => $this->module->version ?? null,
                'previous' => $this->getDataValue('previous_version'),
                'target' => $this->getDataValue('target_version'),
            ];
        }
    }

    /**
     * Add default metadata
     */
    protected function addDefaultMetadata(): void
    {
        $this->metadata = array_merge([
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'url' => request()?->fullUrl(),
            'method' => request()?->method(),
            'source' => 'module_event',
            'server_info' => [
                'php_version' => PHP_VERSION,
                'os' => PHP_OS,
                'timestamp' => time(),
            ],
        ], $this->metadata);
    }

    /**
     * Create event for module installing
     */
    public static function installing(string $moduleId, array $data = [], ?object $user = null, array $metadata = []): static
    {
        return new static($moduleId, self::TYPE_INSTALLING, null, $data, $user, $metadata);
    }

    /**
     * Create event for module installed
     */
    public static function installed(string $moduleId, ?object $module = null, array $data = [], ?object $user = null, array $metadata = []): static
    {
        return new static($moduleId, self::TYPE_INSTALLED, $module, $data, $user, $metadata);
    }

    /**
     * Create event for module enabling
     */
    public static function enabling(string $moduleId, ?object $module = null, array $data = [], ?object $user = null, array $metadata = []): static
    {
        return new static($moduleId, self::TYPE_ENABLING, $module, $data, $user, $metadata);
    }

    /**
     * Create event for module enabled
     */
    public static function enabled(string $moduleId, ?object $module = null, array $data = [], ?object $user = null, array $metadata = []): static
    {
        return new static($moduleId, self::TYPE_ENABLED, $module, $data, $user, $metadata);
    }

    /**
     * Create event for module disabling
     */
    public static function disabling(string $moduleId, ?object $module = null, array $data = [], ?object $user = null, array $metadata = []): static
    {
        return new static($moduleId, self::TYPE_DISABLING, $module, $data, $user, $metadata);
    }

    /**
     * Create event for module disabled
     */
    public static function disabled(string $moduleId, ?object $module = null, array $data = [], ?object $user = null, array $metadata = []): static
    {
        return new static($moduleId, self::TYPE_DISABLED, $module, $data, $user, $metadata);
    }

    /**
     * Create event for module uninstalling
     */
    public static function uninstalling(string $moduleId, ?object $module = null, array $data = [], ?object $user = null, array $metadata = []): static
    {
        return new static($moduleId, self::TYPE_UNINSTALLING, $module, $data, $user, $metadata);
    }

    /**
     * Create event for module uninstalled
     */
    public static function uninstalled(string $moduleId, array $data = [], ?object $user = null, array $metadata = []): static
    {
        return new static($moduleId, self::TYPE_UNINSTALLED, null, $data, $user, $metadata);
    }

    /**
     * Create event for module updating
     */
    public static function updating(string $moduleId, ?object $module = null, array $data = [], ?object $user = null, array $metadata = []): static
    {
        return new static($moduleId, self::TYPE_UPDATING, $module, $data, $user, $metadata);
    }

    /**
     * Create event for module updated
     */
    public static function updated(string $moduleId, ?object $module = null, array $data = [], ?object $user = null, array $metadata = []): static
    {
        return new static($moduleId, self::TYPE_UPDATED, $module, $data, $user, $metadata);
    }

    /**
     * Create event for module configuring
     */
    public static function configuring(string $moduleId, ?object $module = null, array $data = [], ?object $user = null, array $metadata = []): static
    {
        return new static($moduleId, self::TYPE_CONFIGURING, $module, $data, $user, $metadata);
    }

    /**
     * Create event for module configured
     */
    public static function configured(string $moduleId, ?object $module = null, array $data = [], ?object $user = null, array $metadata = []): static
    {
        return new static($moduleId, self::TYPE_CONFIGURED, $module, $data, $user, $metadata);
    }

    /**
     * Determine if the event should be broadcast
     */
    public function broadcastOn(): array
    {
        $channels = [];

        // Broadcast to tenant channel if tenant exists
        if ($this->tenant) {
            $channels[] = "tenant.{$this->tenant->id}.modules";
        }

        // Broadcast to user channel if user exists
        if ($this->user) {
            $channels[] = "user.{$this->user->id}.modules";
        }

        // Broadcast to module-specific channel
        $channels[] = "module.{$this->moduleId}";

        // Broadcast to global modules channel
        $channels[] = "modules";

        return $channels;
    }

    /**
     * Get the event name for broadcasting
     */
    public function broadcastAs(): string
    {
        return "Module{$this->eventType}";
    }

    /**
     * Get the data to broadcast
     */
    public function broadcastWith(): array
    {
        return [
            'event_type' => $this->eventType,
            'module_id' => $this->moduleId,
            'module_name' => $this->getModuleName(),
            'module_version' => $this->getModuleVersion(),
            'summary' => $this->getSummary(),
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get dependencies that should be checked
     */
    public function getDependencies(): array
    {
        return $this->module?->dependencies ?? [];
    }

    /**
     * Get conflicts that should be checked
     */
    public function getConflicts(): array
    {
        return $this->module?->conflicts ?? [];
    }

    /**
     * Check if event should trigger dependency validation
     */
    public function shouldValidateDependencies(): bool
    {
        return in_array($this->eventType, [
            self::TYPE_INSTALLING,
            self::TYPE_ENABLING,
            self::TYPE_UPDATING
        ]);
    }

    /**
     * Check if event should trigger cleanup
     */
    public function shouldTriggerCleanup(): bool
    {
        return in_array($this->eventType, [
            self::TYPE_UNINSTALLED,
            self::TYPE_DISABLED
        ]);
    }

    /**
     * Get cleanup tasks
     */
    public function getCleanupTasks(): array
    {
        return $this->getDataValue('cleanup_tasks', []);
    }

    /**
     * Add cleanup task
     */
    public function addCleanupTask(string $task, array $parameters = []): self
    {
        $cleanupTasks = $this->getCleanupTasks();
        $cleanupTasks[] = [
            'task' => $task,
            'parameters' => $parameters,
        ];

        return $this->setDataValue('cleanup_tasks', $cleanupTasks);
    }
}