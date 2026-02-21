<?php

namespace Modules\Packages\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Services\Models\Service;
use Modules\Booking\Models\Appointment;
use Modules\Auth\Models\User;

class PackageSessionUsage extends BaseModel
{
    use HasTenancy;

    protected $table = 'package_session_usages';

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'service_id',
        'appointment_id',
        'used_at',
        'used_by_user_id',
        'notes',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (PackageSessionUsage $usage) {
            if (empty($usage->used_at)) {
                $usage->used_at = now();
            }
            if (empty($usage->used_by_user_id)) {
                $usage->used_by_user_id = auth()->id();
            }
        });

        static::created(function (PackageSessionUsage $usage) {
            // Check if subscription is now complete
            $usage->subscription?->checkAndMarkComplete();
        });
    }

    // Relationships
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PackageSubscription::class, 'subscription_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by_user_id');
    }

    // Accessors
    public function getServiceNameAttribute(): string
    {
        return $this->service?->translated_name ?? '';
    }

    public function getPatientNameAttribute(): string
    {
        return $this->subscription?->patient?->full_name ?? '';
    }

    // Scopes
    public function scopeForSubscription($query, string $subscriptionId)
    {
        return $query->where('subscription_id', $subscriptionId);
    }

    public function scopeForService($query, string $serviceId)
    {
        return $query->where('service_id', $serviceId);
    }

    public function scopeForDateRange($query, $start, $end)
    {
        return $query->whereBetween('used_at', [$start, $end]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('used_at', today());
    }
}
