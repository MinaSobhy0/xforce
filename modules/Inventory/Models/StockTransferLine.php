<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use XLinic\Framework\Core\Model\BaseModel;

class StockTransferLine extends BaseModel
{
    protected $table = 'stock_transfer_lines';

    protected $fillable = [
        'tenant_id',
        'stock_transfer_id',
        'product_id',
        'uom_id',
        'quantity_planned',
        'quantity_done',
        'unit_cost_minor',
        'stock_movement_id',
    ];

    protected $casts = [
        'quantity_planned' => 'integer',
        'quantity_done' => 'integer',
        'unit_cost_minor' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'quantity_planned' => 0,
        'quantity_done' => 0,
    ];

    /**
     * Get the transfer.
     */
    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    /**
     * Get the product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the UOM used for this line.
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    /**
     * Get the stock movement.
     */
    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    /**
     * Get the remaining quantity to transfer.
     */
    public function getRemainingQuantityAttribute(): int
    {
        return max(0, $this->quantity_planned - $this->quantity_done);
    }

    /**
     * Check if this line is fully transferred.
     */
    public function isFullyTransferred(): bool
    {
        return $this->quantity_done >= $this->quantity_planned;
    }

    /**
     * Check if this line is partially transferred.
     */
    public function isPartiallyTransferred(): bool
    {
        return $this->quantity_done > 0 && $this->quantity_done < $this->quantity_planned;
    }

    /**
     * Get available stock at source location.
     */
    public function getAvailableStockAttribute(): int
    {
        $transfer = $this->stockTransfer;
        if (!$transfer) {
            return 0;
        }

        $stockLevel = StockLevel::where('product_id', $this->product_id)
            ->where('branch_id', $transfer->branch_id)
            ->where('location_id', $transfer->source_location_id)
            ->first();

        return $stockLevel?->quantity_on_hand ?? 0;
    }

    /**
     * Convert quantity to stock UOM (product's sales_uom).
     *
     * @param float $quantity Quantity in the line's UOM
     * @return float Quantity in stock UOM
     */
    public function convertToStockUom(float $quantity): float
    {
        $product = $this->product;
        $lineUom = $this->uom;
        $stockUom = $product?->salesUom;

        // If no UOMs defined or same UOM, return as-is
        if (!$lineUom || !$stockUom || $lineUom->id === $stockUom->id) {
            return $quantity;
        }

        return $lineUom->convertTo($quantity, $stockUom);
    }

    /**
     * Get quantity_done converted to stock UOM.
     */
    public function getQuantityDoneInStockUomAttribute(): float
    {
        return $this->convertToStockUom((float) $this->quantity_done);
    }

    /**
     * Get the UOM abbreviation for display.
     */
    public function getUomAbbreviationAttribute(): string
    {
        return $this->uom?->abbreviation ?? $this->product?->unitAbbreviation ?? 'pcs';
    }
}
