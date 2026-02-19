<?php

namespace XLinic\Framework\Core\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Audit model - extends Model directly to avoid infinite recursion
 * from the HasAudit trait in BaseModel.
 */
class Audit extends Model
{
    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the auto-incrementing ID.
     */
    protected $keyType = 'string';

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Str::orderedUuid()->toString();
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'event',
        'old_values',
        'new_values',
        'user_id',
        'tenant_id',
        'ip_address',
        'user_agent',
        'url',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    /**
     * Get the auditable model.
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Get the changes made.
     */
    public function getChanges(): array
    {
        $changes = [];

        foreach (array_keys($this->new_values ?? []) as $key) {
            $oldValue = $this->old_values[$key] ?? null;
            $newValue = $this->new_values[$key] ?? null;

            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    /**
     * Get the display name for the event.
     */
    public function getEventDisplayName(): string
    {
        return match($this->event) {
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
            'restored' => 'Restored',
            default => ucfirst($this->event),
        };
    }

    /**
     * Get the display name for the auditable model.
     */
    public function getAuditableDisplayName(): string
    {
        if ($this->auditable && method_exists($this->auditable, 'getDisplayName')) {
            return $this->auditable->getDisplayName();
        }

        return class_basename($this->auditable_type) . " #{$this->auditable_id}";
    }

    /**
     * Scope to specific event types.
     */
    public function scopeForEvent($query, $event)
    {
        return $query->where('event', $event);
    }

    /**
     * Scope to specific user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to date range.
     */
    public function scopeInDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Scope to today's audits.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }
}