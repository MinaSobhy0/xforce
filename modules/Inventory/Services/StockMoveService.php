<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockLevel;
use Modules\Inventory\Models\StockLocation;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\StockTransfer;
use Modules\Inventory\Models\StockTransferLine;

/**
 * Odoo-like Stock Move Service
 *
 * All stock operations create StockTransfer documents:
 * - Receipt: Supplier Location → Internal (Purchase)
 * - Delivery: Internal → Customer Location (Sale/Consumption)
 * - Internal: Internal → Internal (Warehouse transfers)
 * - Return In: Customer → Internal (Customer returns)
 * - Return Out: Internal → Supplier (Supplier returns)
 */
class StockMoveService
{
    protected InventoryAccountingService $accountingService;
    protected StockValuationService $valuationService;

    public function __construct(
        InventoryAccountingService $accountingService,
        StockValuationService $valuationService
    ) {
        $this->accountingService = $accountingService;
        $this->valuationService = $valuationService;
    }

    // =========================================================================
    // STOCK TRANSFER CREATION (Odoo-like Picking)
    // =========================================================================

    /**
     * Create a stock transfer document with lines.
     * This is the main method - creates the transfer and optionally processes it.
     */
    public function createTransfer(
        string $transferType,
        StockLocation $sourceLocation,
        StockLocation $destinationLocation,
        array $lines,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $notes = null,
        bool $autoProcess = true
    ): StockTransfer {
        return DB::transaction(function () use (
            $transferType, $sourceLocation, $destinationLocation,
            $lines, $referenceType, $referenceId, $notes, $autoProcess
        ) {
            $branchId = $sourceLocation->branch_id ?? $destinationLocation->branch_id;
            $tenantId = $sourceLocation->tenant_id ?? $destinationLocation->tenant_id;

            // Create the transfer document
            $transfer = StockTransfer::create([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'transfer_type' => $transferType,
                'status' => StockTransfer::STATUS_DRAFT,
                'source_location_id' => $sourceLocation->id,
                'destination_location_id' => $destinationLocation->id,
                'scheduled_date' => now(),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);

            // Create transfer lines
            foreach ($lines as $line) {
                StockTransferLine::create([
                    'tenant_id' => $tenantId,
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $line['product_id'],
                    'quantity_planned' => $line['quantity'],
                    'quantity_done' => $autoProcess ? $line['quantity'] : 0,
                    'unit_cost_minor' => $line['unit_cost_minor'] ?? 0,
                ]);
            }

            // Auto-process if requested (creates stock movements)
            if ($autoProcess) {
                $this->processTransfer($transfer);
            }

            return $transfer->fresh(['lines']);
        });
    }

    /**
     * Process a transfer - validate and create stock movements.
     */
    public function processTransfer(StockTransfer $transfer): bool
    {
        if ($transfer->status === StockTransfer::STATUS_DONE) {
            return true; // Already processed
        }

        return DB::transaction(function () use ($transfer) {
            $sourceLocation = $transfer->sourceLocation;
            $destinationLocation = $transfer->destinationLocation;

            foreach ($transfer->lines as $line) {
                $quantityToTransfer = $line->quantity_done > 0
                    ? $line->quantity_done
                    : $line->quantity_planned;

                if ($quantityToTransfer <= 0) {
                    continue;
                }

                // Update quantity_done
                $line->quantity_done = $quantityToTransfer;
                $line->save();

                // Only create movements for storable products
                $product = $line->product;
                if ($product && $product->tracksInventory()) {
                    $this->createMovementFromTransfer(
                        $transfer,
                        $line,
                        $sourceLocation,
                        $destinationLocation,
                        $quantityToTransfer,
                        $line->unit_cost_minor ?: null
                    );
                }
            }

            $transfer->status = StockTransfer::STATUS_DONE;
            $transfer->effective_date = now();
            $transfer->confirmed_by = auth()->id();
            return $transfer->save();
        });
    }

    /**
     * Create a stock movement from a transfer line.
     */
    protected function createMovementFromTransfer(
        StockTransfer $transfer,
        StockTransferLine $line,
        StockLocation $sourceLocation,
        StockLocation $destinationLocation,
        int $quantity,
        ?int $unitCostMinor = null
    ): StockMovement {
        // Determine movement type based on transfer type
        $movementType = match ($transfer->transfer_type) {
            StockTransfer::TYPE_RECEIPT => StockMovement::TYPE_PURCHASE_RECEIVE,
            StockTransfer::TYPE_DELIVERY => StockMovement::TYPE_INVOICE_SALE,
            StockTransfer::TYPE_INTERNAL => StockMovement::TYPE_LOCATION_TRANSFER_IN,
            StockTransfer::TYPE_RETURN_IN => StockMovement::TYPE_RETURN,
            StockTransfer::TYPE_RETURN_OUT => StockMovement::TYPE_ADJUSTMENT,
            default => StockMovement::TYPE_ADJUSTMENT,
        };

        // Get quantity before for audit trail
        $quantityBefore = 0;
        if ($destinationLocation->isPhysical()) {
            $destLevel = StockLevel::where('product_id', $line->product_id)
                ->where('location_id', $destinationLocation->id)
                ->first();
            $quantityBefore = $destLevel?->quantity_on_hand ?? 0;
        } elseif ($sourceLocation->isPhysical()) {
            $srcLevel = StockLevel::where('product_id', $line->product_id)
                ->where('location_id', $sourceLocation->id)
                ->first();
            $quantityBefore = $srcLevel?->quantity_on_hand ?? 0;
        }

        // Get product for cost tracking
        $product = Product::find($line->product_id);

        // Determine unit cost based on transfer type and valuation method
        if ($unitCostMinor === null && $product) {
            $isReceipt = in_array($transfer->transfer_type, [
                StockTransfer::TYPE_RECEIPT,
                StockTransfer::TYPE_RETURN_IN,
            ]);

            if ($isReceipt) {
                // For receipts, use product's current cost (will be updated after for AVCO)
                $unitCostMinor = $product->cost_price_minor;
            } else {
                // For sales/consumption, use valuation method
                $unitCostMinor = $this->valuationService->getUnitCost($product, $transfer->branch_id);
            }
        }

        // Create the movement record with cost tracking
        $movement = StockMovement::create([
            'tenant_id' => $transfer->tenant_id,
            'product_id' => $line->product_id,
            'branch_id' => $transfer->branch_id,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityBefore,
            'unit_cost_minor' => $unitCostMinor ?? 0,
            'remaining_quantity' => $this->isReceiptType($transfer->transfer_type) ? $quantity : null,
            'source_location_id' => $sourceLocation->id,
            'destination_location_id' => $destinationLocation->id,
            'reference_type' => 'stock_transfer',
            'reference_id' => $transfer->id,
            'notes' => $transfer->notes,
            'created_by' => auth()->id(),
        ]);

        // Execute the movement (update stock levels)
        $this->executeMove($movement, $sourceLocation, $destinationLocation);

        // Link movement to transfer line
        $line->stock_movement_id = $movement->id;
        $line->save();

        // Handle valuation method specific logic
        if ($product) {
            $this->handleValuationAfterMove($movement, $transfer, $product);
        }

        // Create journal entry for stock movement
        $this->createJournalEntryForMovement($movement, $transfer, $line);

        return $movement;
    }

    /**
     * Check if transfer type is a receipt (stock coming in).
     */
    protected function isReceiptType(string $transferType): bool
    {
        return in_array($transferType, [
            StockTransfer::TYPE_RECEIPT,
            StockTransfer::TYPE_RETURN_IN,
        ]);
    }

    /**
     * Handle valuation-specific logic after a move.
     */
    protected function handleValuationAfterMove(
        StockMovement $movement,
        StockTransfer $transfer,
        Product $product
    ): void {
        // For receipts with AVCO, update average cost
        if ($this->isReceiptType($transfer->transfer_type)) {
            if ($product->valuation_method === Product::VALUATION_AVERAGE) {
                $this->valuationService->updateAverageCost(
                    $product,
                    $movement->quantity,
                    $movement->unit_cost_minor,
                    $transfer->branch_id
                );
            }
        } else {
            // For consumption with FIFO, consume layers
            if ($product->valuation_method === Product::VALUATION_FIFO) {
                $this->valuationService->consumeFifoLayers(
                    $product,
                    $movement->quantity,
                    $transfer->branch_id
                );
            }
        }
    }

    /**
     * Execute a move - update stock levels.
     */
    public function executeMove(
        StockMovement $movement,
        ?StockLocation $sourceLocation = null,
        ?StockLocation $destinationLocation = null
    ): void {
        $sourceLocation = $sourceLocation ?? StockLocation::find($movement->source_location_id);
        $destinationLocation = $destinationLocation ?? StockLocation::find($movement->destination_location_id);

        $quantityAfter = $movement->quantity_before;

        // Decrease source location (if physical/internal)
        if ($sourceLocation && $sourceLocation->isPhysical()) {
            $sourceLevel = StockLevel::getOrCreate(
                $movement->product_id,
                $sourceLocation->branch_id,
                $sourceLocation->id,
                $sourceLocation->tenant_id
            );
            $sourceLevel->decrement('quantity_on_hand', $movement->quantity);
            $quantityAfter = $sourceLevel->quantity_on_hand;
        }

        // Increase destination location (if physical/internal)
        if ($destinationLocation && $destinationLocation->isPhysical()) {
            $destLevel = StockLevel::getOrCreate(
                $movement->product_id,
                $destinationLocation->branch_id,
                $destinationLocation->id,
                $destinationLocation->tenant_id
            );
            $destLevel->increment('quantity_on_hand', $movement->quantity);
            $destLevel->update(['last_restock_at' => now()]);
            $quantityAfter = $destLevel->quantity_on_hand;
        }

        // Update movement with final quantity
        $movement->update(['quantity_after' => $quantityAfter]);
    }

    // =========================================================================
    // PURCHASE OPERATIONS
    // =========================================================================

    /**
     * Create purchase receipt: Supplier Location → Internal Location
     * Returns the StockTransfer document.
     *
     * @param Product $product
     * @param StockLocation $destinationLocation
     * @param int $quantity
     * @param string|null $referenceType
     * @param string|null $referenceId
     * @param string|null $notes
     * @param int|null $unitCostMinor Purchase unit cost (for AVCO/FIFO tracking)
     */
    public function createPurchaseReceipt(
        Product $product,
        StockLocation $destinationLocation,
        int $quantity,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $notes = null,
        ?int $unitCostMinor = null
    ): StockTransfer {
        if (!$product->tracksInventory()) {
            throw new \InvalidArgumentException('Cannot create stock transfer for non-storable product');
        }

        $supplierLocation = StockLocation::getSupplierLocation($destinationLocation->branch_id);

        if (!$supplierLocation) {
            StockLocation::ensureVirtualLocations($destinationLocation->branch_id, $destinationLocation->tenant_id);
            $supplierLocation = StockLocation::getSupplierLocation($destinationLocation->branch_id);
        }

        if (!$supplierLocation) {
            throw new \RuntimeException('Supplier location not found for branch');
        }

        // Use provided cost or fall back to product cost
        $cost = $unitCostMinor ?? $product->cost_price_minor;

        return $this->createTransfer(
            StockTransfer::TYPE_RECEIPT,
            $supplierLocation,
            $destinationLocation,
            [['product_id' => $product->id, 'quantity' => $quantity, 'unit_cost_minor' => $cost]],
            $referenceType,
            $referenceId,
            $notes,
            true // Auto-process
        );
    }

    // =========================================================================
    // SALE OPERATIONS
    // =========================================================================

    /**
     * Create sale delivery: Internal Location → Customer Location
     */
    public function createSaleDelivery(
        Product $product,
        StockLocation $sourceLocation,
        int $quantity,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $notes = null
    ): StockTransfer {
        if (!$product->tracksInventory()) {
            throw new \InvalidArgumentException('Cannot create stock transfer for non-storable product');
        }

        $customerLocation = StockLocation::getCustomerLocation($sourceLocation->branch_id);

        if (!$customerLocation) {
            StockLocation::ensureVirtualLocations($sourceLocation->branch_id, $sourceLocation->tenant_id);
            $customerLocation = StockLocation::getCustomerLocation($sourceLocation->branch_id);
        }

        if (!$customerLocation) {
            throw new \RuntimeException('Customer location not found for branch');
        }

        return $this->createTransfer(
            StockTransfer::TYPE_DELIVERY,
            $sourceLocation,
            $customerLocation,
            [['product_id' => $product->id, 'quantity' => $quantity]],
            $referenceType,
            $referenceId,
            $notes,
            true
        );
    }

    // =========================================================================
    // CONSUMPTION OPERATIONS (Appointments/Treatments)
    // =========================================================================

    /**
     * Create consumption: Internal Location → Customer Location
     */
    public function createConsumption(
        Product $product,
        StockLocation $sourceLocation,
        int $quantity,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $notes = null
    ): StockTransfer {
        // Same as sale delivery but with different reference
        return $this->createSaleDelivery(
            $product,
            $sourceLocation,
            $quantity,
            $referenceType,
            $referenceId,
            $notes
        );
    }

    // =========================================================================
    // RETURN OPERATIONS
    // =========================================================================

    /**
     * Create customer return: Customer Location → Internal Location
     */
    public function createCustomerReturn(
        Product $product,
        StockLocation $destinationLocation,
        int $quantity,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $notes = null
    ): StockTransfer {
        if (!$product->tracksInventory()) {
            throw new \InvalidArgumentException('Cannot create stock transfer for non-storable product');
        }

        $customerLocation = StockLocation::getCustomerLocation($destinationLocation->branch_id);

        if (!$customerLocation) {
            StockLocation::ensureVirtualLocations($destinationLocation->branch_id, $destinationLocation->tenant_id);
            $customerLocation = StockLocation::getCustomerLocation($destinationLocation->branch_id);
        }

        if (!$customerLocation) {
            throw new \RuntimeException('Customer location not found for branch');
        }

        return $this->createTransfer(
            StockTransfer::TYPE_RETURN_IN,
            $customerLocation,
            $destinationLocation,
            [['product_id' => $product->id, 'quantity' => $quantity]],
            $referenceType,
            $referenceId,
            $notes,
            true
        );
    }

    // =========================================================================
    // ADJUSTMENT OPERATIONS
    // =========================================================================

    /**
     * Create inventory adjustment.
     * Positive quantity = gain (Inventory → Internal)
     * Negative quantity = loss (Internal → Inventory)
     */
    public function createAdjustment(
        Product $product,
        StockLocation $location,
        int $quantityDiff,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $notes = null
    ): StockTransfer {
        if (!$product->tracksInventory()) {
            throw new \InvalidArgumentException('Cannot create stock transfer for non-storable product');
        }

        if ($quantityDiff === 0) {
            throw new \InvalidArgumentException('Quantity difference cannot be zero');
        }

        $adjustmentLocation = StockLocation::getInventoryAdjustmentLocation($location->branch_id);

        if (!$adjustmentLocation) {
            StockLocation::ensureVirtualLocations($location->branch_id, $location->tenant_id);
            $adjustmentLocation = StockLocation::getInventoryAdjustmentLocation($location->branch_id);
        }

        if (!$adjustmentLocation) {
            throw new \RuntimeException('Inventory adjustment location not found for branch');
        }

        if ($quantityDiff > 0) {
            // Gain: Inventory Adjustment Location → Internal Location
            return $this->createTransfer(
                StockTransfer::TYPE_RECEIPT,
                $adjustmentLocation,
                $location,
                [['product_id' => $product->id, 'quantity' => $quantityDiff]],
                $referenceType,
                $referenceId,
                $notes ?? 'Inventory gain',
                true
            );
        } else {
            // Loss: Internal Location → Inventory Adjustment Location
            return $this->createTransfer(
                StockTransfer::TYPE_DELIVERY,
                $location,
                $adjustmentLocation,
                [['product_id' => $product->id, 'quantity' => abs($quantityDiff)]],
                $referenceType,
                $referenceId,
                $notes ?? 'Inventory loss',
                true
            );
        }
    }

    // =========================================================================
    // WASTE/SCRAP OPERATIONS
    // =========================================================================

    /**
     * Create waste/scrap: Internal Location → Scrap Location
     */
    public function createWaste(
        Product $product,
        StockLocation $sourceLocation,
        int $quantity,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $notes = null
    ): StockTransfer {
        if (!$product->tracksInventory()) {
            throw new \InvalidArgumentException('Cannot create stock transfer for non-storable product');
        }

        $scrapLocation = StockLocation::getScrapLocation($sourceLocation->branch_id);

        if (!$scrapLocation) {
            throw new \RuntimeException('Scrap location not found for branch');
        }

        return $this->createTransfer(
            StockTransfer::TYPE_INTERNAL,
            $sourceLocation,
            $scrapLocation,
            [['product_id' => $product->id, 'quantity' => $quantity]],
            $referenceType,
            $referenceId,
            $notes,
            true
        );
    }

    // =========================================================================
    // INTERNAL TRANSFER OPERATIONS
    // =========================================================================

    /**
     * Create internal transfer: Internal Location A → Internal Location B
     */
    public function createInternalTransfer(
        Product $product,
        StockLocation $sourceLocation,
        StockLocation $destinationLocation,
        int $quantity,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $notes = null
    ): StockTransfer {
        if (!$product->tracksInventory()) {
            throw new \InvalidArgumentException('Cannot create stock transfer for non-storable product');
        }

        if (!$sourceLocation->isPhysical() || !$destinationLocation->isPhysical()) {
            throw new \InvalidArgumentException('Internal transfers require physical locations');
        }

        return $this->createTransfer(
            StockTransfer::TYPE_INTERNAL,
            $sourceLocation,
            $destinationLocation,
            [['product_id' => $product->id, 'quantity' => $quantity]],
            $referenceType,
            $referenceId,
            $notes,
            true
        );
    }

    // =========================================================================
    // BATCH OPERATIONS
    // =========================================================================

    /**
     * Create a batch delivery for multiple products (e.g., from invoice).
     */
    public function createBatchDelivery(
        StockLocation $sourceLocation,
        array $products, // [['product' => Product, 'quantity' => int], ...]
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $notes = null
    ): ?StockTransfer {
        $customerLocation = StockLocation::getCustomerLocation($sourceLocation->branch_id);

        if (!$customerLocation) {
            StockLocation::ensureVirtualLocations($sourceLocation->branch_id, $sourceLocation->tenant_id);
            $customerLocation = StockLocation::getCustomerLocation($sourceLocation->branch_id);
        }

        if (!$customerLocation) {
            return null;
        }

        // Filter to only storable products
        $lines = [];
        foreach ($products as $item) {
            $product = $item['product'];
            if ($product && $product->tracksInventory()) {
                $lines[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                ];
            }
        }

        if (empty($lines)) {
            return null;
        }

        return $this->createTransfer(
            StockTransfer::TYPE_DELIVERY,
            $sourceLocation,
            $customerLocation,
            $lines,
            $referenceType,
            $referenceId,
            $notes,
            true
        );
    }

    /**
     * Create a batch receipt for multiple products (e.g., from PO).
     */
    public function createBatchReceipt(
        StockLocation $destinationLocation,
        array $products, // [['product' => Product, 'quantity' => int], ...]
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $notes = null
    ): ?StockTransfer {
        $supplierLocation = StockLocation::getSupplierLocation($destinationLocation->branch_id);

        if (!$supplierLocation) {
            StockLocation::ensureVirtualLocations($destinationLocation->branch_id, $destinationLocation->tenant_id);
            $supplierLocation = StockLocation::getSupplierLocation($destinationLocation->branch_id);
        }

        if (!$supplierLocation) {
            return null;
        }

        // Filter to only storable products
        $lines = [];
        foreach ($products as $item) {
            $product = $item['product'];
            if ($product && $product->tracksInventory()) {
                $lines[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                ];
            }
        }

        if (empty($lines)) {
            return null;
        }

        return $this->createTransfer(
            StockTransfer::TYPE_RECEIPT,
            $supplierLocation,
            $destinationLocation,
            $lines,
            $referenceType,
            $referenceId,
            $notes,
            true
        );
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Get stock quantity at a specific location.
     */
    public function getStockAtLocation(string $productId, string $locationId): int
    {
        $stockLevel = StockLevel::where('product_id', $productId)
            ->where('location_id', $locationId)
            ->first();

        return $stockLevel?->quantity_on_hand ?? 0;
    }

    /**
     * Get stock quantity across all physical locations in a branch.
     */
    public function getStockInBranch(string $productId, string $branchId): int
    {
        return StockLevel::where('product_id', $productId)
            ->whereHas('location', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId)
                    ->where('location_type', StockLocation::TYPE_INTERNAL);
            })
            ->sum('quantity_on_hand');
    }

