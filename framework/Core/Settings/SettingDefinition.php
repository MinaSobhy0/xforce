<?php

namespace XLinic\Framework\Core\Settings;

/**
 * Setting Definition Value Object
 *
 * Represents the definition and metadata for a system setting.
 * Includes type validation, default values, constraints, and
 * user interface information for settings management.
 *
 * @package XLinic\Framework\Core\Settings
 */
class SettingDefinition
{
    /**
     * Setting data types
     */
    public const TYPE_STRING = 'string';
    public const TYPE_INTEGER = 'integer';
    public const TYPE_FLOAT = 'float';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_ARRAY = 'array';
    public const TYPE_JSON = 'json';
    public const TYPE_EMAIL = 'email';
    public const TYPE_URL = 'url';
    public const TYPE_PASSWORD = 'password';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_SELECT = 'select';
    public const TYPE_MULTISELECT = 'multiselect';
    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_RADIO = 'radio';
    public const TYPE_DATE = 'date';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_TIME = 'time';
    public const TYPE_FILE = 'file';
    public const TYPE_IMAGE = 'image';

    /**
     * Setting scopes
     */
    public const SCOPE_GLOBAL = 'global';
    public const SCOPE_TENANT = 'tenant';
    public const SCOPE_USER = 'user';
    public const SCOPE_MODULE = 'module';

    /**
     * Create a new setting definition
     */
    public function __construct(
        protected string $key,
        protected string $type = self::TYPE_STRING,
        protected mixed $defaultValue = null,
        protected ?string $label = null,
        protected ?string $description = null,
        protected string $scope = self::SCOPE_GLOBAL,
        protected array $options = [],
        protected array $validation = [],
        protected array $constraints = [],
        protected array $ui = [],
        protected bool $encrypted = false,
        protected bool $cached = true,
        protected bool $required = false,
        protected bool $readOnly = false,
        protected ?string $group = null,
        protected array $dependencies = [],
        protected ?string $module = null,
        protected array $permissions = [],
        protected array $metadata = []
    ) {}

    /**
     * Get the setting key
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Get the setting type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the default value
     */
    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    /**
     * Get the setting label
     */
    public function getLabel(): string
    {
        return $this->label ?: $this->formatKeyAsLabel($this->key);
    }

    /**
     * Get the setting description
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Get the setting scope
     */
    public function getScope(): string
    {
        return $this->scope;
    }

    /**
     * Get the setting options (for select/radio/checkbox types)
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get validation rules
     */
    public function getValidation(): array
    {
        return $this->validation;
    }

    /**
     * Get constraints
     */
    public function getConstraints(): array
    {
        return $this->constraints;
    }

    /**
     * Get UI configuration
     */
    public function getUi(): array
    {
        return $this->ui;
    }

    /**
     * Check if the setting should be encrypted
     */
    public function isEncrypted(): bool
    {
        return $this->encrypted;
    }

    /**
     * Check if the setting should be cached
     */
    public function isCached(): bool
    {
        return $this->cached;
    }

    /**
     * Check if the setting is required
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Check if the setting is read-only
     */
    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    /**
     * Get the setting group
     */
    public function getGroup(): ?string
    {
        return $this->group;
    }

    /**
     * Get setting dependencies
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * Get the module this setting belongs to
     */
    public function getModule(): ?string
    {
        return $this->module;
    }

    /**
     * Get required permissions
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * Get metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Validate a value against this setting definition
     */
    public function validate(mixed $value): array
    {
        $errors = [];

        // Check required constraint
        if ($this->isRequired() && ($value === null || $value === '')) {
            $errors[] = "Setting '{$this->key}' is required";
        }

        // Skip validation for null values if not required
        if ($value === null && !$this->isRequired()) {
            return $errors;
        }

        // Type validation
        $typeErrors = $this->validateType($value);
        $errors = array_merge($errors, $typeErrors);

        // Custom validation rules
        $validationErrors = $this->validateRules($value);
        $errors = array_merge($errors, $validationErrors);

        // Constraint validation
        $constraintErrors = $this->validateConstraints($value);
        $errors = array_merge($errors, $constraintErrors);

        return $errors;
    }

