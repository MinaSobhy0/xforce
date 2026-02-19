<?php

namespace XLinic\Framework\Core\Event;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use XLinic\Framework\Core\Tenancy\TenantManager;

/**
 * Base Model Event
 *
 * Abstract base class for all model-related events in the framework.
 * Provides common functionality for model event handling, tenant
 * context, and event metadata management.
 *
 * @package XLinic\Framework\Core\Event
 */
abstract class ModelEvent
{
    use Dispatchable, SerializesModels;

    /**
     * Event types
     */
    public const TYPE_CREATING = 'creating';
    public const TYPE_CREATED = 'created';
    public const TYPE_UPDATING = 'updating';
    public const TYPE_UPDATED = 'updated';
    public const TYPE_SAVING = 'saving';
    public const TYPE_SAVED = 'saved';
    public const TYPE_DELETING = 'deleting';
    public const TYPE_DELETED = 'deleted';
    public const TYPE_RESTORING = 'restoring';
    public const TYPE_RESTORED = 'restored';
    public const TYPE_FORCE_DELETING = 'forceDeleting';
    public const TYPE_FORCE_DELETED = 'forceDeleted';

    /**
     * The model instance
     */
    public Model $model;

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
     * Original model attributes (for update events)
     */
    public array $originalAttributes = [];

    /**
     * Changed attributes (for update events)
     */
    public array $changedAttributes = [];

    /**
     * Event metadata
     */
    public array $metadata = [];

    /**
     * Create a new model event instance
     */
    public function __construct(
        Model $model,
        string $eventType,
        array $data = [],
        ?object $user = null,
        array $metadata = []
    ) {
        $this->model = $model;
        $this->eventType = $eventType;
        $this->data = $data;
        $this->user = $user ?: auth()->user();
        $this->metadata = $metadata;
        $this->timestamp = new \DateTime();

        // Capture tenant context
        $this->captureTenantContext();

        // Capture model changes for update events
        $this->captureModelChanges();

        // Add default metadata
        $this->addDefaultMetadata();
    }

