<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Models\ChartOfAccount;
use XLinic\Framework\Core\Model\BaseModel;

class VendorBillLine extends BaseModel
{
    protected $table = 'vendor_bill_lines';

    protected $fillable = [
        'tenant_id',
        'vendor_bill_id',
        'product_id',
        'account_id',
        'purchase_order_line_id',
        'description',
        'quantity',
        'unit_price_minor',
        'discount_minor',
        'discount_type',
        'tax_rate',
        'tax_minor',
        'total_minor',
        'sort_order',
    ];

    protected $casts = [
        'id' => 'string',
        'quantity' => 'decimal:2',
        'unit_price_minor' => 'integer',
        'discount_minor' => 'integer',
        'tax_rate' => 'decimal:2',
        'tax_minor' => 'integer',
        'total_minor' => 'integer',
        'sort_order' => 'integer',
    ];

    // Discount type constants
    public const DISCOUNT_FIXED = 'fixed';
    public const DISCOUNT_PERCENT = 'percent';

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function vendorBill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getUnitPriceAttribute(): float
    {
        return $this->unit_price_minor / 100;
    }

    public function getSubtotalMinorAttribute(): int
    {
        return (int) round($this->quantity * $this->unit_price_minor);
    }

    public function getSubtotalAttribute(): float
    {
        return $this->subtotal_minor / 100;
    }

    public function getTotalAttribute(): float
    {
        return $this->total_minor / 100;
    }

    public function getEffectiveDiscountMinorAttribute(): int
    {
        if ($this->discount_minor <= 0) {
            return 0;
        }

        if ($this->discount_type === self::DISCOUNT_PERCENT) {
            return (int) round($this->subtotal_minor * $this->discount_minor / 100);
        }

        return $this->discount_minor;
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    public function calculateTotals(): void
    {
        $subtotal = $this->subtotal_minor;

        // Apply discount
        $discountAmount = $this->effective_discount_minor;
        $afterDiscount = max(0, $subtotal - $discountAmount);

        // Calculate tax
        $this->tax_minor = (int) round($afterDiscount * $this->tax_rate / 100);

        // Total is subtotal - discount + tax
        $this->total_minor = $afterDiscount + $this->tax_minor;
    }

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        parent::booted();

        static::saving(function (self $line) {
            $line->calculateTotals();
        });

        static::saved(function (self $line) {
            $line->vendorBill?->recalculateTotals();
        });

        static::deleted(function (self $line) {
            $line->vendorBill?->recalculateTotals();
        });
    }
}