    /**
     * Cast a value to the appropriate type
     */
    public function cast(mixed $value): mixed
    {
        if ($value === null) {
            return $this->defaultValue;
        }

        return match ($this->type) {
            self::TYPE_STRING, self::TYPE_EMAIL, self::TYPE_URL,
            self::TYPE_PASSWORD, self::TYPE_TEXTAREA => (string) $value,
            self::TYPE_INTEGER => (int) $value,
            self::TYPE_FLOAT => (float) $value,
            self::TYPE_BOOLEAN => (bool) $value,
            self::TYPE_ARRAY, self::TYPE_MULTISELECT => is_array($value) ? $value : json_decode($value, true) ?: [],
            self::TYPE_JSON => is_string($value) ? json_decode($value, true) : $value,
            default => $value
        };
    }

    /**
     * Format the value for storage
     */
    public function formatForStorage(mixed $value): string
    {
        $castedValue = $this->cast($value);

        return match ($this->type) {
            self::TYPE_BOOLEAN => $castedValue ? '1' : '0',
            self::TYPE_ARRAY, self::TYPE_JSON, self::TYPE_MULTISELECT => json_encode($castedValue),
            default => (string) $castedValue
        };
    }

    /**
     * Format the value for display
     */
    public function formatForDisplay(mixed $value): mixed
    {
        $castedValue = $this->cast($value);

        return match ($this->type) {
            self::TYPE_PASSWORD => str_repeat('*', strlen($castedValue)),
            self::TYPE_BOOLEAN => $castedValue ? 'Yes' : 'No',
            self::TYPE_SELECT, self::TYPE_RADIO => $this->options[$castedValue] ?? $castedValue,
            self::TYPE_MULTISELECT => array_map(
                fn($key) => $this->options[$key] ?? $key,
                is_array($castedValue) ? $castedValue : []
            ),
            default => $castedValue
        };
    }

    /**
     * Get form field configuration
     */
    public function getFormField(): array
    {
        $field = [
            'type' => $this->type,
            'label' => $this->getLabel(),
            'description' => $this->description,
            'required' => $this->required,
            'readonly' => $this->readOnly,
            'default' => $this->defaultValue,
        ];

        // Add options for select/radio/checkbox types
        if (in_array($this->type, [self::TYPE_SELECT, self::TYPE_RADIO, self::TYPE_MULTISELECT, self::TYPE_CHECKBOX])) {
            $field['options'] = $this->options;
        }

        // Add validation rules
        if (!empty($this->validation)) {
            $field['validation'] = $this->validation;
        }

        // Merge UI configuration
        if (!empty($this->ui)) {
            $field = array_merge($field, $this->ui);
        }

        return $field;
    }

