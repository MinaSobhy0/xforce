<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Tenant;

class TenantActivityLog extends Model
{
    use HasUuids;

    protected $table = 'tenant_activity_logs';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'event_type',
        'description',
        'metadata',
        'causer_type',
        'causer_id',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public const EVENT_TYPES = [
        'subscription_changed' => 'Subscription Changed',
        'plan_upgraded' => 'Plan Upgraded',
        'plan_downgraded' => 'Plan Downgraded',
        'addon_activated' => 'Add-on Activated',
        'addon_cancelled' => 'Add-on Cancelled',
        'payment_received' => 'Payment Received',
        'payment_failed' => 'Payment Failed',
        'user_added' => 'User Added',
        'user_removed' => 'User Removed',
        'module_activated' => 'Module Activated',
        'module_deactivated' => 'Module Deactivated',
        'owner_login' => 'Owner Login',
        'setting_changed' => 'Setting Changed',
        'support_ticket' => 'Support Ticket',
        'suspended' => 'Account Suspended',
        'reactivated' => 'Account Reactivated',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $log) {
            $log->created_at = $log->created_at ?? now();
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeRecent($query, int $limit = 50)
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }

    public function scopeByEventType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    public function getEventTypeLabelAttribute(): string
    {
        return self::EVENT_TYPES[$this->event_type] ?? ucfirst(str_replace('_', ' ', $this->event_type));
    }

    public function getIconEmojiAttribute(): string
    {
        return match ($this->event_type) {
            'subscription_changed', 'plan_upgraded', 'plan_downgraded' => '📦',
            'addon_activated', 'addon_cancelled' => '🧩',
            'payment_received' => '💳',
            'payment_failed' => '❌',
            'user_added', 'user_removed' => '👤',
            'module_activated', 'module_deactivated' => '🔧',
            'owner_login' => '🔑',
            'setting_changed' => '⚙️',
            'support_ticket' => '🎫',
            'suspended' => '⏸️',
            'reactivated' => '▶️',
            default => '📋',
        };
    }

    public static function log(
        Tenant $tenant,
        string $eventType,
        string $description,
        array $metadata = [],
        ?Model $causer = null
    ): self {
        return static::create([
            'tenant_id' => $tenant->id,
            'event_type' => $eventType,
            'description' => $description,
            'metadata' => $metadata,
            'causer_type' => $causer ? get_class($causer) : null,
            'causer_id' => $causer?->id,
        ]);
    }
}
