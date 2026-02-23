<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use XLinic\Framework\Core\Model\BaseModel;

class PurchaseOrderLine extends BaseModel
{
    protected $table = 'purchase_order_lines';

    protected $fillable = [
        'tenant_id',
        'purchase_order_id',
        'product_id',
        'quantity',
        'quantity_received',
        'unit_price_minor',
        'line_total_minor',
        'notes',
    ];

    protected $casts = [
        'id' => 'string',
        'quantity' => 'integer',
        'quantity_received' => 'integer',
        'unit_price_minor' => 'integer',
        'line_total_minor' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'quantity' => 1,
        'quantity_received' => 0,
        'unit_price_minor' => 0,
        'line_total_minor' => 0,
    ];

    /**
     * Get the purchase order.
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Get the product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        parent::booted();

        // Calculate line total on save
        static::saving(function (self $line) {
            $line->line_total_minor = $line->quantity * $line->unit_price_minor;
        });

        // Recalculate order totals after line changes
        static::saved(function (self $line) {
            $line->purchaseOrder?->recalculateTotals();
        });

        static::deleted(function (self $line) {
            $line->purchaseOrder?->recalculateTotals();
        });
    }

    /**
     * Get remaining quantity to receive.
     */
    public function getRemainingQuantityAttribute(): int
    {
        return max(0, $this->quantity - $this->quantity_received);
    }

    /**
     * Check if fully received.
     */
    public function isFullyReceived(): bool
    {
        return $this->quantity_received >= $this->quantity;
    }

    /**
     * Check if partially received.
     */
    public function isPartiallyReceived(): bool
    {
        return $this->quantity_received > 0 && $this->quantity_received < $this->quantity;
    }

    /**
     * Receive items.
     */
    public function receiveItems(int $quantity, ?string $notes = null): ?StockMovement
    {
        if ($quantity <= 0) {
            return null;
        }

        // Don't receive more than ordered
        $maxReceivable = $this->remaining_quantity;
        $quantity = min($quantity, $maxReceivable);

        if ($quantity <= 0) {
            return null;
        }

        // Update quantity received
        $this->quantity_received += $quantity;
        $this->save();

        // Get or create stock level for this product at the branch
        $stockLevel = StockLevel::getOrCreate(
            $this->product_id,
            $this->purchaseOrder->branch_id,
            $this->tenant_id
        );

        // Increase stock
        $movement = $stockLevel->increase(
            $quantity,
            StockMovement::TYPE_PURCHASE_RECEIVE,
            'purchase_order',
            $this->purchase_order_id,
            $notes ?? "Received from PO #{$this->purchaseOrder->order_number}"
        );

        // Create journal entry for stock receipt
        $this->createReceiptJournalEntry($movement, $quantity);

        // Update the order status
        $this->purchaseOrder->receive();

        return $movement;
    }

    /**
     * Create journal entry for stock receipt.
     */
    protected function createReceiptJournalEntry(StockMovement $movement, int $quantity): void
    {
        try {
            $accountingService = app(\Modules\Inventory\Services\InventoryAccountingService::class);

            // Calculate value in major units (value = qty * unit_price in major)
            $valueMajor = ($quantity * $this->unit_price_minor) / 100;

            $accountingService->createStockReceiptEntry(
                $movement,
                (int) $valueMajor,
                "Stock receipt: PO #{$this->purchaseOrder->order_number} - {$this->product->name} x {$quantity}"
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to create stock receipt journal entry', [
                'error' => $e->getMessage(),
                'purchase_order_line_id' => $this->id,
            ]);
        }
    }

    /**
     * Get unit price in major units.
     */
    public function getUnitPriceAttribute(): float
    {
        return $this->unit_price_minor / 100;
    }

    /**
     * Get line total in major units.
     */
    public function getLineTotalAttribute(): float
    {
        return $this->line_total_minor / 100;
    }
}
