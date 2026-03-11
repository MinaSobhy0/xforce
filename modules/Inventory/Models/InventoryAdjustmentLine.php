<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Services\StockMoveService;
use XLinic\Framework\Core\Model\BaseModel;

class InventoryAdjustmentLine extends BaseModel
{
    protected $table = 'inventory_adjustment_lines';

    protected $fillable = [
        'tenant_id',
        'inventory_adjustment_id',
        'product_id',
        'uom_id',
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

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
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
     * Odoo-like: Uses transfers between Inventory Adjustment Location and Internal Location
     */
    public function applyStockChange(): void
    {
        if ($this->isNoChange()) {
            return;
        }

        // Only for storable products
        if (!$this->product || !$this->product->tracksInventory()) {
            return;
        }

        $adjustment = $this->inventoryAdjustment;

        // Get the internal location for this adjustment
        $internalLocation = $adjustment->location_id
            ? StockLocation::find($adjustment->location_id)
            : StockLocation::getDefaultLocation($adjustment->branch_id);

        if (!$internalLocation) {
            return;
        }

        // Use StockMoveService for Odoo-like adjustment
        // Gain: Inventory Adjustment Location → Internal Location
        // Loss: Internal Location → Inventory Adjustment Location
        // Uses product's sales_uom (stock UOM) by default
        $stockMoveService = app(StockMoveService::class);
        $stockMoveService->createAdjustment(
            $this->product,
            $internalLocation,
            $this->difference_qty, // positive = gain, negative = loss
            null, // uom_id - null uses product's sales_uom
            'inventory_adjustment',
            $adjustment->id,
            "Inventory adjustment: {$adjustment->reference}"
        );
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
