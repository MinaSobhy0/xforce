<?php

namespace Modules\Services\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Auth\Models\User;

class ParameterTemplate extends BaseModel
{
    use HasTenancy;

    protected $table = 'parameter_templates';

    protected $fillable = [
        'tenant_id',
        'template_name',
        'template_code',
        'description',
        'service_category',
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
     * Get services using this template.
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'parameter_template_id');
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
                $errors[$key] = 'This field is required.';
                continue;
            }

            // Skip validation if empty and not required
            if ($value === null || $value === '') {
                continue;
            }

            // Validate based on type
            $type = $definition['type'] ?? 'text';
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
            case 'decimal':
                if (!is_numeric($value)) {
                    return 'Must be a number.';
                }
                if (isset($definition['min']) && $value < $definition['min']) {
                    return "Must be at least {$definition['min']}.";
                }
                if (isset($definition['max']) && $value > $definition['max']) {
                    return "Must be at most {$definition['max']}.";
                }
                break;

            case 'select':
                $options = array_column($definition['options'] ?? [], 'value');
                if (!in_array($value, $options)) {
                    return 'Invalid selection.';
                }
                break;

            case 'boolean':
                if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', true, false], true)) {
                    return 'Must be true or false.';
                }
                break;

            case 'range':
                if (!is_array($value) || !isset($value['min'], $value['max'])) {
                    return 'Range must have min and max values.';
                }
                break;
        }

        // Custom validation pattern
        if (isset($definition['validation']['pattern'])) {
            if (!preg_match($definition['validation']['pattern'], (string) $value)) {
                return 'Invalid format.';
            }
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
     * Scope to filter by service category.
     */
    public function scopeForCategory($query, string $category)
    {
        return $query->where('service_category', $category);
    }
}
