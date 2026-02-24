<?php

namespace Modules\Inventory\Services;

use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\AccountingIntegrationService;
use Modules\Billing\Models\TaxRate;
use Modules\Inventory\Models\InventoryAdjustment;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Models\VendorBill;

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

    /**
     * Create journal entry for vendor bill.
     * Debit: Inventory/Expense (per line - subtotal without tax)
     * Debit: Tax Receivable (per tax account)
     * Credit: Accounts Payable (total including tax)
     */
    public function createVendorBillJournalEntry(VendorBill $bill): ?JournalEntry
    {
        $bill->load('lines.product.stockValuationAccount', 'supplier');

        // Get Accounts Payable account (try multiple common codes)
        $apAccount = ChartOfAccount::whereIn('code', ['2000', '2100', '2010'])
            ->where('type', 'liability')
            ->first()
            ?? ChartOfAccount::where('type', 'liability')
                ->where(function ($q) {
                    $q->whereRaw("name->>'en' ILIKE '%payable%'")
                      ->orWhereRaw("name->>'en' ILIKE '%accounts payable%'");
                })
                ->first();

        if (!$apAccount) {
            \Log::error('VendorBill: Accounts Payable account not found');
            return null;
        }

        $lines = [];
        $totalAmount = 0;
        $taxByAccount = [];

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

            $totalAmount += $subtotal;

            // Get the appropriate account for this line
            $debitAccount = null;
            if ($product) {
                $debitAccount = $this->getStockValuationAccount($product);
            }

            // Fallback to line's account or general inventory account
            if (!$debitAccount && $line->account_id) {
                $debitAccount = $line->account;
            }

            if (!$debitAccount) {
                $debitAccount = ChartOfAccount::whereIn('code', ['1200', '1210', '1300'])
                    ->where('type', 'asset')
                    ->first()
                    ?? ChartOfAccount::where('type', 'asset')
                        ->whereRaw("name->>'en' ILIKE '%inventory%'")
                        ->first();
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
                'debit' => $subtotal,
                'credit' => 0,
                'description' => $line->description,
            ];

            // Collect taxes by tax account
            if ($line->tax_minor > 0 && $line->tax_rate > 0) {
                // Find the tax rate and use its configured account
                $taxRate = TaxRate::where('rate', $line->tax_rate)
                    ->where('type', TaxRate::TYPE_PURCHASE)
                    ->first();

                $taxAccountId = $taxRate?->account_id ?? $this->getDefaultTaxReceivableAccount()?->id;

                if ($taxAccountId) {
                    if (!isset($taxByAccount[$taxAccountId])) {
                        $taxByAccount[$taxAccountId] = 0;
                    }
                    $taxByAccount[$taxAccountId] += $line->tax_minor;
                }

                $totalAmount += $line->tax_minor;
            }
        }

        if (empty($lines)) {
            \Log::warning('VendorBill: No journal lines created');
            return null;
        }

        // Debit: Tax accounts (separate line per tax account)
        foreach ($taxByAccount as $accountId => $amount) {
            if ($amount > 0) {
                $taxAccount = ChartOfAccount::find($accountId);
                if ($taxAccount) {
                    $lines[] = [
                        'account_code' => $taxAccount->code,
                        'debit' => $amount,
                        'credit' => 0,
                        'description' => "Input tax on bill {$bill->code}",
                    ];
                }
            }
        }

        // Credit Accounts Payable for total (including tax)
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
     * Get default tax receivable account (Input VAT / Tax Receivable).
     */
    protected function getDefaultTaxReceivableAccount(): ?ChartOfAccount
    {
        return ChartOfAccount::whereIn('code', ['1140', '1150', '1160'])
            ->where('type', 'asset')
            ->where('is_active', true)
            ->first()
            ?? ChartOfAccount::where('type', 'asset')
                ->where(function ($q) {
                    $q->whereRaw("name->>'en' ILIKE '%input%tax%'")
                      ->orWhereRaw("name->>'en' ILIKE '%tax%receivable%'")
                      ->orWhereRaw("name->>'en' ILIKE '%vat%receivable%'");
                })
                ->where('is_active', true)
                ->first();
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
        // Get Accounts Payable account
        $apAccount = ChartOfAccount::where('code', '2000')->first();
        if (!$apAccount) {
            return null;
        }

        // Get Cash or Bank account based on journal type
        $paymentAccount = $journalType === 'bank'
            ? ChartOfAccount::where('code', '1020')->first()
            : ChartOfAccount::where('code', '1000')->first();

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
