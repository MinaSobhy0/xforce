<?php

namespace Modules\Equipment\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Auth\Models\User;

class EquipmentParameterTemplate extends BaseModel
{
    use HasTenancy;

    protected $table = 'equipment_parameter_templates';

    protected $fillable = [
        'tenant_id',
        'template_name',
        'template_code',
        'description',
        'equipment_category',
        'parameters',
        'is_system',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'parameters' => 'array',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $appends = ['translated_name'];

    /**
     * Get the translated name attribute.
     */
    public function getTranslatedNameAttribute(): string
    {
        return $this->template_name ?? '';
    }

    /**
     * Get equipment using this template.
     */
    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class, 'parameter_template_id');
    }

    /**
     * Get the user who created this template.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this template.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get parameter definitions as collection.
     */
    public function getParameterDefinitions(): array
    {
        return $this->parameters ?? [];
    }

    /**
     * Get a specific parameter definition by key.
     */
    public function getParameter(string $key): ?array
    {
        $parameters = $this->parameters ?? [];
        foreach ($parameters as $param) {
            if (($param['key'] ?? null) === $key) {
                return $param;
            }
        }
        return null;
    }

    /**
     * Validate parameter values against template definitions.
     */
    public function validateValues(array $values): array
    {
        $errors = [];
        $definitions = $this->getParameterDefinitions();

        foreach ($definitions as $definition) {
            $key = $definition['key'] ?? null;
            if (!$key) {
                continue;
            }

            $value = $values[$key] ?? null;
            $isRequired = $definition['required'] ?? false;

            // Check required
            if ($isRequired && ($value === null || $value === '')) {
                $errors[$key] = __('equipment::equipment.parameters.validation.required');
                continue;
            }

            // Skip validation if empty and not required
            if ($value === null || $value === '') {
                continue;
            }

            // Validate based on type
            $error = $this->validateValue($value, $definition);
            if ($error) {
                $errors[$key] = $error;
            }
        }

        return $errors;
    }

    /**
     * Validate a single value against its definition.
     */
    protected function validateValue($value, array $definition): ?string
    {
        $type = $definition['type'] ?? 'text';

        switch ($type) {
            case 'number':
            case 'integer':
                if (!is_numeric($value)) {
                    return __('equipment::equipment.parameters.validation.must_be_number');
                }
                if (isset($definition['min']) && $value < $definition['min']) {
                    return __('equipment::equipment.parameters.validation.min', ['min' => $definition['min']]);
                }
                if (isset($definition['max']) && $value > $definition['max']) {
                    return __('equipment::equipment.parameters.validation.max', ['max' => $definition['max']]);
                }
                break;

            case 'decimal':
                if (!is_numeric($value)) {
                    return __('equipment::equipment.parameters.validation.must_be_number');
                }
                if (isset($definition['min']) && $value < $definition['min']) {
                    return __('equipment::equipment.parameters.validation.min', ['min' => $definition['min']]);
                }
                if (isset($definition['max']) && $value > $definition['max']) {
                    return __('equipment::equipment.parameters.validation.max', ['max' => $definition['max']]);
                }
                break;

            case 'select':
                $options = array_column($definition['options'] ?? [], 'value');
                if (!in_array($value, $options)) {
                    return __('equipment::equipment.parameters.validation.invalid_selection');
                }
                break;

            case 'boolean':
                if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', true, false], true)) {
                    return __('equipment::equipment.parameters.validation.must_be_boolean');
                }
                break;
        }

        return null;
    }

    /**
     * Get default values for all parameters.
     */
    public function getDefaultValues(): array
    {
        $defaults = [];
        foreach ($this->getParameterDefinitions() as $definition) {
            $key = $definition['key'] ?? null;
            if ($key && isset($definition['default_value'])) {
                $defaults[$key] = $definition['default_value'];
            }
        }
        return $defaults;
    }

    /**
     * Apply this template to an equipment, creating tracking parameters.
     */
    public function applyToEquipment(Equipment $equipment): void
    {
        // Enable tracking on the equipment
        $equipment->update([
            'tracking_enabled' => true,
            'parameter_template_id' => $this->id,
        ]);

        // Delete existing parameters from this template
        $equipment->trackingParameters()
            ->where('source', 'template')
            ->delete();

        // Create new parameters from template
        foreach ($this->getParameterDefinitions() as $index => $definition) {
            $equipment->trackingParameters()->create([
                'tenant_id' => $equipment->tenant_id,
                'parameter_key' => $definition['key'] ?? 'param_' . $index,
                'name' => $definition['label']['en'] ?? $definition['label'] ?? $definition['key'],
                'value_type' => $this->mapType($definition['type'] ?? 'text'),
                'unit' => $definition['unit'] ?? null,
                'category' => $definition['category'] ?? 'general',
                'description' => $definition['help_text']['en'] ?? $definition['help_text'] ?? null,
                'min_value' => $definition['min'] ?? null,
                'max_value' => $definition['max'] ?? null,
                'default_value' => $definition['default_value'] ?? null,
                'step' => $definition['step'] ?? null,
                'options' => $definition['options'] ?? null,
                'is_required' => $definition['required'] ?? false,
                'is_cumulative' => $definition['cumulative'] ?? false,
                'track_in_session' => true,
                'display_order' => $index + 1,
                'is_active' => true,
                'source' => 'template',
            ]);
        }
    }

    /**
     * Map template type to tracking parameter value type.
     */
    protected function mapType(string $type): string
    {
        return match ($type) {
            'number' => 'integer',
            'decimal' => 'decimal',
            'boolean' => 'boolean',
            'select' => 'select',
            'textarea' => 'text',
            default => 'text',
        };
    }

    /**
     * Scope to system templates only.
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope to tenant templates only.
     */
    public function scopeTenantTemplates($query)
    {
        return $query->where('is_system', false);
    }

    /**
     * Scope to active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by equipment category.
     */
    public function scopeForCategory($query, string $category)
    {
        return $query->where('equipment_category', $category);
    }
}
