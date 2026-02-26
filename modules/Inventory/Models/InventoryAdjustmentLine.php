<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use XLinic\Framework\Core\Model\BaseModel;

class InventoryAdjustmentLine extends BaseModel
{
    protected $table = 'inventory_adjustment_lines';

    protected $fillable = [
        'tenant_id',
        'inventory_adjustment_id',
        'product_id',
        'theoretical_qty',
        'counted_qty',
        'difference_qty',
        'unit_cost_minor',
        'value_adjustment_minor',
        'notes',
    ];

    protected $casts = [
        'theoretical_qty' => 'integer',
        'counted_qty' => 'integer',
        'difference_qty' => 'integer',
        'unit_cost_minor' => 'integer',
        'value_adjustment_minor' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function inventoryAdjustment(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustment::class, 'inventory_adjustment_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getUnitCostAttribute(): float
    {
        return $this->unit_cost_minor / 100;
    }

    public function getValueAdjustmentAttribute(): float
    {
        return $this->value_adjustment_minor / 100;
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Calculate difference and value adjustment.
     */
    public function calculateDifference(): void
    {
        $this->difference_qty = $this->counted_qty - $this->theoretical_qty;
        $this->value_adjustment_minor = $this->difference_qty * $this->unit_cost_minor;
    }

    /**
     * Check if this is a positive adjustment (stock increase).
     */
    public function isPositiveAdjustment(): bool
    {
        return $this->difference_qty > 0;
    }

    /**
     * Check if this is a negative adjustment (stock decrease).
     */
    public function isNegativeAdjustment(): bool
    {
        return $this->difference_qty < 0;
    }

    /**
     * Check if there's no adjustment needed.
     */
    public function isNoChange(): bool
    {
        return $this->difference_qty === 0;
    }

    /**
     * Apply the stock change to inventory.
     */
    public function applyStockChange(): void
    {
        if ($this->isNoChange()) {
            return;
        }

        $adjustment = $this->inventoryAdjustment;
        $stockLevel = StockLevel::getOrCreate($this->product_id, $adjustment->branch_id);

        if ($this->isPositiveAdjustment()) {
            $stockLevel->increase(
                abs($this->difference_qty),
                StockMovement::TYPE_ADJUSTMENT,
                'inventory_adjustment',
                $adjustment->id,
                "Inventory adjustment: {$adjustment->reference}"
            );
        } else {
            $stockLevel->decrease(
                abs($this->difference_qty),
                StockMovement::TYPE_ADJUSTMENT,
                'inventory_adjustment',
                $adjustment->id,
                "Inventory adjustment: {$adjustment->reference}"
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        parent::booted();

        // Auto-calculate difference on save
        static::saving(function (self $model) {
            $model->calculateDifference();
        });

        // Recalculate parent totals after save
        static::saved(function (self $model) {
            $model->inventoryAdjustment?->recalculateTotals();
        });

        static::deleted(function (self $model) {
            $model->inventoryAdjustment?->recalculateTotals();
        });
    }
}
