<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use XLinic\Framework\Core\Model\BaseModel;

class StockTransferLine extends BaseModel
{
    protected $table = 'stock_transfer_lines';

    protected $fillable = [
        'stock_transfer_id',
        'product_id',
        'quantity_planned',
        'quantity_done',
    ];

    protected $casts = [
        'quantity_planned' => 'integer',
        'quantity_done' => 'integer',
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
}
