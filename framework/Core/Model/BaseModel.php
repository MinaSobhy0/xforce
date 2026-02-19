<?php

namespace XLinic\Framework\Core\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasAudit;

abstract class BaseModel extends Model
{
    use HasTenancy, HasAudit;

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the auto-incrementing ID.
     */
    protected $keyType = 'string';

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        parent::booted();

        // Auto-generate UUID on creating
        static::creating(function (self $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Str::orderedUuid()->toString();
            }
        });

        // Apply model extensions
        static::created(function (self $model) {
            app(ModelRegistry::class)->applyExtensions($model);
        });
    }

    /**
     * Get a setting value.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return app(\XLinic\Framework\Core\Settings\SettingsRegistry::class)->get($key, $default);
    }

    /**
     * Get the next sequence number.
     */
    public function nextSequence(string $code): string
    {
        return app(\XLinic\Framework\Core\Sequence\SequenceService::class)->next($code);
    }

    /**
     * Get the display name for this model.
     */
    public function getDisplayName(): string
    {
        // Try common name fields
        if (isset($this->attributes['name'])) {
            return $this->attributes['name'];
        }

        if (isset($this->attributes['title'])) {
            return $this->attributes['title'];
        }

        if (isset($this->attributes['full_name'])) {
            return $this->attributes['full_name'];
        }

        // Fall back to ID
        return "#{$this->getKey()}";
    }

    /**
     * Get the tenant ID for this model.
     */
    public function getTenantId(): ?string
    {
        return $this->tenant_id ?? null;
    }

    /**
     * Check if this model is tenant-scoped.
     */
    public function isTenantScoped(): bool
    {
        return in_array('tenant_id', $this->getFillable());
    }
}