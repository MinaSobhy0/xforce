<?php

namespace Modules\Billing\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Services\Models\Service;

class InvoiceLine extends BaseModel
{
    use HasTenancy;

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'service_id',
        'description',
        'quantity',
        'unit_price_minor',
        'discount_minor',
        'discount_type',
        'tax_rate',
        'tax_minor',
        'total_minor',
        'package_subscription_id',
        'gift_card_id',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price_minor' => 'integer',
        'discount_minor' => 'integer',
        'tax_rate' => 'decimal:2',
        'tax_minor' => 'integer',
        'total_minor' => 'integer',
        'sort_order' => 'integer',
    ];

    public const DISCOUNT_FIXED = 'fixed';
    public const DISCOUNT_PERCENT = 'percent';

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (InvoiceLine $line) {
            $line->calculateTotals();
        });

        static::updating(function (InvoiceLine $line) {
            $line->calculateTotals();
        });

        static::saved(function (InvoiceLine $line) {
            $line->invoice?->recalculateTotals();
        });

        static::deleted(function (InvoiceLine $line) {
            $line->invoice?->recalculateTotals();
        });
    }

    // Relationships
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    // Calculate line totals
    public function calculateTotals(): void
    {
        $subtotal = (int) round($this->quantity * $this->unit_price_minor);

        // Apply line-level discount
        $discountAmount = 0;
        if ($this->discount_minor > 0) {
            if ($this->discount_type === self::DISCOUNT_PERCENT) {
                $discountAmount = (int) round($subtotal * $this->discount_minor / 100);
            } else {
                $discountAmount = $this->discount_minor;
            }
        }

        $afterDiscount = max(0, $subtotal - $discountAmount);

        // Calculate tax
        $taxRate = $this->tax_rate ?? 0;
        $this->tax_minor = (int) round($afterDiscount * $taxRate / 100);

        // Total is subtotal - discount + tax
        $this->total_minor = $afterDiscount + $this->tax_minor;
    }

    // Get subtotal before discount and tax
    public function getSubtotalMinorAttribute(): int
    {
        return (int) round($this->quantity * $this->unit_price_minor);
    }

    // Get effective discount amount
    public function getDiscountAmountAttribute(): int
    {
        if ($this->discount_minor <= 0) {
            return 0;
        }

        if ($this->discount_type === self::DISCOUNT_PERCENT) {
            return (int) round($this->subtotal_minor * $this->discount_minor / 100);
        }

        return $this->discount_minor;
    }
}
