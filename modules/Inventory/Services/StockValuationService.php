<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\StockLevel;

/**
 * Stock Valuation Service
 *
 * Implements Odoo-like stock valuation methods:
 * - Standard Price: Fixed cost from product
 * - AVCO (Average Cost): Weighted average recalculated on each receipt
 * - FIFO (First In First Out): Cost from oldest available layers
 */
class StockValuationService
{
    /**
     * Get the unit cost for a product based on its valuation method.
     *
     * @param Product $product
     * @param string|null $branchId Optional branch for location-specific valuation
     * @return int Cost in minor units
     */
    public function getUnitCost(Product $product, ?string $branchId = null): int
    {
        return match ($product->valuation_method) {
            Product::VALUATION_STANDARD => $this->getStandardCost($product),
            Product::VALUATION_AVERAGE => $this->getAverageCost($product, $branchId),
            Product::VALUATION_FIFO => $this->getFifoCost($product, $branchId),
            default => $product->cost_price_minor,
        };
    }

    /**
     * Get standard cost (fixed price from product).
     */
    public function getStandardCost(Product $product): int
    {
        return $product->cost_price_minor;
    }

    /**
     * Get average cost (AVCO).
     * Returns the stored average cost on the product.
     */
    public function getAverageCost(Product $product, ?string $branchId = null): int
    {
        // For AVCO, we use the product's cost_price_minor which is updated on each receipt
        return $product->cost_price_minor;
    }

    /**
     * Get FIFO cost from oldest available layers.
     *
     * @param Product $product
     * @param string|null $branchId
     * @return int Cost from oldest layer with remaining quantity
     */
    public function getFifoCost(Product $product, ?string $branchId = null): int
    {
        // Find the oldest receipt movement with remaining quantity
        $query = StockMovement::where('product_id', $product->id)
            ->whereIn('movement_type', [
                StockMovement::TYPE_PURCHASE_RECEIVE,
                StockMovement::TYPE_IN,
                StockMovement::TYPE_RETURN,
            ])
            ->where('remaining_quantity', '>', 0)
            ->orderBy('created_at', 'asc');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $oldestLayer = $query->first();

        if ($oldestLayer && $oldestLayer->unit_cost_minor > 0) {
            return $oldestLayer->unit_cost_minor;
        }

        // Fallback to product cost if no layers found
        return $product->cost_price_minor;
    }

    /**
     * Get cost for consuming/selling a specific quantity using FIFO.
     * Returns array of [quantity, unit_cost] layers to consume.
     *
     * @param Product $product
     * @param int $quantity Quantity to consume
     * @param string|null $branchId
     * @return array Array of ['quantity' => int, 'unit_cost_minor' => int, 'movement_id' => int]
     */
    public function getFifoLayers(Product $product, int $quantity, ?string $branchId = null): array
    {
        $layers = [];
        $remainingToConsume = $quantity;

        // Get receipt movements with remaining quantity, oldest first
        $query = StockMovement::where('product_id', $product->id)
            ->whereIn('movement_type', [
                StockMovement::TYPE_PURCHASE_RECEIVE,
                StockMovement::TYPE_IN,
                StockMovement::TYPE_RETURN,
            ])
            ->where('remaining_quantity', '>', 0)
            ->orderBy('created_at', 'asc');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $movements = $query->get();

        foreach ($movements as $movement) {
            if ($remainingToConsume <= 0) {
                break;
            }

            $consumeFromLayer = min($remainingToConsume, $movement->remaining_quantity);

            $layers[] = [
                'quantity' => $consumeFromLayer,
                'unit_cost_minor' => $movement->unit_cost_minor,
                'movement_id' => $movement->id,
            ];

            $remainingToConsume -= $consumeFromLayer;
        }

        // If not enough layers, use product cost for remainder
        if ($remainingToConsume > 0) {
            $layers[] = [
                'quantity' => $remainingToConsume,
                'unit_cost_minor' => $product->cost_price_minor,
                'movement_id' => null,
            ];
        }

        return $layers;
    }

    /**
     * Calculate total cost for consuming a quantity using FIFO.
     */
    public function calculateFifoCost(Product $product, int $quantity, ?string $branchId = null): int
    {
        $layers = $this->getFifoLayers($product, $quantity, $branchId);
        $totalCost = 0;

        foreach ($layers as $layer) {
            $totalCost += $layer['quantity'] * $layer['unit_cost_minor'];
        }

        return $totalCost;
    }

