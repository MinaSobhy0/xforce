<?php

namespace Modules\Inventory\Services;

use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\AccountingIntegrationService;
use Modules\Inventory\Models\InventoryAdjustment;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMovement;

class InventoryAccountingService
{
    protected AccountingIntegrationService $accountingService;

    // Note: All accounts are now configured per-product
    // Products must have stock_valuation_account_id, stock_input_account_id, stock_output_account_id set

    public function __construct(AccountingIntegrationService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Create journal entry for stock receipt (purchase).
     * Debit: Inventory Asset
     * Credit: Stock Input (AP or Goods Received)
     */
    public function createStockReceiptEntry(
        StockMovement $movement,
        int $valueMajor,
        ?string $description = null
    ): ?JournalEntry {
        $product = $movement->product;

        $stockValuationAccount = $this->getStockValuationAccount($product);
        $stockInputAccount = $this->getStockInputAccount($product);

        if (!$stockValuationAccount || !$stockInputAccount) {
            return null;
        }

        $lines = [
            [
                'account_code' => $stockValuationAccount->code,
                'debit' => $valueMajor * 100, // Convert to minor
                'credit' => 0,
            ],
            [
                'account_code' => $stockInputAccount->code,
                'debit' => 0,
                'credit' => $valueMajor * 100,
            ],
        ];

        $description = $description ?? "Stock receipt: {$product->name} x {$movement->quantity}";

        return $this->accountingService->createJournalEntry(
            $movement->created_at ?? now(),
            $description,
            $lines,
            'stock_movement',
            $movement->id,
            true
        );
    }

    /**
     * Create journal entry for stock consumption/sale.
     * Debit: Cost of Goods Sold
     * Credit: Inventory Asset
     */
    public function createStockConsumptionEntry(
        StockMovement $movement,
        int $valueMajor,
        ?string $description = null
    ): ?JournalEntry {
        $product = $movement->product;

        $stockValuationAccount = $this->getStockValuationAccount($product);
        $stockOutputAccount = $this->getStockOutputAccount($product);

        if (!$stockValuationAccount || !$stockOutputAccount) {
            return null;
        }

        $lines = [
            [
                'account_code' => $stockOutputAccount->code,
                'debit' => $valueMajor * 100,
                'credit' => 0,
            ],
            [
                'account_code' => $stockValuationAccount->code,
                'debit' => 0,
                'credit' => $valueMajor * 100,
            ],
        ];

        $description = $description ?? "Stock consumption: {$product->name} x {$movement->quantity}";

        return $this->accountingService->createJournalEntry(
            $movement->created_at ?? now(),
            $description,
            $lines,
            'stock_movement',
            $movement->id,
            true
        );
    }

    /**
     * Create journal entry for inventory adjustment.
     * Uses product-specific accounts for proper accounting.
     */
    public function createAdjustmentJournalEntry(InventoryAdjustment $adjustment): ?JournalEntry
    {
        // Reload lines to ensure we have fresh data
        $adjustment->load('lines.product.stockValuationAccount', 'lines.product.stockInputAccount', 'lines.product.stockOutputAccount');

        // Check if there are any actual changes
        $hasChanges = $adjustment->lines->contains(fn ($line) => !$line->isNoChange());

        if (!$hasChanges) {
            \Log::info('InventoryAccountingService: No changes to create journal entry', [
                'adjustment_id' => $adjustment->id,
            ]);
            return null;
        }

        $lines = [];

        foreach ($adjustment->lines as $line) {
            if ($line->isNoChange()) {
                continue;
            }

            $product = $line->product;
            if (!$product) {
                continue;
            }

            $stockValuationAccount = $this->getStockValuationAccount($product);
            $absValue = abs($line->value_adjustment_minor);
            $productName = $product->getTranslation('name', 'en') ?? $product->sku;

            if (!$stockValuationAccount) {
                \Log::warning('InventoryAccountingService: Skipping line - missing stock valuation account', [
                    'product_id' => $product->id,
                    'product_sku' => $product->sku,
                ]);
                continue;
            }

            if ($line->isPositiveAdjustment()) {
                // Stock increase: Debit Inventory (valuation), Credit Stock Input
                $stockInputAccount = $this->getStockInputAccount($product);

                if (!$stockInputAccount) {
                    \Log::warning('InventoryAccountingService: Skipping line - missing stock input account', [
                        'product_id' => $product->id,
                    ]);
                    continue;
                }

                $lines[] = [
                    'account_code' => $stockValuationAccount->code,
                    'debit' => $absValue,
                    'credit' => 0,
                    'description' => "Stock increase: {$productName}",
                ];
                $lines[] = [
                    'account_code' => $stockInputAccount->code,
                    'debit' => 0,
                    'credit' => $absValue,
                    'description' => "Stock increase: {$productName}",
                ];
            } else {
                // Stock decrease: Debit Stock Output (expense), Credit Inventory (valuation)
                $stockOutputAccount = $this->getStockOutputAccount($product);

                if (!$stockOutputAccount) {
                    \Log::warning('InventoryAccountingService: Skipping line - missing stock output account', [
                        'product_id' => $product->id,
                    ]);
                    continue;
                }

                $lines[] = [
                    'account_code' => $stockOutputAccount->code,
                    'debit' => $absValue,
                    'credit' => 0,
                    'description' => "Stock decrease: {$productName}",
                ];
                $lines[] = [
                    'account_code' => $stockValuationAccount->code,
                    'debit' => 0,
                    'credit' => $absValue,
                    'description' => "Stock decrease: {$productName}",
                ];
            }
        }

        if (empty($lines)) {
            \Log::warning('InventoryAccountingService: No journal lines created - check product account configuration', [
                'adjustment_id' => $adjustment->id,
            ]);
            return null;
        }

        $description = "Inventory Adjustment: {$adjustment->reference}";
        if ($adjustment->reason) {
            $description .= " - {$adjustment->reason}";
        }

        try {
            $journalEntry = $this->accountingService->createJournalEntry(
                $adjustment->adjustment_date,
                $description,
                $lines,
                'inventory_adjustment',
                $adjustment->id,
                true
            );

            \Log::info('InventoryAccountingService: Journal entry created', [
                'adjustment_id' => $adjustment->id,
                'journal_entry_id' => $journalEntry?->id,
            ]);

            return $journalEntry;
        } catch (\Exception $e) {
            \Log::error('InventoryAccountingService: Failed to create journal entry', [
                'adjustment_id' => $adjustment->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get stock valuation account for a product.
     * Uses product's configured account, no fallback - must be configured.
     */
    protected function getStockValuationAccount(Product $product): ?ChartOfAccount
    {
        if ($product->stock_valuation_account_id) {
            return $product->stockValuationAccount;
        }

        \Log::warning('Product missing stock_valuation_account', [
            'product_id' => $product->id,
            'product_sku' => $product->sku,
        ]);

        return null;
    }

    /**
     * Get stock input account for a product (Accounts Payable / Goods Received).
     * Uses product's configured account, no fallback - must be configured.
     */
    protected function getStockInputAccount(Product $product): ?ChartOfAccount
    {
        if ($product->stock_input_account_id) {
            return $product->stockInputAccount;
        }

        \Log::warning('Product missing stock_input_account', [
            'product_id' => $product->id,
            'product_sku' => $product->sku,
        ]);

        return null;
    }

    /**
     * Get stock output account for a product (Cost of Goods Sold).
     * Uses product's configured account, no fallback - must be configured.
     */
    protected function getStockOutputAccount(Product $product): ?ChartOfAccount
    {
        if ($product->stock_output_account_id) {
            return $product->stockOutputAccount;
        }

        \Log::warning('Product missing stock_output_account', [
            'product_id' => $product->id,
            'product_sku' => $product->sku,
        ]);

        return null;
    }

    /**
     * Calculate inventory value for a product.
     */
    public function calculateInventoryValue(Product $product, ?string $branchId = null): int
    {
        $query = $product->stockLevels();

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $totalQty = $query->sum('quantity_on_hand');

        return $totalQty * $product->cost_price_minor;
    }

    /**
     * Calculate total inventory value.
     */
    public function calculateTotalInventoryValue(?string $branchId = null): int
    {
        $products = Product::with('stockLevels')->get();
        $totalValue = 0;

        foreach ($products as $product) {
            $totalValue += $this->calculateInventoryValue($product, $branchId);
        }

        return $totalValue;
    }
}
