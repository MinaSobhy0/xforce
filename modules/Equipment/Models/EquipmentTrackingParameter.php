<?php

namespace Modules\Equipment\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentTrackingParameter extends BaseModel
{
    use HasTenancy;

    protected $table = 'equipment_tracking_parameters';

    protected $fillable = [
        'tenant_id',
        'equipment_id',
        'parameter_key',
        'name',
        'value_type',
        'unit',
        'min_value',
        'max_value',
        'default_value',
        'step',
        'options',
        'is_required',
        'is_cumulative',
        'track_in_session',
        'description',
        'category',
        'display_order',
        'is_active',
        'source',
    ];

    protected $casts = [
        'min_value' => 'decimal:4',
        'max_value' => 'decimal:4',
        'step' => 'decimal:4',
        'options' => 'array',
        'is_required' => 'boolean',
        'is_cumulative' => 'boolean',
        'track_in_session' => 'boolean',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    // Value types
    public const TYPE_INTEGER = 'integer';
    public const TYPE_DECIMAL = 'decimal';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_TEXT = 'text';
    public const TYPE_SELECT = 'select';

    public const VALUE_TYPES = [
        self::TYPE_INTEGER => 'Integer',
        self::TYPE_DECIMAL => 'Decimal',
        self::TYPE_BOOLEAN => 'Yes/No',
        self::TYPE_TEXT => 'Text',
        self::TYPE_SELECT => 'Select',
    ];

    // Common units for equipment parameters
    public const COMMON_UNITS = [
        // Energy
        'J' => 'Joules (J)',
        'mJ' => 'Millijoules (mJ)',
        'kJ' => 'Kilojoules (kJ)',
        'J/cm²' => 'Fluence (J/cm²)',

        // Power
        'W' => 'Watts (W)',
        'mW' => 'Milliwatts (mW)',
        'kW' => 'Kilowatts (kW)',

        // Frequency
        'Hz' => 'Hertz (Hz)',
        'kHz' => 'Kilohertz (kHz)',
        'MHz' => 'Megahertz (MHz)',

        // Time
        'ms' => 'Milliseconds (ms)',
        'μs' => 'Microseconds (μs)',
        's' => 'Seconds (s)',
        'min' => 'Minutes (min)',

        // Temperature
        '°C' => 'Celsius (°C)',
        '°F' => 'Fahrenheit (°F)',

        // Pressure
        'bar' => 'Bar',
        'psi' => 'PSI',
        'mmHg' => 'mmHg',

        // Measurements
        'mm' => 'Millimeters (mm)',
        'cm' => 'Centimeters (cm)',
        'cm²' => 'Square cm (cm²)',
        'nm' => 'Nanometers (nm)',

        // Counts
        'pulses' => 'Pulses',
        'shots' => 'Shots',
        '%' => 'Percentage (%)',
    ];

    // Parameter categories
    public const CATEGORIES = [
        'energy' => 'Energy Settings',
        'timing' => 'Timing Settings',
        'safety' => 'Safety Settings',
        'cooling' => 'Cooling Settings',
        'delivery' => 'Delivery Settings',
        'other' => 'Other',
    ];

    /**
     * Get the equipment this parameter belongs to.
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * Scope to active parameters.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to parameters that should be tracked in sessions.
     */
    public function scopeForSession($query)
    {
        return $query->where('track_in_session', true)->where('is_active', true);
    }

    /**
     * Scope to required parameters.
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope ordered by display order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    /**
     * Scope by category.
     */
    public function scopeInCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Format a value with unit for display.
     */
    public function formatValue($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if ($this->value_type === self::TYPE_BOOLEAN) {
            return $value ? __('Yes') : __('No');
        }

        if ($this->value_type === self::TYPE_SELECT && $this->options) {
            foreach ($this->options as $option) {
                if (($option['value'] ?? '') == $value) {
                    return $option['label'] ?? $value;
                }
            }
        }

        $formatted = $value;

        if (in_array($this->value_type, [self::TYPE_INTEGER, self::TYPE_DECIMAL])) {
            $formatted = is_numeric($value) ? number_format((float) $value, $this->value_type === self::TYPE_DECIMAL ? 2 : 0) : $value;
        }

        if ($this->unit) {
            $formatted .= ' ' . $this->unit;
        }

        return $formatted;
    }

    /**
     * Validate a value against this parameter's constraints.
     */
    public function validateValue($value): array
    {
        $errors = [];

        if ($this->is_required && ($value === null || $value === '')) {
            $errors[] = __('This field is required');
            return $errors;
        }

        if ($value === null || $value === '') {
            return $errors; // Not required and empty is OK
        }

        // Type validation
        switch ($this->value_type) {
            case self::TYPE_INTEGER:
                if (!is_numeric($value) || (int) $value != $value) {
                    $errors[] = __('Value must be a whole number');
                }
                break;

            case self::TYPE_DECIMAL:
                if (!is_numeric($value)) {
                    $errors[] = __('Value must be a number');
                }
                break;

            case self::TYPE_BOOLEAN:
                if (!in_array($value, [true, false, 0, 1, '0', '1'], true)) {
                    $errors[] = __('Value must be yes or no');
                }
                break;

            case self::TYPE_SELECT:
                $validValues = collect($this->options ?? [])->pluck('value')->toArray();
                if (!in_array($value, $validValues)) {
                    $errors[] = __('Invalid selection');
                }
                break;
        }

        // Range validation for numeric types
        if (in_array($this->value_type, [self::TYPE_INTEGER, self::TYPE_DECIMAL]) && is_numeric($value)) {
            if ($this->min_value !== null && $value < $this->min_value) {
                $errors[] = __('Value must be at least :min', ['min' => $this->formatValue($this->min_value)]);
            }

            if ($this->max_value !== null && $value > $this->max_value) {
                $errors[] = __('Value must be at most :max', ['max' => $this->formatValue($this->max_value)]);
            }
        }

        return $errors;
    }

    /**
     * Convert to form field configuration for dynamic forms.
     */
    public function toFormFieldConfig(): array
    {
        $config = [
            'key' => $this->parameter_key,
            'label' => $this->name,
            'type' => $this->getInputType(),
            'required' => $this->is_required,
            'help_text' => $this->description,
            'default' => $this->default_value,
        ];

        if ($this->unit) {
            $config['unit'] = $this->unit;
        }

        if (in_array($this->value_type, [self::TYPE_INTEGER, self::TYPE_DECIMAL])) {
            if ($this->min_value !== null) {
                $config['min'] = $this->min_value;
            }
            if ($this->max_value !== null) {
                $config['max'] = $this->max_value;
            }
            if ($this->step !== null) {
                $config['step'] = $this->step;
            } elseif ($this->value_type === self::TYPE_DECIMAL) {
                $config['step'] = 0.01;
            }
        }

        if ($this->value_type === self::TYPE_SELECT && $this->options) {
            $config['options'] = $this->options;
        }

        return $config;
    }

    /**
     * Get HTML input type based on value type.
     */
    public function getInputType(): string
    {
        return match ($this->value_type) {
            self::TYPE_INTEGER, self::TYPE_DECIMAL => 'number',
            self::TYPE_BOOLEAN => 'boolean',
            self::TYPE_SELECT => 'select',
            default => 'text',
        };
    }

    /**
     * Get the category label.
     */
    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category ?? 'Other';
    }

    /**
     * Cast a value to the appropriate type.
     */
    public function castValue($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($this->value_type) {
            self::TYPE_INTEGER => (int) $value,
            self::TYPE_DECIMAL => (float) $value,
            self::TYPE_BOOLEAN => (bool) $value,
            default => $value,
        };
    }
}
