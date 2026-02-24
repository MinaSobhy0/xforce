<?php

namespace Modules\Services\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceParameter extends BaseModel
{
    use HasTenancy;

    protected $table = 'service_parameters';

    protected $fillable = [
        'tenant_id',
        'service_id',
        'parameter_key',
        'parameter_config',
        'is_required',
        'category',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'parameter_config' => 'array',
        'is_required' => 'boolean',
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];

    // Parameter categories
    public const CATEGORY_EQUIPMENT_SETTINGS = 'equipment_settings';
    public const CATEGORY_CLINICAL = 'clinical';
    public const CATEGORY_SAFETY = 'safety';
    public const CATEGORY_OUTCOMES = 'outcomes';

    // Parameter types
    public const TYPE_NUMBER = 'number';
    public const TYPE_DECIMAL = 'decimal';
    public const TYPE_TEXT = 'text';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_SELECT = 'select';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_DATE = 'date';
    public const TYPE_TIME = 'time';
    public const TYPE_RANGE = 'range';

    /**
     * Get the service this parameter belongs to.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get the parameter type.
     */
    public function getType(): string
    {
        return $this->parameter_config['type'] ?? self::TYPE_TEXT;
    }

    /**
     * Get the translated label.
     */
    public function getLabel(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $label = $this->parameter_config['label'] ?? [];

        return $label[$locale] ?? $label['en'] ?? $this->parameter_key;
    }

    /**
     * Get the unit of measurement.
     */
    public function getUnit(): ?string
    {
        return $this->parameter_config['unit'] ?? null;
    }

    /**
     * Get the default value.
     */
    public function getDefaultValue()
    {
        return $this->parameter_config['default_value'] ?? null;
    }

    /**
     * Get minimum value (for numeric types).
     */
    public function getMin()
    {
        return $this->parameter_config['min'] ?? null;
    }

    /**
     * Get maximum value (for numeric types).
     */
    public function getMax()
    {
        return $this->parameter_config['max'] ?? null;
    }

    /**
     * Get step value (for numeric types).
     */
    public function getStep()
    {
        return $this->parameter_config['step'] ?? null;
    }

    /**
     * Get select options.
     */
    public function getOptions(): array
    {
        return $this->parameter_config['options'] ?? [];
    }

    /**
     * Get help text.
     */
    public function getHelpText(?string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        $helpText = $this->parameter_config['help_text'] ?? [];

        return $helpText[$locale] ?? $helpText['en'] ?? null;
    }

    /**
     * Get placeholder text.
     */
    public function getPlaceholder(?string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        $placeholder = $this->parameter_config['placeholder'] ?? [];

        return $placeholder[$locale] ?? $placeholder['en'] ?? null;
    }

    /**
     * Validate a value against this parameter's configuration.
     */
    public function validateValue($value): ?string
    {
        // Check required
        if ($this->is_required && ($value === null || $value === '')) {
            return 'This field is required.';
        }

        // Skip if empty and not required
        if ($value === null || $value === '') {
            return null;
        }

        $type = $this->getType();

        switch ($type) {
            case self::TYPE_NUMBER:
            case self::TYPE_DECIMAL:
                if (!is_numeric($value)) {
                    return 'Must be a number.';
                }
                $min = $this->getMin();
                $max = $this->getMax();
                if ($min !== null && $value < $min) {
                    return "Must be at least {$min}.";
                }
                if ($max !== null && $value > $max) {
                    return "Must be at most {$max}.";
                }
                break;

            case self::TYPE_SELECT:
                $options = array_column($this->getOptions(), 'value');
                if (!in_array($value, $options)) {
                    return 'Invalid selection.';
                }
                break;

            case self::TYPE_BOOLEAN:
                if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', true, false], true)) {
                    return 'Must be true or false.';
                }
                break;
        }

        // Custom validation
        $validation = $this->parameter_config['validation'] ?? [];
        if (isset($validation['pattern'])) {
            if (!preg_match($validation['pattern'], (string) $value)) {
                return 'Invalid format.';
            }
        }
        if (isset($validation['min_length']) && strlen((string) $value) < $validation['min_length']) {
            return "Must be at least {$validation['min_length']} characters.";
        }
        if (isset($validation['max_length']) && strlen((string) $value) > $validation['max_length']) {
            return "Must be at most {$validation['max_length']} characters.";
        }

        return null;
    }

    /**
     * Get a Filament form field for this parameter.
     */
    public function toFormField(): array
    {
        $config = [
            'key' => $this->parameter_key,
            'type' => $this->getType(),
            'label' => $this->getLabel(),
            'required' => $this->is_required,
            'default' => $this->getDefaultValue(),
            'help_text' => $this->getHelpText(),
            'placeholder' => $this->getPlaceholder(),
        ];

        if (in_array($this->getType(), [self::TYPE_NUMBER, self::TYPE_DECIMAL, self::TYPE_RANGE])) {
            $config['min'] = $this->getMin();
            $config['max'] = $this->getMax();
            $config['step'] = $this->getStep();
            $config['unit'] = $this->getUnit();
        }

        if ($this->getType() === self::TYPE_SELECT) {
            $config['options'] = $this->getOptions();
        }

        return $config;
    }

    /**
     * Scope to active parameters.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to required parameters.
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope to filter by category.
     */
    public function scopeInCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to order by display order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('id');
    }
}
