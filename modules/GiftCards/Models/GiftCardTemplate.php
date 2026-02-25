<?php

namespace Modules\GiftCards\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasSequence;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;
use Modules\Auth\Models\User;
use Carbon\Carbon;

class GiftCardTemplate extends BaseModel
{
    use HasTenancy, HasSequence, HasActivity, SoftDeletes;

    protected $table = 'gc_templates';

    protected string $sequenceCode = 'gc_template';
    protected string $sequenceColumn = 'code';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'amount_minor',
        'validity_days',
        'discount_type',
        'discount_value',
        'card_design',
        'allow_partial_redemption',
        'requires_activation',
        'is_active',
        'liability_account_id',
        'expense_account_id',
        'sales_journal_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'card_design' => 'array',
        'amount_minor' => 'integer',
        'validity_days' => 'integer',
        'discount_value' => 'integer',
        'allow_partial_redemption' => 'boolean',
        'requires_activation' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Discount types
    public const DISCOUNT_PERCENTAGE = 'percentage';
    public const DISCOUNT_FIXED = 'fixed';

    public const DISCOUNT_TYPES = [
        self::DISCOUNT_PERCENTAGE => 'Percentage',
        self::DISCOUNT_FIXED => 'Fixed Amount',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (GiftCardTemplate $template) {
            if (empty($template->created_by_user_id)) {
                $template->created_by_user_id = auth()->id();
            }
        });
    }

    // Relationships
    public function giftCards(): HasMany
    {
        return $this->hasMany(GiftCard::class, 'template_id');
    }

    public function liabilityAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'liability_account_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }

    public function salesJournal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'sales_journal_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // Accessors
    public function getFormattedAmountAttribute(): string
    {
        return format_money($this->amount_minor);
    }

    public function getDiscountTypeLabelAttribute(): ?string
    {
        return self::DISCOUNT_TYPES[$this->discount_type] ?? null;
    }

    // Methods
    public function getDiscountedPrice(): int
    {
        if (!$this->discount_type || $this->discount_value <= 0) {
            return $this->amount_minor;
        }

        if ($this->discount_type === self::DISCOUNT_PERCENTAGE) {
            $discount = (int) round($this->amount_minor * $this->discount_value / 100);
            return $this->amount_minor - $discount;
        }

        if ($this->discount_type === self::DISCOUNT_FIXED) {
            return max(0, $this->amount_minor - $this->discount_value);
        }

        return $this->amount_minor;
    }

    public function calculateExpiryDate(): Carbon
    {
        return now()->addDays($this->validity_days ?? 365);
    }

    public function getStatistics(): array
    {
        $cards = $this->giftCards();

        $totalIssued = $cards->count();
        $totalIssuedValue = $cards->sum('initial_value_minor');

        $activeCards = (clone $cards)->whereIn('status', [
            GiftCard::STATUS_ACTIVE,
            GiftCard::STATUS_PARTIALLY_USED,
        ]);
        $activeCount = $activeCards->count();
        $outstandingBalance = $activeCards->sum('remaining_value_minor');

        $redeemedCards = (clone $cards)->whereIn('status', [
            GiftCard::STATUS_PARTIALLY_USED,
            GiftCard::STATUS_FULLY_USED,
        ]);
        $redeemedValue = $redeemedCards->sum('initial_value_minor')
            - $redeemedCards->sum('remaining_value_minor');

        $expiredCards = (clone $cards)->where('status', GiftCard::STATUS_EXPIRED);
        $expiredCount = $expiredCards->count();
        $breakageValue = $expiredCards->sum('remaining_value_minor');

        return [
            'total_issued' => $totalIssued,
            'total_issued_value' => $totalIssuedValue,
            'active_count' => $activeCount,
            'outstanding_balance' => $outstandingBalance,
            'redeemed_value' => $redeemedValue,
            'expired_count' => $expiredCount,
            'breakage_value' => $breakageValue,
            'redemption_rate' => $totalIssuedValue > 0
                ? round(($redeemedValue / $totalIssuedValue) * 100, 1)
                : 0,
        ];
    }
}
