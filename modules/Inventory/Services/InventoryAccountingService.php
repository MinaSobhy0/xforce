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

    // Default account codes (can be configured in settings)
    protected string $defaultStockValuationCode = '1400'; // Inventory Asset
    protected string $defaultStockInputCode = '2100'; // Stock Received Not Invoiced / AP
    protected string $defaultStockOutputCode = '5100'; // Cost of Goods Sold
    protected string $defaultAdjustmentExpenseCode = '6200'; // Inventory Adjustment Expense
    protected string $defaultAdjustmentIncomeCode = '4900'; // Inventory Adjustment Income

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
     */
    public function createAdjustmentJournalEntry(InventoryAdjustment $adjustment): ?JournalEntry
    {
        if ($adjustment->total_value_adjustment_minor === 0) {
            return null; // No value change
        }

        $lines = [];
        $totalPositive = 0;
        $totalNegative = 0;

        foreach ($adjustment->lines as $line) {
            if ($line->isNoChange()) {
                continue;
            }

            $product = $line->product;
            $stockValuationAccount = $this->getStockValuationAccount($product);

            if (!$stockValuationAccount) {
                continue;
            }

            $absValue = abs($line->value_adjustment_minor);

            if ($line->isPositiveAdjustment()) {
                // Stock increase - Debit Inventory, Credit Adjustment Income
                $lines[] = [
                    'account_code' => $stockValuationAccount->code,
                    'debit' => $absValue,
                    'credit' => 0,
                    'description' => "Stock increase: {$product->name}",
                ];
                $totalPositive += $absValue;
            } else {
                // Stock decrease - Debit Adjustment Expense, Credit Inventory
                $lines[] = [
                    'account_code' => $stockValuationAccount->code,
                    'debit' => 0,
                    'credit' => $absValue,
                    'description' => "Stock decrease: {$product->name}",
                ];
                $totalNegative += $absValue;
            }
        }

        // Add the offsetting entries
        if ($totalPositive > 0) {
            $adjustmentIncomeAccount = $this->getAdjustmentIncomeAccount();
            if ($adjustmentIncomeAccount) {
                $lines[] = [
                    'account_code' => $adjustmentIncomeAccount->code,
                    'debit' => 0,
                    'credit' => $totalPositive,
                    'description' => 'Inventory adjustment gain',
                ];
            }
        }

        if ($totalNegative > 0) {
            $adjustmentExpenseAccount = $this->getAdjustmentExpenseAccount();
            if ($adjustmentExpenseAccount) {
                $lines[] = [
                    'account_code' => $adjustmentExpenseAccount->code,
                    'debit' => $totalNegative,
                    'credit' => 0,
                    'description' => 'Inventory adjustment loss',
                ];
            }
        }

        if (empty($lines)) {
            return null;
        }

        $description = "Inventory Adjustment: {$adjustment->reference}";
        if ($adjustment->reason) {
            $description .= " - {$adjustment->reason}";
        }

        return $this->accountingService->createJournalEntry(
            $adjustment->adjustment_date,
            $description,
            $lines,
            'inventory_adjustment',
            $adjustment->id,
            true
        );
    }

    /**
     * Get stock valuation account for a product.
     */
    protected function getStockValuationAccount(Product $product): ?ChartOfAccount
    {
        if ($product->stock_valuation_account_id) {
            return ChartOfAccount::find($product->stock_valuation_account_id);
        }

        // Try to find default inventory account
        return ChartOfAccount::where('code', $this->defaultStockValuationCode)
            ->orWhere('sub_type', 'inventory')
            ->first();
    }

    /**
     * Get stock input account for a product.
     */
    protected function getStockInputAccount(Product $product): ?ChartOfAccount
    {
        if ($product->stock_input_account_id) {
            return ChartOfAccount::find($product->stock_input_account_id);
        }

        return ChartOfAccount::where('code', $this->defaultStockInputCode)
            ->orWhere('sub_type', 'accounts_payable')
            ->first();
    }

    /**
     * Get stock output account for a product.
     */
    protected function getStockOutputAccount(Product $product): ?ChartOfAccount
    {
        if ($product->stock_output_account_id) {
            return ChartOfAccount::find($product->stock_output_account_id);
        }

        return ChartOfAccount::where('code', $this->defaultStockOutputCode)
            ->orWhere('sub_type', 'cost_of_goods')
            ->first();
    }

    /**
     * Get adjustment expense account.
     */
    protected function getAdjustmentExpenseAccount(): ?ChartOfAccount
    {
        return ChartOfAccount::where('code', $this->defaultAdjustmentExpenseCode)
            ->orWhere('sub_type', 'operating_expense')
            ->first();
    }

    /**
     * Get adjustment income account.
     */
    protected function getAdjustmentIncomeAccount(): ?ChartOfAccount
    {
        return ChartOfAccount::where('code', $this->defaultAdjustmentIncomeCode)
            ->orWhere('sub_type', 'other_income')
            ->first();
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
