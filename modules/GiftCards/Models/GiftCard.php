<?php

namespace Modules\GiftCards\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasSequence;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Patients\Models\Patient;
use Modules\Billing\Models\Invoice;

class GiftCard extends BaseModel
{
    use HasTenancy, HasSequence, HasActivity, SoftDeletes;

    protected string $sequenceCode = 'gift_card';
    protected string $sequenceColumn = 'code';

    protected $fillable = [
        'tenant_id',
        'code',
        'purchaser_patient_id',
        'recipient_patient_id',
        'initial_value_minor',
        'remaining_value_minor',
        'status',
        'purchased_via_invoice_id',
        'expires_at',
        'activated_at',
        'notes',
    ];

    protected $casts = [
        'initial_value_minor' => 'integer',
        'remaining_value_minor' => 'integer',
        'expires_at' => 'datetime',
        'activated_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PARTIALLY_USED = 'partially_used';
    public const STATUS_FULLY_USED = 'fully_used';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_PARTIALLY_USED => 'Partially Used',
        self::STATUS_FULLY_USED => 'Fully Used',
        self::STATUS_EXPIRED => 'Expired',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const STATUS_COLORS = [
        self::STATUS_DRAFT => 'gray',
        self::STATUS_ACTIVE => 'success',
        self::STATUS_PARTIALLY_USED => 'warning',
        self::STATUS_FULLY_USED => 'info',
        self::STATUS_EXPIRED => 'danger',
        self::STATUS_CANCELLED => 'gray',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (GiftCard $giftCard) {
            if (empty($giftCard->status)) {
                $giftCard->status = self::STATUS_DRAFT;
            }
            if (empty($giftCard->remaining_value_minor)) {
                $giftCard->remaining_value_minor = $giftCard->initial_value_minor;
            }
            // Set default expiry
            if (empty($giftCard->expires_at)) {
                $expiryDays = config('giftcards.default_expiry_days', 365);
                if ($expiryDays) {
                    $giftCard->expires_at = now()->addDays($expiryDays);
                }
            }
        });
    }

