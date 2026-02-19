<?php

namespace XLinic\Framework\Core\Action;

abstract class ServerAction
{
    /**
     * The action code (unique identifier).
     */
    protected string $code;

    /**
     * The action name (translatable).
     */
    protected array $name = [];

    /**
     * The action description (translatable).
     */
    protected array $description = [];

    /**
     * The module this action belongs to.
     */
    protected ?string $moduleCode = null;

    /**
     * The permission required to execute this action.
     */
    protected ?string $permission = null;

    /**
     * The role required to execute this action.
     */
    protected ?string $role = null;

    /**
     * Validation rules for action data.
     */
    protected array $rules = [];

    /**
     * Execute the server action.
     */
    abstract public function execute(array $data = []): mixed;

    /**
     * Get the action code.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Get the action name for current locale.
     */
    public function getName(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        if (isset($this->name[$locale])) {
            return $this->name[$locale];
        }

        if (isset($this->name['en'])) {
            return $this->name['en'];
        }

        if (!empty($this->name)) {
            return array_values($this->name)[0];
        }

        return str_replace(['_', '-'], ' ', ucwords($this->code, '_-'));
    }

    /**
     * Get the action description for current locale.
     */
    public function getDescription(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        if (isset($this->description[$locale])) {
            return $this->description[$locale];
        }

        if (isset($this->description['en'])) {
            return $this->description['en'];
        }

        if (!empty($this->description)) {
            return array_values($this->description)[0];
        }

        return $this->getName($locale);
    }

    /**
     * Get the module code this action belongs to.
     */
    public function getModuleCode(): ?string
    {
        return $this->moduleCode;
    }

    /**
     * Check if user can execute this action.
     */
    public function canExecute($user = null): bool
    {
        $user = $user ?? auth()->user();

        if (!$user) {
            return false;
        }

        $permissionRegistry = app(\XLinic\Framework\Core\Security\PermissionRegistry::class);

        // Check permission if specified
        if ($this->permission) {
            return $permissionRegistry->userCan($user, $this->permission);
        }

        // Check role if specified
        if ($this->role) {
            return $permissionRegistry->userHasRole($user, $this->role);
        }

        return true;
    }

    /**
     * Validate action data.
     */
    public function validate(array $data): array
    {
        if (empty($this->rules)) {
            return [];
        }

        $validator = validator($data, $this->rules);

        if ($validator->fails()) {
            return $validator->errors()->toArray();
        }

        return [];
    }

    /**
     * Get validation rules.
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Set validation rules.
     */
    public function setRules(array $rules): self
    {
        $this->rules = $rules;
        return $this;
    }

    /**
     * Before execution hook.
     */
    protected function beforeExecution(array $data): void
    {
        // Override in subclasses if needed
    }

    /**
     * After execution hook.
     */
    protected function afterExecution(array $data, mixed $result): void
    {
        // Override in subclasses if needed
    }

    /**
     * Handle execution with hooks.
     */
    final public function handle(array $data = []): mixed
    {
        $this->beforeExecution($data);
        $result = $this->execute($data);
        $this->afterExecution($data, $result);

        return $result;
    }

    /**
     * Get action metadata.
     */
    public function getMetadata(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'module_code' => $this->moduleCode,
            'permission' => $this->permission,
            'role' => $this->role,
            'rules' => $this->rules,
        ];
    }

    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'module_code' => $this->moduleCode,
            'permission' => $this->permission,
            'role' => $this->role,
            'can_execute' => $this->canExecute(),
            'has_rules' => !empty($this->rules),
        ];
    }

    /**
     * Get current tenant for context.
     */
    protected function getCurrentTenant()
    {
        return app(\XLinic\Framework\Core\Tenancy\TenantManager::class)->current();
    }

    /**
     * Get current user for context.
     */
    protected function getCurrentUser()
    {
        return auth()->user();
    }

    /**
     * Log activity for this action.
     */
    protected function logActivity(string $message, array $properties = []): void
    {
        activity()
            ->withProperties(array_merge($properties, [
                'action_code' => $this->code,
                'module_code' => $this->moduleCode,
            ]))
            ->log($message);
    }

    /**
     * Send notification after action execution.
     */
    protected function sendNotification(string $title, string $body = '', string $type = 'info'): void
    {
        if (class_exists(\Filament\Notifications\Notification::class)) {
            $notification = \Filament\Notifications\Notification::make()
                ->title($title)
                ->body($body);

            match ($type) {
                'success' => $notification->success(),
                'warning' => $notification->warning(),
                'danger' => $notification->danger(),
                default => $notification->info(),
            };

            $notification->send();
        }
    }

    /**
     * Get tenant-scoped data.
     */
    protected function getTenantData(string $model, array $options = []): array
    {
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            return [];
        }

        $query = app($model)->newQuery();

        // Apply tenant scoping if model supports it
        if (method_exists(app($model), 'isTenantScoped') && app($model)->isTenantScoped()) {
            $query->where('tenant_id', $tenant->id);
        }

        // Apply filters if provided
        if (isset($options['filters'])) {
            foreach ($options['filters'] as $field => $value) {
                $query->where($field, $value);
            }
        }

        return [
            'query' => $query,
            'total' => $query->count(),
            'records' => $query->limit($options['limit'] ?? 100)->get(),
        ];
    }
}