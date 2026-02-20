<?php

namespace Modules\Packages\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Treatments\Models\Treatment;
use Modules\Booking\Models\Appointment;
use Modules\Auth\Models\User;

class PackageSessionUsage extends BaseModel
{
    use HasTenancy;

    protected $table = 'package_session_usages';

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'treatment_id',
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

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
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
    public function getTreatmentNameAttribute(): string
    {
        return $this->treatment?->translated_name ?? '';
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

    public function scopeForTreatment($query, string $treatmentId)
    {
        return $query->where('treatment_id', $treatmentId);
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