    // Relationships
    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'purchaser_patient_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'recipient_patient_id');
    }

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'purchased_via_invoice_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(GiftCardTransaction::class)->orderBy('created_at', 'desc');
    }

    // Accessors
    public function getFormattedInitialValueAttribute(): string
    {
        return number_format($this->initial_value_minor / 100, 2) . ' ' . config('app.currency_symbol', 'EGP');
    }

    public function getFormattedRemainingValueAttribute(): string
    {
        return number_format($this->remaining_value_minor / 100, 2) . ' ' . config('app.currency_symbol', 'EGP');
    }

    public function getUsedValueMinorAttribute(): int
    {
        return $this->initial_value_minor - $this->remaining_value_minor;
    }

    public function getUsagePercentageAttribute(): float
    {
        if ($this->initial_value_minor <= 0) {
            return 100;
        }
        return round((($this->initial_value_minor - $this->remaining_value_minor) / $this->initial_value_minor) * 100, 1);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }
        return max(0, now()->diffInDays($this->expires_at, false));
    }

    public function getOwnerAttribute(): ?Patient
    {
        return $this->recipient ?? $this->purchaser;
    }

    // Status checks
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPartiallyUsed(): bool
    {
        return $this->status === self::STATUS_PARTIALLY_USED;
    }

    public function isFullyUsed(): bool
    {
        return $this->status === self::STATUS_FULLY_USED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canRedeem(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_PARTIALLY_USED])
            && $this->remaining_value_minor > 0
            && (!$this->expires_at || !$this->expires_at->isPast());
    }

    public function hasBalance(): bool
    {
        return $this->remaining_value_minor > 0;
    }

    // State transitions
    public function canTransitionTo(string $status): bool
    {
        $transitions = [
            self::STATUS_DRAFT => [self::STATUS_ACTIVE, self::STATUS_CANCELLED],
            self::STATUS_ACTIVE => [self::STATUS_PARTIALLY_USED, self::STATUS_FULLY_USED, self::STATUS_EXPIRED, self::STATUS_CANCELLED],
            self::STATUS_PARTIALLY_USED => [self::STATUS_FULLY_USED, self::STATUS_EXPIRED, self::STATUS_CANCELLED],
            self::STATUS_FULLY_USED => [],
            self::STATUS_EXPIRED => [],
            self::STATUS_CANCELLED => [],
        ];

        return in_array($status, $transitions[$this->status] ?? []);
    }

    // Actions
    public function activate(): bool
    {
        if (!$this->canTransitionTo(self::STATUS_ACTIVE)) {
            return false;
        }

        $this->status = self::STATUS_ACTIVE;
        $this->activated_at = now();
        $saved = $this->save();

        if ($saved) {
            // Record activation transaction
            $this->recordTransaction(
                GiftCardTransaction::TYPE_ACTIVATE,
                $this->initial_value_minor,
                'Gift card activated'
            );
        }

        return $saved;
    }

    public function redeem(int $amountMinor, ?string $invoiceId = null, ?string $paymentId = null, ?string $notes = null): bool
    {
        if (!$this->canRedeem()) {
            return false;
        }

        $amountToRedeem = min($amountMinor, $this->remaining_value_minor);
        $this->remaining_value_minor -= $amountToRedeem;

        // Update status based on remaining balance
        if ($this->remaining_value_minor <= 0) {
            $this->status = self::STATUS_FULLY_USED;
        } elseif ($this->status === self::STATUS_ACTIVE) {
            $this->status = self::STATUS_PARTIALLY_USED;
        }

        $saved = $this->save();

        if ($saved) {
            $this->recordTransaction(
                GiftCardTransaction::TYPE_REDEEM,
                -$amountToRedeem,
                $notes ?? 'Redemption',
                $invoiceId,
                $paymentId
            );
        }

        return $saved;
    }

    public function refund(int $amountMinor, ?string $notes = null): bool
    {
        $newBalance = $this->remaining_value_minor + $amountMinor;

        // Cannot refund more than initial value
        if ($newBalance > $this->initial_value_minor) {
            return false;
        }

        $this->remaining_value_minor = $newBalance;

        // Update status based on remaining balance
        if ($this->remaining_value_minor >= $this->initial_value_minor) {
            $this->status = self::STATUS_ACTIVE;
        } elseif ($this->remaining_value_minor > 0) {
            $this->status = self::STATUS_PARTIALLY_USED;
        }

        $saved = $this->save();

        if ($saved) {
            $this->recordTransaction(
                GiftCardTransaction::TYPE_REFUND,
                $amountMinor,
                $notes ?? 'Refund'
            );
        }

        return $saved;
    }

    public function adjust(int $amountMinor, string $reason): bool
    {
        $newBalance = $this->remaining_value_minor + $amountMinor;

        if ($newBalance < 0 || $newBalance > $this->initial_value_minor) {
            return false;
        }

        $this->remaining_value_minor = $newBalance;

        $saved = $this->save();

        if ($saved) {
            $this->recordTransaction(
                GiftCardTransaction::TYPE_ADJUST,
                $amountMinor,
                $reason
            );
        }

        return $saved;
    }

    public function expire(): bool
    {
        if (!$this->canTransitionTo(self::STATUS_EXPIRED)) {
            return false;
        }

        $this->status = self::STATUS_EXPIRED;
        $saved = $this->save();

        if ($saved && $this->remaining_value_minor > 0) {
            $this->recordTransaction(
                GiftCardTransaction::TYPE_EXPIRE,
                -$this->remaining_value_minor,
                'Gift card expired'
            );
        }

        return $saved;
    }

    public function cancel(?string $reason = null): bool
    {
        if (!$this->canTransitionTo(self::STATUS_CANCELLED)) {
            return false;
        }

        $this->status = self::STATUS_CANCELLED;
        return $this->save();
    }

    public function checkAndMarkExpired(): bool
    {
        if ($this->expires_at?->isPast() && in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_PARTIALLY_USED])) {
            return $this->expire();
        }
        return false;
    }

    protected function recordTransaction(
        string $type,
        int $amountMinor,
        ?string $notes = null,
        ?string $invoiceId = null,
        ?string $paymentId = null
    ): GiftCardTransaction {
        return $this->transactions()->create([
            'tenant_id' => $this->tenant_id,
            'type' => $type,
            'amount_minor' => $amountMinor,
            'running_balance_minor' => $this->remaining_value_minor,
            'invoice_id' => $invoiceId,
            'payment_id' => $paymentId,
            'notes' => $notes,
            'created_by_user_id' => auth()->id(),
        ]);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_PARTIALLY_USED]);
    }

    public function scopeWithBalance($query)
    {
        return $query->where('remaining_value_minor', '>', 0);
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->active()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days));
    }

    public function scopeForPatient($query, string $patientId)
    {
        return $query->where(function ($q) use ($patientId) {
            $q->where('purchaser_patient_id', $patientId)
                ->orWhere('recipient_patient_id', $patientId);
        });
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('code', 'ilike', "%{$term}%");
        });
    }
}
