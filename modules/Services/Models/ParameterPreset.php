<?php

namespace Modules\Services\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasTranslation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\User;

class ParameterPreset extends BaseModel
{
    use HasTenancy, HasTranslation;

    protected $table = 'parameter_presets';

    protected $fillable = [
        'tenant_id',
        'service_id',
        'name',
        'description',
        'values',
        'is_default',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'values' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public array $translatable = ['name', 'description'];

    protected $appends = ['translated_name', 'translated_description'];

    public function getTranslatedNameAttribute(): string
    {
        return $this->getTranslation('name', app()->getLocale())
            ?? $this->getTranslation('name', 'en')
            ?? '';
    }

    public function getTranslatedDescriptionAttribute(): ?string
    {
        return $this->getTranslation('description', app()->getLocale())
            ?? $this->getTranslation('description', 'en');
    }

    /**
     * Get the service this preset belongs to.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get the user who created this preset.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this preset.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get a specific preset value by key.
     */
    public function getValue(string $key)
    {
        return $this->values[$key] ?? null;
    }

    /**
     * Get all preset values.
     */
    public function getValues(): array
    {
        return $this->values ?? [];
    }

    /**
     * Check if a specific key has a value.
     */
    public function hasValue(string $key): bool
    {
        return isset($this->values[$key]);
    }

    /**
     * Merge preset values with provided values (provided values take priority).
     */
    public function mergeWithValues(array $values): array
    {
        return array_merge($this->getValues(), $values);
    }

    /**
     * Get only the values that differ from another preset.
     */
    public function getDifferencesFrom(ParameterPreset $other): array
    {
        $differences = [];
        $thisValues = $this->getValues();
        $otherValues = $other->getValues();

        foreach ($thisValues as $key => $value) {
            if (!isset($otherValues[$key]) || $otherValues[$key] !== $value) {
                $differences[$key] = [
                    'preset' => $value,
                    'other' => $otherValues[$key] ?? null,
                ];
            }
        }

        return $differences;
    }

    /**
     * Mark this preset as the default for its service.
     */
    public function markAsDefault(): void
    {
        // Unset any existing default for this service
        static::where('service_id', $this->service_id)
            ->where('id', '!=', $this->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Scope to active presets.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to default preset.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to presets for a specific service.
     */
    public function scopeForService($query, string $serviceId)
    {
        return $query->where('service_id', $serviceId);
    }
}
