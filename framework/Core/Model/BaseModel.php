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
     * Get the attributes that should be cast to booleans.
     * This is auto-detected from the $casts array.
     */
    protected function getBooleanFields(): array
    {
        $booleans = [];
        foreach ($this->getCasts() as $key => $type) {
            if ($type === 'boolean' || $type === 'bool') {
                $booleans[] = $key;
            }
        }
        return $booleans;
    }

    /**
     * Override performInsert to handle PostgreSQL boolean casting.
     */
    protected function performInsert(\Illuminate\Database\Eloquent\Builder $query): bool
    {
        $booleanFields = $this->getBooleanFields();

        if (empty($booleanFields)) {
            return parent::performInsert($query);
        }

        // Fire creating event
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        // Set timestamps
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        // Build raw insert with proper boolean handling
        $attributes = $this->getAttributes();
        $columns = [];
        $placeholders = [];
        $values = [];

        foreach ($attributes as $key => $value) {
            $columns[] = '"' . $key . '"';
            if (in_array($key, $booleanFields)) {
                $boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                $placeholders[] = $boolValue ? 'true' : 'false';
            } elseif ($value instanceof \DateTimeInterface) {
                $placeholders[] = '?';
                $values[] = $value->format('Y-m-d H:i:s');
            } elseif (is_array($value)) {
                $placeholders[] = '?';
                $values[] = json_encode($value);
            } else {
                $placeholders[] = '?';
                $values[] = $value;
            }
        }

        $sql = sprintf(
            'INSERT INTO "%s" (%s) VALUES (%s)',
            $this->getTable(),
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        \DB::statement($sql, $values);

        $this->exists = true;
        $this->wasRecentlyCreated = true;
        $this->fireModelEvent('created', false);

        return true;
    }

    /**
     * Override performUpdate to handle PostgreSQL boolean casting.
     */
    protected function performUpdate(\Illuminate\Database\Eloquent\Builder $query): bool
    {
        $dirty = $this->getDirty();
        $booleanFields = $this->getBooleanFields();
        $hasBooleanUpdates = !empty(array_intersect(array_keys($dirty), $booleanFields));

        if (!$hasBooleanUpdates) {
            return parent::performUpdate($query);
        }

        // Fire updating event
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        // Update timestamps
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
            $dirty = $this->getDirty();
        }

        // Build raw update
        $sets = [];
        $values = [];

        foreach ($dirty as $key => $value) {
            if (in_array($key, $booleanFields)) {
                $boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                $sets[] = '"' . $key . '" = ' . ($boolValue ? 'true' : 'false');
            } elseif ($value instanceof \DateTimeInterface) {
                $sets[] = '"' . $key . '" = ?';
                $values[] = $value->format('Y-m-d H:i:s');
            } elseif (is_array($value)) {
                $sets[] = '"' . $key . '" = ?';
                $values[] = json_encode($value);
            } else {
                $sets[] = '"' . $key . '" = ?';
                $values[] = $value;
            }
        }

        $values[] = $this->getKey();

        $sql = sprintf(
            'UPDATE "%s" SET %s WHERE "%s" = ?',
            $this->getTable(),
            implode(', ', $sets),
            $this->getKeyName()
        );

        \DB::statement($sql, $values);

        $this->syncChanges();
        $this->fireModelEvent('updated', false);

        return true;
    }

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