    /**
     * Consume FIFO layers (reduce remaining_quantity on movements).
     * Call this when stock is consumed/sold.
     */
    public function consumeFifoLayers(Product $product, int $quantity, ?string $branchId = null): int
    {
        $layers = $this->getFifoLayers($product, $quantity, $branchId);
        $totalCost = 0;

        foreach ($layers as $layer) {
            $totalCost += $layer['quantity'] * $layer['unit_cost_minor'];

            // Update the movement's remaining quantity
            if ($layer['movement_id']) {
                StockMovement::where('id', $layer['movement_id'])
                    ->decrement('remaining_quantity', $layer['quantity']);
            }
        }

        return $totalCost;
    }

    /**
     * Update average cost after a purchase receipt.
     * Always updates product cost with weighted average, regardless of valuation method.
     * This ensures product.cost_price_minor always reflects current average cost.
     *
     * Formula: New Avg = (Old Qty * Old Avg + New Qty * New Cost) / (Old Qty + New Qty)
     *
     * @param Product $product
     * @param int $newQuantity Quantity received (in stock UOM)
     * @param int $newUnitCostMinor Cost per unit of new receipt (in stock UOM)
     * @param string|null $branchId
     */
    public function updateAverageCost(
        Product $product,
        int $newQuantity,
        int $newUnitCostMinor,
        ?string $branchId = null
    ): void {
        // Get current stock quantity
        $query = StockLevel::where('product_id', $product->id);
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        $currentQty = $query->sum('quantity_on_hand');

        // Subtract the new quantity to get quantity before receipt
        $oldQty = max(0, $currentQty - $newQuantity);
        $oldCost = $product->cost_price_minor ?? 0;

        if ($oldQty + $newQuantity <= 0) {
            return;
        }

        // Calculate new weighted average
        $totalValue = ($oldQty * $oldCost) + ($newQuantity * $newUnitCostMinor);
        $totalQty = $oldQty + $newQuantity;
        $newAvgCost = (int) round($totalValue / $totalQty);

        // Update product cost (always, regardless of valuation method)
        $product->cost_price_minor = $newAvgCost;
        $product->save();

        Log::info('Updated average cost for product', [
            'product_id' => $product->id,
            'valuation_method' => $product->valuation_method,
            'old_qty' => $oldQty,
            'old_cost' => $oldCost,
            'new_qty' => $newQuantity,
            'new_cost' => $newUnitCostMinor,
            'new_avg_cost' => $newAvgCost,
        ]);
    }

    /**
     * Record a receipt movement with cost tracking.
     * Sets up the FIFO layer (remaining_quantity) for future consumption.
     * Always updates product average cost regardless of valuation method.
     */
    public function recordReceiptCost(StockMovement $movement, int $unitCostMinor): void
    {
        $movement->unit_cost_minor = $unitCostMinor;
        $movement->remaining_quantity = $movement->quantity; // Full quantity available for FIFO
        $movement->save();

        // Always update average cost on product
        $product = $movement->product;
        if ($product) {
            $this->updateAverageCost($product, $movement->quantity, $unitCostMinor, $movement->branch_id);
        }
    }

    /**
     * Record a consumption/sale movement with cost tracking.
     */
    public function recordConsumptionCost(StockMovement $movement, ?string $branchId = null): void
    {
        $product = $movement->product;
        if (!$product) {
            return;
        }

        $unitCost = match ($product->valuation_method) {
            Product::VALUATION_FIFO => $this->getFifoCost($product, $branchId),
            default => $this->getUnitCost($product, $branchId),
        };

        $movement->unit_cost_minor = $unitCost;
        $movement->save();

        // For FIFO, consume the layers
        if ($product->valuation_method === Product::VALUATION_FIFO) {
            $this->consumeFifoLayers($product, $movement->quantity, $branchId);
        }
    }

    /**
     * Get inventory value for a product.
     */
    public function getInventoryValue(Product $product, ?string $branchId = null): int
    {
        $query = StockLevel::where('product_id', $product->id);
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        $totalQty = $query->sum('quantity_on_hand');

        return match ($product->valuation_method) {
            Product::VALUATION_FIFO => $this->calculateFifoInventoryValue($product, $branchId),
            default => $totalQty * $this->getUnitCost($product, $branchId),
        };
    }

    /**
     * Calculate FIFO inventory value from remaining layers.
     */
    protected function calculateFifoInventoryValue(Product $product, ?string $branchId = null): int
    {
        $query = StockMovement::where('product_id', $product->id)
            ->whereIn('movement_type', [
                StockMovement::TYPE_PURCHASE_RECEIVE,
                StockMovement::TYPE_IN,
                StockMovement::TYPE_RETURN,
            ])
            ->where('remaining_quantity', '>', 0);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->get()->sum(function ($movement) {
            return $movement->remaining_quantity * $movement->unit_cost_minor;
        });
    }
}
