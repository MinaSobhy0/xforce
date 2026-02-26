<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\Tenant;

class TenantAddonSubscription extends Model
{
    use SoftDeletes;

    protected $connection = 'central';

    protected $table = 'public.tenant_addon_subscriptions';

    protected $fillable = [
        'tenant_id',
        'module_code',
        'price_minor',
        'currency',
        'status',
        'started_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'price_minor' => 'integer',
        'started_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public const STATUSES = [
        'active' => 'Active',
        'cancelled' => 'Cancelled',
        'pending' => 'Pending',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_code', 'code');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'cancelled' => 'gray',
            'pending' => 'warning',
            default => 'gray',
        };
    }

    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }
}
