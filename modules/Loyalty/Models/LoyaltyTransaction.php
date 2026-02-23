<?php

namespace Modules\Loyalty\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Patients\Models\Patient;
use Modules\Auth\Models\User;

class LoyaltyTransaction extends BaseModel
{
    use HasTenancy;

    protected $table = 'loyalty_transactions';

    // Transaction types
    public const TYPE_EARN = 'earn';
    public const TYPE_REDEEM = 'redeem';
    public const TYPE_EXPIRE = 'expire';
    public const TYPE_ADJUST = 'adjust';
    public const TYPE_REFUND = 'refund';
    public const TYPE_BONUS = 'bonus';
    public const TYPE_REFERRAL = 'referral';

    protected $fillable = [
        'tenant_id',
        'patient_id',
        'loyalty_rule_id',
        'type',
        'points',
        'running_balance',
        'description',
        'reference_type',
        'reference_id',
        'metadata',
        'expires_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'points' => 'integer',
        'running_balance' => 'integer',
        'metadata' => 'array',
        'expires_at' => 'datetime',
    ];

    public static function getTypes(): array
    {
        return [
            self::TYPE_EARN => __('loyalty::loyalty.transaction_types.earn'),
            self::TYPE_REDEEM => __('loyalty::loyalty.transaction_types.redeem'),
            self::TYPE_EXPIRE => __('loyalty::loyalty.transaction_types.expire'),
            self::TYPE_ADJUST => __('loyalty::loyalty.transaction_types.adjust'),
            self::TYPE_REFUND => __('loyalty::loyalty.transaction_types.refund'),
            self::TYPE_BONUS => __('loyalty::loyalty.transaction_types.bonus'),
            self::TYPE_REFERRAL => __('loyalty::loyalty.transaction_types.referral'),
        ];
    }

    public static function getTypeColors(): array
    {
        return [
            self::TYPE_EARN => 'success',
            self::TYPE_REDEEM => 'warning',
            self::TYPE_EXPIRE => 'danger',
            self::TYPE_ADJUST => 'info',
            self::TYPE_REFUND => 'gray',
            self::TYPE_BONUS => 'primary',
            self::TYPE_REFERRAL => 'success',
        ];
    }

    public static function getTypeIcons(): array
    {
        return [
            self::TYPE_EARN => 'heroicon-o-plus-circle',
            self::TYPE_REDEEM => 'heroicon-o-minus-circle',
            self::TYPE_EXPIRE => 'heroicon-o-clock',
            self::TYPE_ADJUST => 'heroicon-o-adjustments-horizontal',
            self::TYPE_REFUND => 'heroicon-o-arrow-uturn-left',
            self::TYPE_BONUS => 'heroicon-o-gift',
            self::TYPE_REFERRAL => 'heroicon-o-user-plus',
        ];
    }

    public static function getTypeOptions(): array
    {
        return self::getTypes();
    }

    public static function getTypeLabel(?string $type): string
    {
        return self::getTypes()[$type] ?? $type ?? '-';
    }

    public static function getTypeColor(?string $type): string
    {
        return self::getTypeColors()[$type] ?? 'gray';
    }

    public static function getTypeIcon(?string $type): string
    {
        return self::getTypeIcons()[$type] ?? 'heroicon-o-question-mark-circle';
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function loyaltyRule(): BelongsTo
    {
        return $this->belongsTo(LoyaltyRule::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isCredit(): bool
    {
        return in_array($this->type, [
            self::TYPE_EARN,
            self::TYPE_BONUS,
            self::TYPE_REFERRAL,
            self::TYPE_REFUND,
        ]);
    }

    public function isDebit(): bool
    {
        return in_array($this->type, [
            self::TYPE_REDEEM,
            self::TYPE_EXPIRE,
        ]);
    }

    public function getSignedPoints(): int
    {
        return $this->isCredit() ? abs($this->points) : -abs($this->points);
    }

    public function scopeCredits($query)
    {
        return $query->whereIn('type', [
            self::TYPE_EARN,
            self::TYPE_BONUS,
            self::TYPE_REFERRAL,
            self::TYPE_REFUND,
        ]);
    }

    public function scopeDebits($query)
    {
        return $query->whereIn('type', [
            self::TYPE_REDEEM,
            self::TYPE_EXPIRE,
        ]);
    }

    public function scopeForPatient($query, $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }
}