    /**
     * Check if there's enough stock at a location.
     */
    public function hasEnoughStock(string $productId, string $locationId, int $quantity): bool
    {
        return $this->getStockAtLocation($productId, $locationId) >= $quantity;
    }

    // =========================================================================
    // ACCOUNTING INTEGRATION
    // =========================================================================

    /**
     * Create journal entry for a stock movement based on transfer type.
     */
    protected function createJournalEntryForMovement(
        StockMovement $movement,
        StockTransfer $transfer,
        StockTransferLine $line
    ): void {
        try {
            $product = $movement->product;
            if (!$product) {
                return;
            }

            // Calculate value using product cost price
            $valueMinor = $movement->quantity * $product->cost_price_minor;
            $valueMajor = (int) round($valueMinor / 100);

            if ($valueMajor <= 0) {
                return;
            }

            $productName = $product->getTranslation('name', 'en') ?? $product->sku;

            switch ($transfer->transfer_type) {
                case StockTransfer::TYPE_RECEIPT:
                    // Purchase receipt: Debit Inventory, Credit Stock Input
                    $this->accountingService->createStockReceiptEntry(
                        $movement,
                        $valueMajor,
                        "Stock receipt: {$productName} x {$movement->quantity} (Transfer #{$transfer->transfer_number})"
                    );
                    break;

                case StockTransfer::TYPE_DELIVERY:
                    // Sale delivery: Debit COGS, Credit Inventory
                    $this->accountingService->createStockConsumptionEntry(
                        $movement,
                        $valueMajor,
                        "Stock delivery: {$productName} x {$movement->quantity} (Transfer #{$transfer->transfer_number})"
                    );
                    break;

                case StockTransfer::TYPE_INTERNAL:
                    // Internal transfers don't need journal entries (same value, different location)
                    break;

                case StockTransfer::TYPE_RETURN_IN:
                    // Customer return: Debit Inventory, Credit COGS (reverse of sale)
                    $this->accountingService->createStockReceiptEntry(
                        $movement,
                        $valueMajor,
                        "Customer return: {$productName} x {$movement->quantity}"
                    );
                    break;

                case StockTransfer::TYPE_RETURN_OUT:
                    // Supplier return: Debit Stock Input, Credit Inventory (reverse of purchase)
                    $this->accountingService->createStockConsumptionEntry(
                        $movement,
                        $valueMajor,
                        "Supplier return: {$productName} x {$movement->quantity}"
                    );
                    break;
            }

            Log::info('Stock movement journal entry created', [
                'movement_id' => $movement->id,
                'transfer_id' => $transfer->id,
                'transfer_type' => $transfer->transfer_type,
                'product_id' => $product->id,
                'value_major' => $valueMajor,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create journal entry for stock movement', [
                'movement_id' => $movement->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
