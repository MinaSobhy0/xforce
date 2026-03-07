<?php

namespace Modules\Booking\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockLevel;
use Modules\Inventory\Models\StockLocation;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Services\StockMoveService;
use Modules\Core\Models\Branch;
use Modules\Auth\Models\User;

class SessionProduct extends BaseModel
{
    use HasTenancy;

    protected $table = 'session_products';

    // Usage types
    public const USAGE_APPLIED = 'applied';  // Used on patient during treatment
    public const USAGE_SOLD = 'sold';        // Sold to patient (take-home)

    public const USAGE_TYPES = [
        self::USAGE_APPLIED => 'Applied during treatment',
        self::USAGE_SOLD => 'Sold to patient',
    ];

    protected $fillable = [
        'tenant_id',
        'appointment_id',
        'session_data_id',
        'visit_id',
        'product_id',
        'branch_id',
        'quantity',
        'uom_id',
        'unit', // Deprecated: use uom_id instead
        'unit_price_minor',
        'total_price_minor',
        'discount_type',
        'discount_value',
        'discount_minor',
        'usage_type',
        'notes',
        'is_invoiced',
        'invoice_line_id',
        'is_deducted',
        'deducted_at',
        'stock_movement_id',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price_minor' => 'integer',
        'total_price_minor' => 'integer',
        'discount_value' => 'decimal:2',
        'discount_minor' => 'integer',
        'is_invoiced' => 'boolean',
        'is_deducted' => 'boolean',
        'deducted_at' => 'datetime',
    ];

    protected $attributes = [
        'usage_type' => self::USAGE_APPLIED,
        'discount_type' => 'none',
        'discount_value' => 0,
        'discount_minor' => 0,
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::saving(function (self $model) {
            // Calculate discount_minor based on discount_type and discount_value
            $subtotal = (int) ($model->quantity * $model->unit_price_minor);

            if ($model->discount_type === 'percent' && $model->discount_value > 0) {
                $model->discount_minor = (int) round($subtotal * $model->discount_value / 100);
            } elseif ($model->discount_type === 'fixed' && $model->discount_value > 0) {
                // Fixed discount value is in major units, convert to minor
                $model->discount_minor = min((int) ($model->discount_value * 100), $subtotal);
            } else {
                $model->discount_minor = 0;
            }

            // Auto-calculate total price
            $model->total_price_minor = max(0, $subtotal - $model->discount_minor);
        });
    }

    /**
     * Get the appointment this product belongs to.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Get the visit this product belongs to.
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * Get the product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the branch from which stock was deducted.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the user who created this record.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the unit of measure.
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    /**
     * Get the stock movement record for this product.
     */
    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    /**
     * Get the unit abbreviation for display.
     * Uses uom relationship if set, otherwise falls back to product's UoM, then legacy unit field.
     */
    public function getUnitAbbreviationAttribute(): string
    {
        if ($this->uom) {
            return $this->uom->abbreviation;
        }

        if ($this->product?->salesUom) {
            return $this->product->salesUom->abbreviation;
        }

        return $this->unit ?? 'pcs';
    }

    /**
     * Get the unit name for display.
     * Uses uom relationship if set, otherwise falls back to product's UoM.
     */
    public function getUnitNameAttribute(): string
    {
        if ($this->uom) {
            return $this->uom->getTranslation('name', app()->getLocale());
        }

        if ($this->product?->salesUom) {
            return $this->product->salesUom->getTranslation('name', app()->getLocale());
        }

        return Product::UNITS[$this->unit] ?? $this->unit ?? 'Pieces';
    }

    /**
     * Get unit price in major units (for display).
     */
    public function getUnitPriceAttribute(): float
    {
        return $this->unit_price_minor / 100;
    }

    /**
     * Get total price in major units (for display).
     */
    public function getTotalPriceAttribute(): float
    {
        return $this->total_price_minor / 100;
    }

    /**
     * Get discount in major units (for display).
     */
    public function getDiscountAttribute(): float
    {
        return $this->discount_minor / 100;
    }

    /**
     * Get formatted total price with currency.
     */
    public function getFormattedTotalPriceAttribute(): string
    {
        return number_format($this->total_price, 2) . ' ' . current_currency();
    }

    /**
     * Check if this is an applied product.
     */
    public function isApplied(): bool
    {
        return $this->usage_type === self::USAGE_APPLIED;
    }

    /**
     * Check if this is a sold product.
     */
    public function isSold(): bool
    {
        return $this->usage_type === self::USAGE_SOLD;
    }

    /**
     * Scope to applied products.
     */
    public function scopeApplied($query)
    {
        return $query->where('usage_type', self::USAGE_APPLIED);
    }

    /**
     * Scope to sold products.
     */
    public function scopeSold($query)
    {
        return $query->where('usage_type', self::USAGE_SOLD);
    }

    /**
     * Scope to not yet invoiced items.
     */
    public function scopePendingInvoice($query)
    {
        return $query->where('is_invoiced', false);
    }

    /**
     * Scope to not yet deducted items.
     */
    public function scopePendingDeduction($query)
    {
        return $query->where('is_deducted', false);
    }

    /**
     * Mark as deducted from inventory.
     */
    public function markAsDeducted(): void
    {
        $this->update([
            'is_deducted' => true,
            'deducted_at' => now(),
        ]);
    }

    /**
     * Mark as invoiced.
     */
    public function markAsInvoiced(?string $invoiceLineId = null): void
    {
        $this->update([
            'is_invoiced' => true,
            'invoice_line_id' => $invoiceLineId,
        ]);
    }

    /**
     * Return product to inventory.
     * Used when a sold product is cancelled at checkout.
     * Odoo-like: Creates reverse transfer (Customer → Treatment Location)
     */
    public function returnToInventory(): void
    {
        // Only return if it was deducted
        if (!$this->is_deducted) {
            return;
        }

        // Only for storable products
        if (!$this->product || !$this->product->tracksInventory()) {
            return;
        }

        // Get the treatment default location for this branch
        $destinationLocation = StockLocation::getTreatmentDefaultLocation($this->branch_id);

        if (!$destinationLocation) {
            $destinationLocation = StockLocation::getDefaultLocation($this->branch_id);
        }

        if (!$destinationLocation) {
            return;
        }

        // Use StockMoveService for Odoo-like return (Customer → Treatment Location)
        $stockMoveService = app(StockMoveService::class);
        $stockMoveService->createCustomerReturn(
            $this->product,
            $destinationLocation,
            (int) $this->quantity,
            'session_product_return',
            (string) $this->id,
            'Product returned from cancelled checkout - Appointment #' . $this->appointment_id
        );

        // Mark as not deducted
        $this->update([
            'is_deducted' => false,
            'deducted_at' => null,
            'stock_movement_id' => null,
        ]);
    }
}
