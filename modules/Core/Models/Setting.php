<?php

namespace Modules\Core\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends BaseModel
{
    use HasTenancy;

    protected $fillable = [
        'tenant_id',
        'group',
        'key',
        'value',
        'type',
        'is_public',
        'is_locked',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_locked' => 'boolean',
    ];

    /**
     * Supported setting types.
     */
    public const TYPES = [
        'string' => 'Text',
        'integer' => 'Integer',
        'float' => 'Decimal',
        'boolean' => 'Yes/No',
        'array' => 'List',
        'json' => 'JSON',
    ];

    /**
     * Get the tenant this setting belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope to settings in a specific group.
     */
    public function scopeInGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    /**
     * Scope to public settings only.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope to unlocked settings only.
     */
    public function scopeUnlocked($query)
    {
        return $query->where('is_locked', false);
    }

    /**
     * Get the typed value of this setting.
     */
    public function getTypedValue(): mixed
    {
        return match ($this->type) {
            'integer' => (int) $this->value,
            'float' => (float) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'array', 'json' => json_decode($this->value, true) ?? [],
            default => $this->value,
        };
    }

    /**
     * Set the value with proper type casting.
     */
    public function setTypedValue(mixed $value): self
    {
        $this->value = match ($this->type) {
            'array', 'json' => is_array($value) ? json_encode($value) : $value,
            'boolean' => $value ? 'true' : 'false',
            default => (string) $value,
        };

        return $this;
    }

    /**
     * Get a setting value by key for a tenant.
     */
    public static function getValue(string $key, ?string $tenantId = null, mixed $default = null): mixed
    {
        $setting = static::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('key', $key)
            ->first();

        return $setting ? $setting->getTypedValue() : $default;
    }

    /**
     * Set a setting value by key for a tenant.
     */
    public static function setValue(string $key, mixed $value, ?string $tenantId = null, string $group = 'general'): static
    {
        $setting = static::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('key', $key)
            ->first();

        if ($setting) {
            if (!$setting->is_locked) {
                $setting->setTypedValue($value)->save();
            }
            return $setting;
        }

        return static::create([
            'tenant_id' => $tenantId,
            'group' => $group,
            'key' => $key,
            'value' => is_array($value) ? json_encode($value) : (string) $value,
            'type' => match (true) {
                is_bool($value) => 'boolean',
                is_int($value) => 'integer',
                is_float($value) => 'float',
                is_array($value) => 'array',
                default => 'string',
            },
        ]);
    }
}
