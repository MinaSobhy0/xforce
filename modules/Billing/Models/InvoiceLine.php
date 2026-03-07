<?php

namespace Modules\Billing\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Booking\Models\Appointment;
use Modules\Booking\Models\SessionProduct;
use Modules\Inventory\Models\Product;
use Modules\Services\Models\Service;
use Modules\TreatmentPlans\Models\TreatmentPlanItem;

class InvoiceLine extends BaseModel
{
    use HasTenancy;

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'service_id',
        'product_id',
        'session_product_id',
        'account_id',
        'treatment_plan_item_id',
        'appointment_id',
        'line_type',
        'description',
        'quantity',
        'unit_price_minor',
        'discount_minor',
        'discount_type',
        'tax_rates',
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
        'tax_rates' => 'array',
        'tax_minor' => 'integer',
        'total_minor' => 'integer',
        'sort_order' => 'integer',
    ];

    public const DISCOUNT_FIXED = 'fixed';
    public const DISCOUNT_PERCENT = 'percent';

    // Line types
    public const LINE_TYPE_SERVICE = 'service';
    public const LINE_TYPE_PRODUCT = 'product';
    public const LINE_TYPE_PACKAGE = 'package';
    public const LINE_TYPE_OTHER = 'other';

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

            // Update treatment plan item invoiced quantity
            if ($line->treatment_plan_item_id && $line->wasRecentlyCreated) {
                $line->treatmentPlanItem?->increment('invoiced_quantity', (int) $line->quantity);
            }

            // Note: Stock transfers are created when the invoice is ISSUED,
            // not when lines are created. See Invoice::deductStockForProductLines()
        });

        static::deleted(function (InvoiceLine $line) {
            $line->invoice?->recalculateTotals();

            // Reverse treatment plan item invoiced quantity
            if ($line->treatment_plan_item_id) {
                $line->treatmentPlanItem?->decrement('invoiced_quantity', (int) $line->quantity);
            }
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

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function treatmentPlanItem(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlanItem::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
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

        // Calculate tax - sum all tax rates
        $taxRates = $this->tax_rates ?? [];
        $totalTaxPercent = array_sum(array_map('floatval', $taxRates));
        $this->tax_minor = (int) round($afterDiscount * $totalTaxPercent / 100);

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

    // Product relationship
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Session product relationship (links to upsold product during session)
    public function sessionProduct(): BelongsTo
    {
        return $this->belongsTo(SessionProduct::class);
    }

    // Scopes for filtering by line type
    public function scopeServices(Builder $query): Builder
    {
        return $query->where('line_type', self::LINE_TYPE_SERVICE);
    }

    public function scopeProducts(Builder $query): Builder
    {
        return $query->where('line_type', self::LINE_TYPE_PRODUCT);
    }

    public function scopePackages(Builder $query): Builder
    {
        return $query->where('line_type', self::LINE_TYPE_PACKAGE);
    }

    // Check if this is a service line
    public function isService(): bool
    {
        return $this->line_type === self::LINE_TYPE_SERVICE;
    }

    // Check if this is a product line
    public function isProduct(): bool
    {
        return $this->line_type === self::LINE_TYPE_PRODUCT;
    }
}
