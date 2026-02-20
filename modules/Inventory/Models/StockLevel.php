<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Branch;
use XLinic\Framework\Core\Model\BaseModel;

class StockLevel extends BaseModel
{
    protected $table = 'stock_levels';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'branch_id',
        'quantity_on_hand',
        'quantity_reserved',
        'quantity_on_order',
        'last_restock_at',
        'last_count_at',
    ];

    protected $casts = [
        'id' => 'string',
        'quantity_on_hand' => 'integer',
        'quantity_reserved' => 'integer',
        'quantity_on_order' => 'integer',
        'last_restock_at' => 'datetime',
        'last_count_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'quantity_on_hand' => 0,
        'quantity_reserved' => 0,
        'quantity_on_order' => 0,
    ];

    /**
     * Get the product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the branch.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get available quantity (on hand minus reserved).
     */
    public function getAvailableQuantityAttribute(): int
    {
        return max(0, $this->quantity_on_hand - $this->quantity_reserved);
    }

    /**
     * Check if stock is low.
     */
    public function isLowStock(): bool
    {
        return $this->quantity_on_hand <= ($this->product->reorder_point ?? 10);
    }

    /**
     * Check if stock is out.
     */
    public function isOutOfStock(): bool
    {
        return $this->quantity_on_hand <= 0;
    }

    /**
     * Increase stock.
     */
    public function increase(int $quantity, string $movementType = StockMovement::TYPE_IN, ?string $referenceType = null, ?string $referenceId = null, ?string $notes = null): StockMovement
    {
        $this->quantity_on_hand += $quantity;
        $this->last_restock_at = now();
        $this->save();

        return StockMovement::create([
            'tenant_id' => $this->tenant_id,
            'product_id' => $this->product_id,
            'branch_id' => $this->branch_id,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'quantity_before' => $this->quantity_on_hand - $quantity,
            'quantity_after' => $this->quantity_on_hand,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
        ]);
    }

    /**
     * Decrease stock.
     */
    public function decrease(int $quantity, string $movementType = StockMovement::TYPE_OUT, ?string $referenceType = null, ?string $referenceId = null, ?string $notes = null): StockMovement
    {
        $previousQuantity = $this->quantity_on_hand;
        $this->quantity_on_hand = max(0, $this->quantity_on_hand - $quantity);
        $this->save();

        return StockMovement::create([
            'tenant_id' => $this->tenant_id,
            'product_id' => $this->product_id,
            'branch_id' => $this->branch_id,
            'movement_type' => $movementType,
            'quantity' => -$quantity,
            'quantity_before' => $previousQuantity,
            'quantity_after' => $this->quantity_on_hand,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
        ]);
    }

    /**
     * Reserve stock.
     */
    public function reserve(int $quantity): bool
    {
        if ($this->available_quantity < $quantity) {
            return false;
        }

        $this->quantity_reserved += $quantity;
        return $this->save();
    }

    /**
     * Release reserved stock.
     */
    public function releaseReservation(int $quantity): bool
    {
        $this->quantity_reserved = max(0, $this->quantity_reserved - $quantity);
        return $this->save();
    }

    /**
     * Adjust stock to a specific quantity.
     */
    public function adjustTo(int $newQuantity, ?string $notes = null): StockMovement
    {
        $previousQuantity = $this->quantity_on_hand;
        $this->quantity_on_hand = $newQuantity;
        $this->last_count_at = now();
        $this->save();

        return StockMovement::create([
            'tenant_id' => $this->tenant_id,
            'product_id' => $this->product_id,
            'branch_id' => $this->branch_id,
            'movement_type' => StockMovement::TYPE_ADJUSTMENT,
            'quantity' => $newQuantity - $previousQuantity,
            'quantity_before' => $previousQuantity,
            'quantity_after' => $newQuantity,
            'notes' => $notes ?? 'Stock adjustment',
        ]);
    }

    /**
     * Get or create stock level for a product and branch.
     */
    public static function getOrCreate(string $productId, string $branchId, ?string $tenantId = null): self
    {
        return static::firstOrCreate(
            [
                'product_id' => $productId,
                'branch_id' => $branchId,
            ],
            [
                'tenant_id' => $tenantId ?? app('currentTenant')?->id,
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
                'quantity_on_order' => 0,
            ]
        );
    }
}
