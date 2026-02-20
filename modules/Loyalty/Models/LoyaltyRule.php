<?php

namespace Modules\Loyalty\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Treatments\Models\Treatment;
use Modules\Treatments\Models\TreatmentCategory;

class LoyaltyRule extends BaseModel
{
    use HasTenancy, SoftDeletes;

    protected $table = 'loyalty_rules';

    // Rule types
    public const TYPE_PER_SPEND = 'per_spend';
    public const TYPE_PER_VISIT = 'per_visit';
    public const TYPE_REFERRAL = 'referral';
    public const TYPE_BIRTHDAY = 'birthday';
    public const TYPE_SIGNUP = 'signup';
    public const TYPE_FIRST_PURCHASE = 'first_purchase';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'type',
        'points_amount',
        'points_per_currency_unit',
        'min_spend_minor',
        'max_points_per_transaction',
        'treatment_id',
        'treatment_category_id',
        'multiplier',
        'conditions',
        'is_active',
        'priority',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'points_amount' => 'integer',
        'points_per_currency_unit' => 'decimal:2',
        'min_spend_minor' => 'integer',
        'max_points_per_transaction' => 'integer',
        'multiplier' => 'decimal:2',
        'conditions' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    protected $attributes = [
        'is_active' => true,
        'priority' => 0,
        'multiplier' => 1.0,
    ];

    public static function getTypes(): array
    {
        return [
            self::TYPE_PER_SPEND => __('loyalty::loyalty.rule_types.per_spend'),
            self::TYPE_PER_VISIT => __('loyalty::loyalty.rule_types.per_visit'),
            self::TYPE_REFERRAL => __('loyalty::loyalty.rule_types.referral'),
            self::TYPE_BIRTHDAY => __('loyalty::loyalty.rule_types.birthday'),
            self::TYPE_SIGNUP => __('loyalty::loyalty.rule_types.signup'),
            self::TYPE_FIRST_PURCHASE => __('loyalty::loyalty.rule_types.first_purchase'),
        ];
    }

    public static function getTypeColors(): array
    {
        return [
            self::TYPE_PER_SPEND => 'success',
            self::TYPE_PER_VISIT => 'info',
            self::TYPE_REFERRAL => 'warning',
            self::TYPE_BIRTHDAY => 'danger',
            self::TYPE_SIGNUP => 'primary',
            self::TYPE_FIRST_PURCHASE => 'gray',
        ];
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    public function treatmentCategory(): BelongsTo
    {
        return $this->belongsTo(TreatmentCategory::class);
    }

    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    public function calculatePoints(int $amountMinor): int
    {
        if (!$this->isValid()) {
            return 0;
        }

        // Check minimum spend
        if ($this->min_spend_minor && $amountMinor < $this->min_spend_minor) {
            return 0;
        }

        $points = 0;

        switch ($this->type) {
            case self::TYPE_PER_SPEND:
                // Calculate points based on spend amount
                if ($this->points_per_currency_unit > 0) {
                    // Convert minor to major units (e.g., cents to dollars)
                    $majorAmount = $amountMinor / 100;
                    $points = (int) floor($majorAmount * $this->points_per_currency_unit);
                }
                break;

            case self::TYPE_PER_VISIT:
            case self::TYPE_BIRTHDAY:
            case self::TYPE_SIGNUP:
            case self::TYPE_FIRST_PURCHASE:
            case self::TYPE_REFERRAL:
                // Fixed points amount
                $points = $this->points_amount ?? 0;
                break;
        }

        // Apply multiplier
        $points = (int) floor($points * ($this->multiplier ?? 1.0));

        // Cap at max points if set
        if ($this->max_points_per_transaction && $points > $this->max_points_per_transaction) {
            $points = $this->max_points_per_transaction;
        }

        return $points;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            });
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('priority', 'desc');
    }
}
