<?php

namespace Modules\Booking\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Uom;
use Modules\Core\Models\Branch;
use Modules\Auth\Models\User;

class SessionConsumable extends BaseModel
{
    use HasTenancy;

    protected $table = 'session_consumables';

    protected $fillable = [
        'tenant_id',
        'appointment_id',
        'product_id',
        'branch_id',
        'quantity',
        'base_quantity',
        'uom_id',
        'unit', // Deprecated: use uom_id instead
        'unit_cost_minor',
        'total_cost_minor',
        'notes',
        'is_deducted',
        'deducted_at',
        'deducted_by',
        'stock_movement_id',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'base_quantity' => 'decimal:2',
        'unit_cost_minor' => 'integer',
        'total_cost_minor' => 'integer',
        'is_deducted' => 'boolean',
        'deducted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::saving(function (self $model) {
            // Auto-calculate total cost
            $model->total_cost_minor = (int) ($model->quantity * $model->unit_cost_minor);
        });
    }

    /**
     * Get the appointment this consumable belongs to.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
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
     * Get the user who deducted the stock.
     */
    public function deductedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deducted_by');
    }

    /**
     * Get the stock movement record for this consumable.
     */
    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    /**
     * Get the unit of measure.
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
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
     * Get unit cost in major units (for display).
     */
    public function getUnitCostAttribute(): float
    {
        return $this->unit_cost_minor / 100;
    }

    /**
     * Get total cost in major units (for display).
     */
    public function getTotalCostAttribute(): float
    {
        return $this->total_cost_minor / 100;
    }

    /**
     * Get formatted total cost with currency.
     */
    public function getFormattedTotalCostAttribute(): string
    {
        return number_format($this->total_cost, 2) . ' ' . current_currency();
    }

    /**
     * Scope to not yet deducted items.
     */
    public function scopePendingDeduction($query)
    {
        return $query->where('is_deducted', false);
    }

    /**
     * Scope to deducted items.
     */
    public function scopeDeducted($query)
    {
        return $query->where('is_deducted', true);
    }

    /**
     * Mark as deducted from inventory.
     */
    public function markAsDeducted(?string $userId = null): void
    {
        $this->update([
            'is_deducted' => true,
            'deducted_at' => now(),
            'deducted_by' => $userId ?? auth()->id(),
        ]);
    }
}
