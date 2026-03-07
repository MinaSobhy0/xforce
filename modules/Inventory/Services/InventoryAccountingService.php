<?php

namespace Modules\Inventory\Services;

use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\AccountingIntegrationService;
use Modules\Accounting\Services\DefaultAccountsService;
use Modules\Billing\Models\TaxRate;
use Modules\Inventory\Models\InventoryAdjustment;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Models\VendorBill;

class InventoryAccountingService
{
    protected AccountingIntegrationService $accountingService;
    protected DefaultAccountsService $defaultAccounts;

    public function __construct(AccountingIntegrationService $accountingService)
    {
        $this->accountingService = $accountingService;
        $this->defaultAccounts = new DefaultAccountsService();
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
     * Debit: Expense Account (COGS) - from product's expense_account_id
     * Credit: Inventory Asset
     */
    public function createStockConsumptionEntry(
        StockMovement $movement,
        int $valueMajor,
        ?string $description = null
    ): ?JournalEntry {
        $product = $movement->product;

        $stockValuationAccount = $this->getStockValuationAccount($product);
        $expenseAccount = $this->getExpenseAccount($product);

        if (!$stockValuationAccount || !$expenseAccount) {
            return null;
        }

        $lines = [
            [
                'account_code' => $expenseAccount->code,
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
     * Uses product-specific accounts with fallback to defaults.
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
                // Stock increase (gain): Debit Inventory (valuation), Credit Stock Input
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
                    'description' => "Inventory gain: {$productName} (+{$line->difference_qty})",
                ];
                $lines[] = [
                    'account_code' => $stockInputAccount->code,
                    'debit' => 0,
                    'credit' => $absValue,
                    'description' => "Inventory gain: {$productName} (+{$line->difference_qty})",
                ];
            } else {
                // Stock decrease (loss): Debit Stock Output (expense), Credit Inventory (valuation)
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
                    'description' => "Inventory loss: {$productName} ({$line->difference_qty})",
                ];
                $lines[] = [
                    'account_code' => $stockValuationAccount->code,
                    'debit' => 0,
                    'credit' => $absValue,
                    'description' => "Inventory loss: {$productName} ({$line->difference_qty})",
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
     * Uses product's configured account with fallback to defaults.
     */
    protected function getStockValuationAccount(Product $product): ?ChartOfAccount
    {
        // First try product-specific account
        if ($product->stock_valuation_account_id) {
            return $product->stockValuationAccount;
        }

        // Fallback to system default
        $defaultAccount = $this->defaultAccounts->getStockValuationAccount();
        if ($defaultAccount) {
            return $defaultAccount;
        }

        \Log::warning('Product missing stock_valuation_account and no default configured', [
            'product_id' => $product->id,
            'product_sku' => $product->sku,
        ]);

        return null;
    }

    /**
     * Get stock input account for a product (Accounts Payable / Goods Received).
     * Uses product's configured account with fallback to defaults.
     */
    protected function getStockInputAccount(Product $product): ?ChartOfAccount
    {
        // First try product-specific account
        if ($product->stock_input_account_id) {
            return $product->stockInputAccount;
        }

        // Fallback to system default
        $defaultAccount = $this->defaultAccounts->getStockInputAccount();
        if ($defaultAccount) {
            return $defaultAccount;
        }

        \Log::warning('Product missing stock_input_account and no default configured', [
            'product_id' => $product->id,
            'product_sku' => $product->sku,
        ]);

        return null;
    }

    /**
     * Get stock output account for a product (Cost of Goods Sold).
     * Uses product's configured account with fallback to defaults.
     */
    protected function getStockOutputAccount(Product $product): ?ChartOfAccount
    {
        // First try product-specific account
        if ($product->stock_output_account_id) {
            return $product->stockOutputAccount;
        }

        // Fallback to system default
        $defaultAccount = $this->defaultAccounts->getStockOutputAccount();
        if ($defaultAccount) {
            return $defaultAccount;
        }

        \Log::warning('Product missing stock_output_account and no default configured', [
            'product_id' => $product->id,
            'product_sku' => $product->sku,
        ]);

        return null;
    }

    /**
     * Get expense account for a product (for consumable products).
     * Uses product's configured account with fallback to defaults.
     */
    protected function getExpenseAccount(Product $product): ?ChartOfAccount
    {
        // First try product-specific account
        if ($product->expense_account_id) {
            return $product->expenseAccount;
        }

        // Fallback to system default
        $defaultAccount = $this->defaultAccounts->getExpenseAccount();
        if ($defaultAccount) {
            return $defaultAccount;
        }

        \Log::warning('Product missing expense_account and no default configured', [
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

    /**
     * Create journal entry for vendor bill.
     * Debit: Inventory/Expense (per line - subtotal without tax)
     * Debit: Tax Receivable (per tax account - for positive taxes like VAT)
     * Credit: Tax Payable (for negative taxes like Withholding)
     * Credit: Accounts Payable (total including tax)
     */
    public function createVendorBillJournalEntry(VendorBill $bill): ?JournalEntry
    {
        $bill->load('lines.product.stockValuationAccount', 'lines.product.expenseAccount', 'supplier');

        // Get Accounts Payable account from defaults
        $apAccount = $this->defaultAccounts->getSupplierPayableAccount();

        if (!$apAccount) {
            \Log::error('VendorBill: Accounts Payable account not found');
            return null;
        }

        $lines = [];
        $totalAmount = 0;
        $taxByAccount = []; // [account_id => ['amount' => int, 'is_debit' => bool]]

        foreach ($bill->lines as $line) {
            $product = $line->product;

            // Calculate subtotal without tax (quantity * unit_price - discount)
            $subtotal = (int) round($line->quantity * $line->unit_price_minor);
            if ($line->discount_minor > 0) {
                if ($line->discount_type === 'percent') {
                    $subtotal -= (int) round($subtotal * $line->discount_minor / 100);
                } else {
                    $subtotal -= $line->discount_minor;
                }
            }

            $afterDiscount = max(0, $subtotal);
            $totalAmount += $afterDiscount;

            // Get the appropriate account for this line based on product type
            $debitAccount = null;
            if ($product) {
                // Storable products: Debit Inventory (Stock Valuation Account)
                // Consumable products: Debit Expense Account directly
                if ($product->tracksInventory()) {
                    $debitAccount = $this->getStockValuationAccount($product);
                } else {
                    // Consumable - use expense account
                    $debitAccount = $this->getExpenseAccount($product);
                }
            }

            // Fallback to line's account
            if (!$debitAccount && $line->account_id) {
                $debitAccount = $line->account;
            }

            // Fallback to default stock valuation (for storable) or expense (for consumable)
            if (!$debitAccount) {
                if ($product && !$product->tracksInventory()) {
                    $debitAccount = $this->defaultAccounts->getExpenseAccount();
                } else {
                    $debitAccount = $this->defaultAccounts->getStockValuationAccount();
                }
            }

            if (!$debitAccount) {
                \Log::warning('VendorBill: No debit account found for line', [
                    'line_id' => $line->id,
                    'product_id' => $product?->id,
                ]);
                continue;
            }

            // Debit inventory/expense account (subtotal without tax)
            $lines[] = [
                'account_code' => $debitAccount->code,
                'debit' => $afterDiscount,
                'credit' => 0,
                'description' => $line->description,
            ];

            // Process each tax rate in the tax_rates array
            $taxRates = $line->tax_rates ?? [];
            foreach ($taxRates as $rateValue) {
                $rateFloat = floatval($rateValue);
                if ($rateFloat == 0) {
                    continue;
                }

                // Calculate tax amount for this rate
                $taxAmount = (int) round($afterDiscount * abs($rateFloat) / 100);
                if ($taxAmount <= 0) {
                    continue;
                }

                // Find the tax rate record to get its account
                $taxRate = TaxRate::where('rate', $rateFloat)
                    ->where('type', TaxRate::TYPE_PURCHASE)
                    ->first();

                // Determine tax account based on tax type
                $isPositiveTax = $rateFloat > 0; // VAT is positive, Withholding is negative

                if ($isPositiveTax) {
                    // Positive tax (VAT): Debit Tax Receivable
                    $taxAccountId = $taxRate?->account_id
                        ?? $this->defaultAccounts->getTaxReceivableAccount()?->id;
                } else {
                    // Negative tax (Withholding): Credit Tax Payable
                    $taxAccountId = $taxRate?->account_id
                        ?? $this->defaultAccounts->getTaxPayableAccount()?->id;
                }

                if ($taxAccountId) {
                    $key = $taxAccountId . '_' . ($isPositiveTax ? 'debit' : 'credit');
                    if (!isset($taxByAccount[$key])) {
                        $taxByAccount[$key] = [
                            'account_id' => $taxAccountId,
                            'amount' => 0,
                            'is_debit' => $isPositiveTax,
                        ];
                    }
                    $taxByAccount[$key]['amount'] += $taxAmount;
                }

                // Adjust total amount: add positive tax, subtract negative (withholding)
                if ($isPositiveTax) {
                    $totalAmount += $taxAmount;
                } else {
                    $totalAmount -= $taxAmount;
                }
            }
        }

        if (empty($lines)) {
            \Log::warning('VendorBill: No journal lines created');
            return null;
        }

        // Add tax lines (separate line per tax account)
        foreach ($taxByAccount as $taxData) {
            if ($taxData['amount'] > 0) {
                $taxAccount = ChartOfAccount::find($taxData['account_id']);
                if ($taxAccount) {
                    if ($taxData['is_debit']) {
                        // Positive tax (VAT): Debit Tax Receivable
                        $lines[] = [
                            'account_code' => $taxAccount->code,
                            'debit' => $taxData['amount'],
                            'credit' => 0,
                            'description' => "Input VAT on bill {$bill->code}",
                        ];
                    } else {
                        // Negative tax (Withholding): Credit Tax Payable
                        $lines[] = [
                            'account_code' => $taxAccount->code,
                            'debit' => 0,
                            'credit' => $taxData['amount'],
                            'description' => "Withholding tax on bill {$bill->code}",
                        ];
                    }
                }
            }
        }

        // Credit Accounts Payable for total (subtotal + VAT - withholding)
        $lines[] = [
            'account_code' => $apAccount->code,
            'debit' => 0,
            'credit' => $totalAmount,
            'description' => "Vendor Bill: {$bill->code}",
        ];

        // Get Purchase Journal
        $purchaseJournal = Journal::getPurchaseJournal();
        if (!$purchaseJournal) {
            \Log::warning('VendorBill: Purchase journal not found, using General Journal');
            $purchaseJournal = Journal::getMiscJournal();
        }

        if (!$purchaseJournal) {
            \Log::error('VendorBill: No suitable journal found');
            return null;
        }

        $supplierName = $bill->supplier?->getTranslation('name', 'en') ?? 'Vendor';

        try {
            // Create journal entry directly with proper reference (bill code)
            $entry = JournalEntry::create([
                'tenant_id' => $bill->tenant_id,
                'journal_id' => $purchaseJournal->id,
                'date' => $bill->bill_date ?? now(),
                'reference' => $bill->code,
                'description' => "Vendor Bill {$bill->code} - {$supplierName}",
                'source_type' => VendorBill::class,
                'source_id' => $bill->id,
            ]);

            // Create journal entry lines
            foreach ($lines as $lineData) {
                $account = ChartOfAccount::where('code', $lineData['account_code'])->first();
                if (!$account) {
                    \Log::warning('VendorBill: Account not found', ['code' => $lineData['account_code']]);
                    continue;
                }

                $entry->lines()->create([
                    'tenant_id' => $bill->tenant_id,
                    'account_id' => $account->id,
                    'debit_minor' => $lineData['debit'] ?? 0,
                    'credit_minor' => $lineData['credit'] ?? 0,
                    'description' => $lineData['description'] ?? null,
                    'branch_id' => $bill->branch_id,
                    'partner_type' => $bill->supplier_id ? Supplier::class : null,
                    'partner_id' => $bill->supplier_id,
                ]);
            }

            // Recalculate and post
            $entry->recalculateTotals();
            $entry->post();

            \Log::info('VendorBill: Journal entry created', [
                'bill_id' => $bill->id,
                'bill_code' => $bill->code,
                'entry_id' => $entry->id,
            ]);

            return $entry;
        } catch (\Exception $e) {
            \Log::error('VendorBill: Failed to create journal entry', [
                'bill_id' => $bill->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Create journal entry for vendor bill payment.
     * Debit: Accounts Payable
     * Credit: Cash/Bank
     */
    public function createVendorPaymentJournalEntry(
        VendorBill $bill,
        int $amountMinor,
        string $journalType = 'cash'
    ): ?JournalEntry {
        // Get Accounts Payable account from defaults
        $apAccount = $this->defaultAccounts->getSupplierPayableAccount();
        if (!$apAccount) {
            return null;
        }

        // Get Cash or Bank account from defaults based on journal type
        $paymentAccount = $journalType === 'bank'
            ? $this->defaultAccounts->getBankAccount()
            : $this->defaultAccounts->getCashAccount();

        if (!$paymentAccount) {
            return null;
        }

        $lines = [
            [
                'account_code' => $apAccount->code,
                'debit' => $amountMinor,
                'credit' => 0,
                'description' => "Payment for Bill: {$bill->code}",
            ],
            [
                'account_code' => $paymentAccount->code,
                'debit' => 0,
                'credit' => $amountMinor,
                'description' => "Payment for Bill: {$bill->code}",
            ],
        ];

        $supplierName = $bill->supplier?->getTranslation('name', 'en') ?? 'Vendor';
        $description = "Payment for Bill {$bill->code} - {$supplierName}";

        try {
            return $this->accountingService->createJournalEntry(
                now(),
                $description,
                $lines,
                'vendor_payment',
                $bill->id,
                true
            );
        } catch (\Exception $e) {
            \Log::error('VendorPayment: Failed to create journal entry', [
                'bill_id' => $bill->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