    /**
     * Get the model instance
     */
    public function getModel(): Model
    {
        return $this->model;
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
     * Get original attributes
     */
    public function getOriginalAttributes(): array
    {
        return $this->originalAttributes;
    }

    /**
     * Get changed attributes
     */
    public function getChangedAttributes(): array
    {
        return $this->changedAttributes;
    }

    /**
     * Check if a specific attribute was changed
     */
    public function isAttributeChanged(string $attribute): bool
    {
        return array_key_exists($attribute, $this->changedAttributes);
    }

    /**
     * Get the original value of an attribute
     */
    public function getOriginalValue(string $attribute): mixed
    {
        return $this->originalAttributes[$attribute] ?? null;
    }

    /**
     * Get the new value of an attribute
     */
    public function getNewValue(string $attribute): mixed
    {
        return $this->changedAttributes[$attribute] ?? $this->model->getAttribute($attribute);
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
     * Get the model class name
     */
    public function getModelClass(): string
    {
        return get_class($this->model);
    }

    /**
     * Get the model table name
     */
    public function getModelTable(): string
    {
        return $this->model->getTable();
    }

    /**
     * Get the model key
     */
    public function getModelKey(): mixed
    {
        return $this->model->getKey();
    }

    /**
     * Get the model key name
     */
    public function getModelKeyName(): string
    {
        return $this->model->getKeyName();
    }

    /**
     * Check if this is a create event
     */
    public function isCreateEvent(): bool
    {
        return in_array($this->eventType, [self::TYPE_CREATING, self::TYPE_CREATED]);
    }

    /**
     * Check if this is an update event
     */
    public function isUpdateEvent(): bool
    {
        return in_array($this->eventType, [self::TYPE_UPDATING, self::TYPE_UPDATED]);
    }

    /**
     * Check if this is a delete event
     */
    public function isDeleteEvent(): bool
    {
        return in_array($this->eventType, [
            self::TYPE_DELETING,
            self::TYPE_DELETED,
            self::TYPE_FORCE_DELETING,
            self::TYPE_FORCE_DELETED
        ]);
    }

    /**
     * Check if this is a restore event
     */
    public function isRestoreEvent(): bool
    {
        return in_array($this->eventType, [self::TYPE_RESTORING, self::TYPE_RESTORED]);
    }

    /**
     * Check if this is a "before" event
     */
    public function isBeforeEvent(): bool
    {
        return in_array($this->eventType, [
            self::TYPE_CREATING,
            self::TYPE_UPDATING,
            self::TYPE_SAVING,
            self::TYPE_DELETING,
            self::TYPE_RESTORING,
            self::TYPE_FORCE_DELETING
        ]);
    }

    /**
     * Check if this is an "after" event
     */
    public function isAfterEvent(): bool
    {
        return in_array($this->eventType, [
            self::TYPE_CREATED,
            self::TYPE_UPDATED,
            self::TYPE_SAVED,
            self::TYPE_DELETED,
            self::TYPE_RESTORED,
            self::TYPE_FORCE_DELETED
        ]);
    }

    /**
     * Get event summary for logging
     */
    public function getSummary(): string
    {
        $modelClass = class_basename($this->getModelClass());
        $modelKey = $this->getModelKey();
        $eventType = $this->getEventType();

        return "{$modelClass}#{$modelKey} {$eventType}";
    }

    /**
     * Get event context for logging
     */
    public function getContext(): array
    {
        return [
            'event_type' => $this->eventType,
            'model_class' => $this->getModelClass(),
            'model_table' => $this->getModelTable(),
            'model_key' => $this->getModelKey(),
            'tenant_id' => $this->tenant?->id,
            'user_id' => $this->user?->id,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'changes' => $this->getChangedAttributes(),
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
            'model' => [
                'class' => $this->getModelClass(),
                'table' => $this->getModelTable(),
                'key' => $this->getModelKey(),
                'attributes' => $this->model->getAttributes(),
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
            'original_attributes' => $this->originalAttributes,
            'changed_attributes' => $this->changedAttributes,
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
     * Capture model changes for update events
     */
    protected function captureModelChanges(): void
    {
        if ($this->isUpdateEvent() && $this->model->exists) {
            $this->originalAttributes = $this->model->getOriginal();
            $this->changedAttributes = $this->model->getDirty();
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
            'source' => 'model_event',
        ], $this->metadata);
    }

    /**
     * Create event for model creating
     */
    public static function creating(Model $model, array $data = [], ?object $user = null, array $metadata = []): static
    {
        return new static($model, self::TYPE_CREATING, $data, $user, $metadata);
    }

    /**
     * Create event for model created
     */
    public static function created(Model $model, array $data = [], ?object $user = null, array $metadata = []): static
    {
        return new static($model, self::TYPE_CREATED, $data, $user, $metadata);
    }

    /**
     * Create event for model updating
     */
    public static function updating(Model $model, array $data = [], ?object $user = null, array $metadata = []): static
    {
        return new static($model, self::TYPE_UPDATING, $data, $user, $metadata);
    }

    /**
     * Create event for model updated
     */
    public static function updated(Model $model, array $data = [], ?object $user = null, array $metadata = []): static
    {
        return new static($model, self::TYPE_UPDATED, $data, $user, $metadata);
    }

    /**
     * Create event for model deleting
     */
    public static function deleting(Model $model, array $data = [], ?object $user = null, array $metadata = []): static
    {
        return new static($model, self::TYPE_DELETING, $data, $user, $metadata);
    }

    /**
     * Create event for model deleted
     */
    public static function deleted(Model $model, array $data = [], ?object $user = null, array $metadata = []): static
    {
        return new static($model, self::TYPE_DELETED, $data, $user, $metadata);
    }

    /**
     * Create event for model restoring
     */
    public static function restoring(Model $model, array $data = [], ?object $user = null, array $metadata = []): static
    {
        return new static($model, self::TYPE_RESTORING, $data, $user, $metadata);
    }

    /**
     * Create event for model restored
     */
    public static function restored(Model $model, array $data = [], ?object $user = null, array $metadata = []): static
    {
        return new static($model, self::TYPE_RESTORED, $data, $user, $metadata);
    }

    /**
     * Determine if the event should be broadcast
     */
    public function broadcastOn(): array
    {
        $channels = [];

        // Broadcast to tenant channel if tenant exists
        if ($this->tenant) {
            $channels[] = "tenant.{$this->tenant->id}";
        }

        // Broadcast to user channel if user exists
        if ($this->user) {
            $channels[] = "user.{$this->user->id}";
        }

        // Broadcast to model-specific channel
        $modelClass = str_replace('\\', '.', strtolower($this->getModelClass()));
        $channels[] = "model.{$modelClass}";

        if ($this->getModelKey()) {
            $channels[] = "model.{$modelClass}.{$this->getModelKey()}";
        }

        return $channels;
    }

    /**
     * Get the event name for broadcasting
     */
    public function broadcastAs(): string
    {
        $modelClass = class_basename($this->getModelClass());
        return "Model{$modelClass}{$this->eventType}";
    }

    /**
     * Get the data to broadcast
     */
    public function broadcastWith(): array
    {
        return [
            'event_type' => $this->eventType,
            'model_class' => class_basename($this->getModelClass()),
            'model_key' => $this->getModelKey(),
            'summary' => $this->getSummary(),
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
        ];
    }
}