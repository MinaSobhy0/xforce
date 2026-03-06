<?php

namespace Modules\Booking\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockLevel;
use Modules\Inventory\Models\StockMovement;
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
        'unit',
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
     */
    public function returnToInventory(): void
    {
        // Only return if it was deducted
        if (!$this->is_deducted) {
            return;
        }

        // Get stock level for product and branch
        $stockLevel = StockLevel::getOrCreate(
            $this->product_id,
            $this->branch_id,
            $this->tenant_id
        );

        // Increase stock
        $stockLevel->increase(
            (int) $this->quantity,
            StockMovement::TYPE_IN,
            'session_product_return',
            (string) $this->id,
            'Product returned from cancelled checkout - Appointment #' . $this->appointment_id
        );

        // Mark as not deducted
        $this->update([
            'is_deducted' => false,
            'deducted_at' => null,
        ]);
    }
}
