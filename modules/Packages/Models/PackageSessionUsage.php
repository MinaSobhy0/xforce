<?php

namespace Modules\Packages\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Services\Models\Service;
use Modules\Booking\Models\Appointment;
use Modules\Auth\Models\User;
use Modules\Accounting\Models\JournalEntry;
use Modules\Packages\Events\PackageSessionUsed;

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
        'quantity_used',
        'unit_type',
        'used_by_user_id',
        'notes',
        'journal_entry_id',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'quantity_used' => 'integer',
    ];

    // Unit types
    public const UNIT_SESSION = 'session';
    public const UNIT_PULSE = 'pulse';
    public const UNIT_CREDIT = 'credit';

    public const UNIT_TYPES = [
        self::UNIT_SESSION => 'Session',
        self::UNIT_PULSE => 'Pulse',
        self::UNIT_CREDIT => 'Credit',
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
            if (empty($usage->quantity_used)) {
                $usage->quantity_used = 1;
            }
            if (empty($usage->unit_type)) {
                $usage->unit_type = self::UNIT_SESSION;
            }
        });

        static::created(function (PackageSessionUsage $usage) {
            // Check if subscription is now complete
            $usage->subscription?->checkAndMarkComplete();

            // Dispatch event for revenue recognition
            if ($usage->subscription) {
                PackageSessionUsed::dispatch(
                    $usage->subscription,
                    $usage,
                    $usage->appointment
                );
            }
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

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
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

    public function scopeByUnitType($query, string $unitType)
    {
        return $query->where('unit_type', $unitType);
    }

    // Unit type helpers
    public function isSessionUsage(): bool
    {
        return $this->unit_type === self::UNIT_SESSION;
    }

    public function isPulseUsage(): bool
    {
        return $this->unit_type === self::UNIT_PULSE;
    }

    public function getUnitTypeLabelAttribute(): string
    {
        return self::UNIT_TYPES[$this->unit_type] ?? $this->unit_type;
    }

    public function hasRevenueRecognition(): bool
    {
        return $this->journal_entry_id !== null;
    }
}
