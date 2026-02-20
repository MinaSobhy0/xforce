<?php

namespace Modules\GiftCards\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Auth\Models\User;

class GiftCardTransaction extends BaseModel
{
    use HasTenancy;

    protected $fillable = [
        'tenant_id',
        'gift_card_id',
        'type',
        'amount_minor',
        'running_balance_minor',
        'invoice_id',
        'payment_id',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'running_balance_minor' => 'integer',
    ];

    // Transaction types
    public const TYPE_ACTIVATE = 'activate';
    public const TYPE_REDEEM = 'redeem';
    public const TYPE_REFUND = 'refund';
    public const TYPE_ADJUST = 'adjust';
    public const TYPE_EXPIRE = 'expire';

    public const TYPES = [
        self::TYPE_ACTIVATE => 'Activation',
        self::TYPE_REDEEM => 'Redemption',
        self::TYPE_REFUND => 'Refund',
        self::TYPE_ADJUST => 'Adjustment',
        self::TYPE_EXPIRE => 'Expiration',
    ];

    public const TYPE_COLORS = [
        self::TYPE_ACTIVATE => 'success',
        self::TYPE_REDEEM => 'warning',
        self::TYPE_REFUND => 'info',
        self::TYPE_ADJUST => 'gray',
        self::TYPE_EXPIRE => 'danger',
    ];

    // Relationships
    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // Accessors
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getTypeColorAttribute(): string
    {
        return self::TYPE_COLORS[$this->type] ?? 'gray';
    }

    public function getFormattedAmountAttribute(): string
    {
        $prefix = $this->amount_minor >= 0 ? '+' : '';
        return $prefix . number_format($this->amount_minor / 100, 2) . ' ' . config('app.currency_symbol', 'EGP');
    }

    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->running_balance_minor / 100, 2) . ' ' . config('app.currency_symbol', 'EGP');
    }

    public function isDebit(): bool
    {
        return $this->amount_minor < 0;
    }

    public function isCredit(): bool
    {
        return $this->amount_minor >= 0;
    }

    // Scopes
    public function scopeForGiftCard($query, string $giftCardId)
    {
        return $query->where('gift_card_id', $giftCardId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRedemptions($query)
    {
        return $query->where('type', self::TYPE_REDEEM);
    }

    public function scopeForDateRange($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }
}
