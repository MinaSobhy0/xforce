<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Events\PurchaseOrderReceived;
use Modules\Inventory\Services\StockMoveService;
use XLinic\Framework\Core\Model\BaseModel;

class PurchaseOrderLine extends BaseModel
{
    protected $table = 'purchase_order_lines';

    protected $fillable = [
        'tenant_id',
        'purchase_order_id',
        'product_id',
        'uom_id',
        'quantity',
        'quantity_received',
        'unit_price_minor',
        'discount_minor',
        'discount_type',
        'tax_rates',
        'tax_amount_minor',
        'line_total_minor',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'quantity_received' => 'integer',
        'unit_price_minor' => 'integer',
        'discount_minor' => 'integer',
        'tax_rates' => 'array',
        'tax_amount_minor' => 'integer',
        'line_total_minor' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'quantity' => 1,
        'quantity_received' => 0,
        'unit_price_minor' => 0,
        'discount_minor' => 0,
        'discount_type' => 'fixed',
        'tax_amount_minor' => 0,
        'line_total_minor' => 0,
    ];

    // Discount type constants
    public const DISCOUNT_FIXED = 'fixed';
    public const DISCOUNT_PERCENT = 'percent';

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
     * Get the UOM.
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        parent::booted();

        // Calculate line total on save (including discount and multiple taxes)
        static::saving(function (self $line) {
            $subtotal = $line->quantity * $line->unit_price_minor;

            // Apply discount first (Odoo-like)
            $discountAmount = 0;
            if ($line->discount_minor > 0) {
                if ($line->discount_type === self::DISCOUNT_PERCENT) {
                    $discountAmount = (int) round($subtotal * $line->discount_minor / 100);
                } else {
                    $discountAmount = $line->discount_minor;
                }
            }
            $afterDiscount = max(0, $subtotal - $discountAmount);

            // Sum all tax rates (positive VAT and negative withholding)
            $taxRates = $line->tax_rates ?? [];
            $totalTaxPercent = array_sum(array_map('floatval', $taxRates));

            $line->tax_amount_minor = (int) round($afterDiscount * $totalTaxPercent / 100);
            $line->line_total_minor = $afterDiscount + $line->tax_amount_minor;
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
     *
     * Odoo-like behavior:
     * - Storable products: Stock is increased, journal entries created
     * - Consumable products: Only quantity_received is updated (no stock tracking)
     *
     * @param int $quantity Quantity to receive
     * @param string|null $locationId Destination location ID (null for default)
     * @param string|null $notes Optional notes
     */
    public function receiveItems(int $quantity, ?string $locationId = null, ?string $notes = null): ?StockTransfer
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

        // Update quantity received (for all product types)
        $this->quantity_received += $quantity;
        $this->save();

        $transfer = null;

        // Only create stock transfers for storable products
        // Consumable products are not tracked in inventory
        if ($this->product && $this->product->tracksInventory()) {
            // Get the destination location
            $destinationLocation = $locationId
                ? StockLocation::find($locationId)
                : StockLocation::getDefaultLocation($this->purchaseOrder->branch_id);

            if (!$destinationLocation) {
                // Fallback: create default location
                $destinationLocation = StockLocation::getDefaultLocation($this->purchaseOrder->branch_id);
            }

            if ($destinationLocation) {
                // Use StockMoveService for Odoo-like transfer (Supplier → Internal)
                // StockMoveService automatically creates journal entries for stock movements
                $stockMoveService = app(StockMoveService::class);
                $transfer = $stockMoveService->createPurchaseReceipt(
                    $this->product,
                    $destinationLocation,
                    $quantity,
                    $this->uom_id, // Pass the UOM from the PO line
                    'purchase_order',
                    $this->purchase_order_id,
                    $notes ?? "Received from PO #{$this->purchaseOrder->order_number}",
                    $this->unit_price_minor // Pass PO unit price for cost tracking
                );
            }
        }

        // Dispatch event for asset creation (applies to both types)
        PurchaseOrderReceived::dispatch($this, $quantity);

        // Update the order status
        $this->purchaseOrder->receive();

        return $transfer;
    }

    // Note: Receipt journal entries are now created automatically by StockMoveService

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

    /**
     * Reverse receiving - decrease stock and create reverse journal entry.
     *
     * Odoo-like behavior:
     * - Storable products: Stock is decreased, journal entries reversed
     * - Consumable products: Only quantity_received is updated (no stock tracking)
     *
     * @param int|null $quantity Quantity to reverse. If null, reverses all received.
     */
    public function reverseReceiving(?int $quantity = null): bool
    {
        if ($this->quantity_received <= 0) {
            return true; // Nothing to reverse
        }

        // Default to reversing all, but cap at quantity_received
        $quantityToReverse = $quantity !== null
            ? min($quantity, $this->quantity_received)
            : $this->quantity_received;

        if ($quantityToReverse <= 0) {
            return true;
        }

        // Only reverse stock for storable products
        if ($this->product && $this->product->tracksInventory()) {
            // Get stock level
            $stockLevel = StockLevel::where('product_id', $this->product_id)
                ->where('branch_id', $this->purchaseOrder->branch_id)
                ->first();

            if ($stockLevel) {
                // Decrease stock
                $movement = $stockLevel->decrease(
                    $quantityToReverse,
                    StockMovement::TYPE_ADJUSTMENT,
                    'purchase_order_reversal',
                    $this->purchase_order_id,
                    "Reversal of PO #{$this->purchaseOrder->order_number}"
                );

                // Create reverse journal entry
                $this->createReversalJournalEntry($movement, $quantityToReverse);
            }
        }

        // Reduce quantity received (for all product types)
        $this->quantity_received -= $quantityToReverse;
        $this->save();

        return true;
    }

    /**
     * Create reverse journal entry for stock reversal.
     * Uses product's stock valuation account, falls back to system defaults.
     */
    protected function createReversalJournalEntry(StockMovement $movement, int $quantity): void
    {
        try {
            $product = $this->product;
            $defaultAccounts = app(\Modules\Accounting\Services\DefaultAccountsService::class);

            // Use product's stock valuation account, fallback to defaults
            $stockValuationAccount = $product->stockValuationAccount ?? $defaultAccounts->getStockValuationAccount();
            $stockInputAccount = $product->stockValuationAccount ?? $defaultAccounts->getStockInputAccount();

            if (!$stockValuationAccount || !$stockInputAccount) {
                \Illuminate\Support\Facades\Log::warning('Missing accounts for reversal journal entry', [
                    'product_id' => $product->id,
                    'has_valuation' => (bool) $stockValuationAccount,
                    'has_input' => (bool) $stockInputAccount,
                ]);
                return;
            }

            $accountingService = app(\Modules\Accounting\Services\AccountingIntegrationService::class);

            // Calculate value in minor units
            $valueMinor = $quantity * $this->unit_price_minor;
            $productName = $product->getTranslation('name', 'en') ?? $product->sku;

            // Reverse entry: Debit Stock Input (AP), Credit Inventory (valuation)
            $lines = [
                [
                    'account_code' => $stockInputAccount->code,
                    'debit' => $valueMinor,
                    'credit' => 0,
                    'description' => "Reversal: {$productName}",
                ],
                [
                    'account_code' => $stockValuationAccount->code,
                    'debit' => 0,
                    'credit' => $valueMinor,
                    'description' => "Reversal: {$productName}",
                ],
            ];

            $accountingService->createJournalEntry(
                now(),
                "Stock reversal: PO #{$this->purchaseOrder->order_number} - {$productName} x {$quantity}",
                $lines,
                'purchase_order_reversal',
                $this->purchase_order_id,
                true
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to create reversal journal entry', [
                'error' => $e->getMessage(),
                'purchase_order_line_id' => $this->id,
            ]);
        }
    }
}