    /**
     * Create a definition from array
     */
    public static function fromArray(string $key, array $definition): self
    {
        return new self(
            key: $key,
            type: $definition['type'] ?? self::TYPE_STRING,
            defaultValue: $definition['default'] ?? null,
            label: $definition['label'] ?? null,
            description: $definition['description'] ?? null,
            scope: $definition['scope'] ?? self::SCOPE_GLOBAL,
            options: $definition['options'] ?? [],
            validation: $definition['validation'] ?? [],
            constraints: $definition['constraints'] ?? [],
            ui: $definition['ui'] ?? [],
            encrypted: $definition['encrypted'] ?? false,
            cached: $definition['cached'] ?? true,
            required: $definition['required'] ?? false,
            readOnly: $definition['readonly'] ?? false,
            group: $definition['group'] ?? null,
            dependencies: $definition['dependencies'] ?? [],
            module: $definition['module'] ?? null,
            permissions: $definition['permissions'] ?? [],
            metadata: $definition['metadata'] ?? []
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type,
            'default' => $this->defaultValue,
            'label' => $this->label,
            'description' => $this->description,
            'scope' => $this->scope,
            'options' => $this->options,
            'validation' => $this->validation,
            'constraints' => $this->constraints,
            'ui' => $this->ui,
            'encrypted' => $this->encrypted,
            'cached' => $this->cached,
            'required' => $this->required,
            'readonly' => $this->readOnly,
            'group' => $this->group,
            'dependencies' => $this->dependencies,
            'module' => $this->module,
            'permissions' => $this->permissions,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Validate type
     */
    protected function validateType(mixed $value): array
    {
        $errors = [];

        switch ($this->type) {
            case self::TYPE_EMAIL:
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Setting '{$this->key}' must be a valid email address";
                }
                break;

            case self::TYPE_URL:
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    $errors[] = "Setting '{$this->key}' must be a valid URL";
                }
                break;

            case self::TYPE_INTEGER:
                if (!is_numeric($value) || !is_int($value + 0)) {
                    $errors[] = "Setting '{$this->key}' must be an integer";
                }
                break;

            case self::TYPE_FLOAT:
                if (!is_numeric($value)) {
                    $errors[] = "Setting '{$this->key}' must be a number";
                }
                break;

            case self::TYPE_BOOLEAN:
                if (!is_bool($value) && !in_array($value, ['0', '1', 0, 1, 'true', 'false'], true)) {
                    $errors[] = "Setting '{$this->key}' must be a boolean value";
                }
                break;

            case self::TYPE_ARRAY:
            case self::TYPE_MULTISELECT:
                if (!is_array($value) && !is_string($value)) {
                    $errors[] = "Setting '{$this->key}' must be an array or JSON string";
                } elseif (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $errors[] = "Setting '{$this->key}' must be valid JSON";
                    }
                }
                break;

            case self::TYPE_JSON:
                if (is_string($value)) {
                    json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $errors[] = "Setting '{$this->key}' must be valid JSON";
                    }
                }
                break;
        }

        return $errors;
    }

    /**
     * Validate custom rules
     */
    protected function validateRules(mixed $value): array
    {
        $errors = [];

        foreach ($this->validation as $rule => $parameter) {
            switch ($rule) {
                case 'min_length':
                    if (strlen($value) < $parameter) {
                        $errors[] = "Setting '{$this->key}' must be at least {$parameter} characters";
                    }
                    break;

                case 'max_length':
                    if (strlen($value) > $parameter) {
                        $errors[] = "Setting '{$this->key}' must not exceed {$parameter} characters";
                    }
                    break;

                case 'regex':
                    if (!preg_match($parameter, $value)) {
                        $errors[] = "Setting '{$this->key}' format is invalid";
                    }
                    break;

                case 'in':
                    if (!in_array($value, $parameter)) {
                        $errors[] = "Setting '{$this->key}' must be one of: " . implode(', ', $parameter);
                    }
                    break;
            }
        }

        return $errors;
    }

    /**
     * Validate constraints
     */
    protected function validateConstraints(mixed $value): array
    {
        $errors = [];

        foreach ($this->constraints as $constraint => $parameter) {
            switch ($constraint) {
                case 'min':
                    if (is_numeric($value) && $value < $parameter) {
                        $errors[] = "Setting '{$this->key}' must be at least {$parameter}";
                    }
                    break;

                case 'max':
                    if (is_numeric($value) && $value > $parameter) {
                        $errors[] = "Setting '{$this->key}' must not exceed {$parameter}";
                    }
                    break;

                case 'unique':
                    // This would need to be implemented with database checks
                    break;
            }
        }

        return $errors;
    }

    /**
     * Format key as label
     */
    protected function formatKeyAsLabel(string $key): string
    {
        return ucwords(str_replace(['_', '.', '-'], ' ', $key));
    }
}