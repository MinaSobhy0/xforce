<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Models\ChartOfAccount;
use Modules\Core\Models\Setting;

class DefaultAccountsService
{
    /**
     * Account setting keys mapped to fallback codes.
     */
    protected array $fallbackCodes = [
        // Receivables
        'default_patient_receivable_account_id' => ['1110', '1100'],
        'default_supplier_receivable_account_id' => ['1120', '1100'],
        'default_staff_receivable_account_id' => ['1130', '1100'],

        // Payables
        'default_patient_payable_account_id' => ['2030', '2000'],
        'default_supplier_payable_account_id' => ['2010', '2000'],
        'default_staff_payable_account_id' => ['2020', '2000'],

        // Revenue
        'default_service_revenue_account_id' => ['4110', '4100', '4000'],
        'default_product_revenue_account_id' => ['4200', '4100', '4000'],
        'default_other_income_account_id' => ['4500', '4000'],

        // Expenses
        'default_cost_of_goods_account_id' => ['5010', '5000'],
        'default_expense_account_id' => ['5100', '5000'],
        'default_discount_account_id' => ['5194', '5100'],

        // Bank & Cash
        'default_cash_account_id' => ['1010', '1000'],
        'default_bank_account_id' => ['1030', '1000'],

        // Tax
        'default_tax_payable_account_id' => ['2310', '2300'],
        'default_tax_receivable_account_id' => ['1140', '1100'],

        // Inventory
        'default_stock_valuation_account_id' => ['1210', '1200'],
        'default_stock_input_account_id' => ['1211', '1210', '1200'],
        'default_stock_output_account_id' => ['5010', '5000'],

        // Other
        'default_rounding_account_id' => ['5195', '5100'],
        'default_exchange_diff_account_id' => ['5196', '5100'],
        'default_retained_earnings_account_id' => ['3200', '3000'],
    ];

    /**
     * Get a default account by setting key.
     */
    public function getAccount(string $settingKey): ?ChartOfAccount
    {
        // First try to get from settings
        $accountId = Setting::getValue($settingKey);

        if ($accountId) {
            $account = ChartOfAccount::find($accountId);
            if ($account && $account->is_active) {
                return $account;
            }
        }

        // Fall back to code-based lookup
        return $this->getAccountByFallbackCodes($settingKey);
    }

    /**
     * Get account using fallback codes.
     */
    protected function getAccountByFallbackCodes(string $settingKey): ?ChartOfAccount
    {
        $codes = $this->fallbackCodes[$settingKey] ?? [];

        if (empty($codes)) {
            return null;
        }

        return ChartOfAccount::whereIn('code', $codes)
            ->where('is_active', true)
            ->orderByRaw("CASE code " . collect($codes)->map(fn ($code, $i) => "WHEN '{$code}' THEN {$i}")->join(' ') . " ELSE 999 END")
            ->first();
    }

    // ===== Receivable Accounts =====

    public function getPatientReceivableAccount(): ?ChartOfAccount
    {
        return $this->getAccount('default_patient_receivable_account_id');
    }

    public function getSupplierReceivableAccount(): ?ChartOfAccount
    {
        return $this->getAccount('default_supplier_receivable_account_id');
    }

    public function getStaffReceivableAccount(): ?ChartOfAccount
    {
        return $this->getAccount('default_staff_receivable_account_id');
    }

    // ===== Payable Accounts =====

    public function getPatientPayableAccount(): ?ChartOfAccount
    {
        return $this->getAccount('default_patient_payable_account_id');
    }

    public function getSupplierPayableAccount(): ?ChartOfAccount
    {
        return $this->getAccount('default_supplier_payable_account_id');
    }

    public function getStaffPayableAccount(): ?ChartOfAccount
    {
        return $this->getAccount('default_staff_payable_account_id');
    }

    // ===== Revenue Accounts =====

    public function getServiceRevenueAccount(): ?ChartOfAccount
    {
        return $this->getAccount('default_service_revenue_account_id');
    }

    public function getProductRevenueAccount(): ?ChartOfAccount
    {
        return $this->getAccount('default_product_revenue_account_id');
    }

    public function getOtherIncomeAccount(): ?ChartOfAccount
    {
        return $this->getAccount('default_other_income_account_id');
    }

    // ===== Expense Accounts =====

    public function getCostOfGoodsAccount(): ?ChartOfAccount
    {
        return $this->getAccount('default_cost_of_goods_account_id');
    }

    public function getExpenseAccount(): ?ChartOfAccount
    {
        return $this->getAccount('default_expense_account_id');
    }

    public function getDiscountAccount(): ?ChartOfAccount
    {
        return $this->getAccount('default_discount_account_id');
    }

    // ===== Bank & Cash Accounts =====

    public function getCashAccount(): ?ChartOfAccount
    {
        return $this->getAccount('default_cash_account_id');
    }

    public function getBankAccount(): ?ChartOfAccount
    {
        return $this->getAccount('default_bank_account_id');
    }

    // ===== Tax Accounts =====

    public function getTaxPayableAccount(): ?ChartOfAccount
    {
        return $this->getAccount('default_tax_payable_account_id');
    }

    public function getTaxReceivableAccount(): ?ChartOfAccount
    {
        return $this->getAccount('default_tax_receivable_account_id');
    }

    // ===== Inventory Accounts =====

    public function getStockValuationAccount(): ?ChartOfAccount
    {
        return $this->getAccount('default_stock_valuation_account_id');
    }

    public function getStockInputAccount(): ?ChartOfAccount
    {
        return $this->getAccount('default_stock_input_account_id');
    }

    public function getStockOutputAccount(): ?ChartOfAccount
    {
        return $this->getAccount('default_stock_output_account_id');
    }

    // ===== Other Accounts =====

    public function getRoundingAccount(): ?ChartOfAccount
    {
        return $this->getAccount('default_rounding_account_id');
    }

    public function getExchangeDiffAccount(): ?ChartOfAccount
    {
        return $this->getAccount('default_exchange_diff_account_id');
    }

    public function getRetainedEarningsAccount(): ?ChartOfAccount
    {
        return $this->getAccount('default_retained_earnings_account_id');
    }

    // ===== Helper Methods =====

    /**
     * Get receivable account based on partner type.
     */
    public function getReceivableAccountForPartner(string $partnerType): ?ChartOfAccount
    {
        return match ($partnerType) {
            'patient' => $this->getPatientReceivableAccount(),
            'supplier' => $this->getSupplierReceivableAccount(),
            'staff' => $this->getStaffReceivableAccount(),
            default => $this->getPatientReceivableAccount(),
        };
    }

    /**
     * Get payable account based on partner type.
     */
    public function getPayableAccountForPartner(string $partnerType): ?ChartOfAccount
    {
        return match ($partnerType) {
            'patient' => $this->getPatientPayableAccount(),
            'supplier' => $this->getSupplierPayableAccount(),
            'staff' => $this->getStaffPayableAccount(),
            default => $this->getSupplierPayableAccount(),
        };
    }

    /**
     * Get revenue account based on item type.
     */
    public function getRevenueAccountForItem(string $itemType): ?ChartOfAccount
    {
        return match ($itemType) {
            'service' => $this->getServiceRevenueAccount(),
            'product' => $this->getProductRevenueAccount(),
            default => $this->getServiceRevenueAccount(),
        };
    }
}
