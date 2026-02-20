<?php

namespace Modules\Loyalty\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasSequence;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Patients\Models\Patient;

class Referral extends BaseModel
{
    use HasTenancy, HasSequence;

    protected $table = 'referrals';

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REWARDED = 'rewarded';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'code',
        'referral_program_id',
        'referrer_patient_id',
        'referred_patient_id',
        'status',
        'referrer_points_awarded',
        'referred_points_awarded',
        'referrer_discount_used',
        'referred_discount_used',
        'first_purchase_invoice_id',
        'completed_at',
        'rewarded_at',
        'expires_at',
        'notes',
    ];

    protected $casts = [
        'referrer_points_awarded' => 'integer',
        'referred_points_awarded' => 'integer',
        'referrer_discount_used' => 'boolean',
        'referred_discount_used' => 'boolean',
        'completed_at' => 'datetime',
        'rewarded_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected static function booted(): void
    {
        static::creating(function ($referral) {
            if (empty($referral->code)) {
                $referral->code = $referral->generateSequenceCode('REF');
            }
        });
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => __('loyalty::loyalty.referral_statuses.pending'),
            self::STATUS_COMPLETED => __('loyalty::loyalty.referral_statuses.completed'),
            self::STATUS_REWARDED => __('loyalty::loyalty.referral_statuses.rewarded'),
            self::STATUS_EXPIRED => __('loyalty::loyalty.referral_statuses.expired'),
            self::STATUS_CANCELLED => __('loyalty::loyalty.referral_statuses.cancelled'),
        ];
    }

    public static function getStatusColors(): array
    {
        return [
            self::STATUS_PENDING => 'warning',
            self::STATUS_COMPLETED => 'info',
            self::STATUS_REWARDED => 'success',
            self::STATUS_EXPIRED => 'gray',
            self::STATUS_CANCELLED => 'danger',
        ];
    }

    public function referralProgram(): BelongsTo
    {
        return $this->belongsTo(ReferralProgram::class);
    }

    public function referrerPatient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'referrer_patient_id');
    }

    public function referredPatient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'referred_patient_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isRewarded(): bool
    {
        return $this->status === self::STATUS_REWARDED;
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function markAsRewarded(int $referrerPoints, int $referredPoints): void
    {
        $this->update([
            'status' => self::STATUS_REWARDED,
            'referrer_points_awarded' => $referrerPoints,
            'referred_points_awarded' => $referredPoints,
            'rewarded_at' => now(),
        ]);
    }

    public function markAsExpired(): void
    {
        $this->update([
            'status' => self::STATUS_EXPIRED,
        ]);
    }

    public function markAsCancelled(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeRewarded($query)
    {
        return $query->where('status', self::STATUS_REWARDED);
    }

    public function scopeForReferrer($query, $patientId)
    {
        return $query->where('referrer_patient_id', $patientId);
    }

    public function scopeForReferred($query, $patientId)
    {
        return $query->where('referred_patient_id', $patientId);
    }
}
