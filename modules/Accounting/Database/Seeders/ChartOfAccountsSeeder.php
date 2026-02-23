<?php

namespace Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Accounting\Models\ChartOfAccount;
use Illuminate\Support\Str;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Resolve the tenant ID from various sources.
     */
    protected function resolveTenantId(): ?string
    {
        // Try TenantManager first
        try {
            $tenantManager = app(\XLinic\Framework\Core\Tenancy\TenantManager::class);
            if ($tenantManager->current()) {
                return $tenantManager->current()->id;
            }
        } catch (\Exception $e) {
            // Ignore
        }

        // Try app('currentTenant')
        try {
            if ($tenant = app('currentTenant')) {
                return $tenant->id;
            }
        } catch (\Exception $e) {
            // Ignore
        }

        // Try to resolve from database search_path (for CLI seeding)
        // Check both default and tenant connections
        foreach (['pgsql', 'tenant'] as $conn) {
            try {
                $result = \DB::connection($conn)->select('SHOW search_path');
                $searchPath = $result[0]->search_path ?? 'public';

                // Extract tenant slug from search path (e.g., "tenant_clinic" -> "clinic")
                if (preg_match('/tenant[_-]([^,\s"]+)/', $searchPath, $matches)) {
                    $slug = str_replace('_', '-', $matches[1]);

                    // Find tenant by slug using central connection
                    $tenant = \DB::connection('pgsql')
                        ->table('tenants')
                        ->where('slug', $slug)
                        ->orWhere('slug', $matches[1])
                        ->first();

                    if ($tenant) {
                        return $tenant->id;
                    }
                }
            } catch (\Exception $e) {
                // Ignore errors
            }
        }

        return null;
    }

    public function run(): void
    {
        $accounts = [
            // Assets (1000-1999)
            ['code' => '1000', 'name' => 'Cash', 'type' => 'asset', 'parent_code' => null],
            ['code' => '1010', 'name' => 'Petty Cash', 'type' => 'asset', 'parent_code' => '1000'],
            ['code' => '1020', 'name' => 'Bank Account', 'type' => 'asset', 'parent_code' => '1000'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'parent_code' => null],
            ['code' => '1110', 'name' => 'Patient Receivables', 'type' => 'asset', 'parent_code' => '1100'],
            ['code' => '1120', 'name' => 'Insurance Receivables', 'type' => 'asset', 'parent_code' => '1100'],
            ['code' => '1200', 'name' => 'Inventory', 'type' => 'asset', 'parent_code' => null],
            ['code' => '1210', 'name' => 'Medical Supplies', 'type' => 'asset', 'parent_code' => '1200'],
            ['code' => '1220', 'name' => 'Consumables', 'type' => 'asset', 'parent_code' => '1200'],
            ['code' => '1300', 'name' => 'Prepaid Expenses', 'type' => 'asset', 'parent_code' => null],
            ['code' => '1500', 'name' => 'Fixed Assets', 'type' => 'asset', 'parent_code' => null],
            ['code' => '1510', 'name' => 'Medical Equipment', 'type' => 'asset', 'parent_code' => '1500'],
            ['code' => '1520', 'name' => 'Furniture & Fixtures', 'type' => 'asset', 'parent_code' => '1500'],
            ['code' => '1530', 'name' => 'Computers & Software', 'type' => 'asset', 'parent_code' => '1500'],
            ['code' => '1600', 'name' => 'Accumulated Depreciation', 'type' => 'asset', 'parent_code' => null],

            // Liabilities (2000-2999)
            ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability', 'parent_code' => null],
            ['code' => '2010', 'name' => 'Supplier Payables', 'type' => 'liability', 'parent_code' => '2000'],
            ['code' => '2100', 'name' => 'Accrued Expenses', 'type' => 'liability', 'parent_code' => null],
            ['code' => '2110', 'name' => 'Accrued Salaries', 'type' => 'liability', 'parent_code' => '2100'],
            ['code' => '2120', 'name' => 'Accrued Utilities', 'type' => 'liability', 'parent_code' => '2100'],
            ['code' => '2200', 'name' => 'Deferred Revenue', 'type' => 'liability', 'parent_code' => null],
            ['code' => '2210', 'name' => 'Prepaid Treatments', 'type' => 'liability', 'parent_code' => '2200'],
            ['code' => '2220', 'name' => 'Gift Card Liability', 'type' => 'liability', 'parent_code' => '2200'],
            ['code' => '2230', 'name' => 'Membership Deposits', 'type' => 'liability', 'parent_code' => '2200'],
            ['code' => '2300', 'name' => 'Taxes Payable', 'type' => 'liability', 'parent_code' => null],
            ['code' => '2310', 'name' => 'VAT Payable', 'type' => 'liability', 'parent_code' => '2300'],
            ['code' => '2320', 'name' => 'Income Tax Payable', 'type' => 'liability', 'parent_code' => '2300'],
            ['code' => '2400', 'name' => 'Loans Payable', 'type' => 'liability', 'parent_code' => null],

            // Equity (3000-3999)
            ['code' => '3000', 'name' => 'Owner\'s Equity', 'type' => 'equity', 'parent_code' => null],
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity', 'parent_code' => null],
            ['code' => '3200', 'name' => 'Current Year Earnings', 'type' => 'equity', 'parent_code' => null],

            // Revenue (4000-4999)
            ['code' => '4000', 'name' => 'Service Revenue', 'type' => 'revenue', 'parent_code' => null],
            ['code' => '4010', 'name' => 'Treatment Revenue', 'type' => 'revenue', 'parent_code' => '4000'],
            ['code' => '4020', 'name' => 'Consultation Revenue', 'type' => 'revenue', 'parent_code' => '4000'],
            ['code' => '4030', 'name' => 'Package Revenue', 'type' => 'revenue', 'parent_code' => '4000'],
            ['code' => '4100', 'name' => 'Product Sales', 'type' => 'revenue', 'parent_code' => null],
            ['code' => '4200', 'name' => 'Membership Revenue', 'type' => 'revenue', 'parent_code' => null],
            ['code' => '4300', 'name' => 'Other Income', 'type' => 'revenue', 'parent_code' => null],
            ['code' => '4310', 'name' => 'Late Payment Fees', 'type' => 'revenue', 'parent_code' => '4300'],
            ['code' => '4320', 'name' => 'Cancellation Fees', 'type' => 'revenue', 'parent_code' => '4300'],

            // Expenses (5000-5999)
            ['code' => '5000', 'name' => 'Cost of Services', 'type' => 'expense', 'parent_code' => null],
            ['code' => '5010', 'name' => 'Medical Supplies Used', 'type' => 'expense', 'parent_code' => '5000'],
            ['code' => '5020', 'name' => 'Consumables Used', 'type' => 'expense', 'parent_code' => '5000'],
            ['code' => '5100', 'name' => 'Salaries & Wages', 'type' => 'expense', 'parent_code' => null],
            ['code' => '5110', 'name' => 'Staff Salaries', 'type' => 'expense', 'parent_code' => '5100'],
            ['code' => '5120', 'name' => 'Commissions', 'type' => 'expense', 'parent_code' => '5100'],
            ['code' => '5130', 'name' => 'Bonuses', 'type' => 'expense', 'parent_code' => '5100'],
            ['code' => '5200', 'name' => 'Rent & Utilities', 'type' => 'expense', 'parent_code' => null],
            ['code' => '5210', 'name' => 'Rent Expense', 'type' => 'expense', 'parent_code' => '5200'],
            ['code' => '5220', 'name' => 'Electricity', 'type' => 'expense', 'parent_code' => '5200'],
            ['code' => '5230', 'name' => 'Water', 'type' => 'expense', 'parent_code' => '5200'],
            ['code' => '5240', 'name' => 'Internet & Phone', 'type' => 'expense', 'parent_code' => '5200'],
            ['code' => '5300', 'name' => 'Marketing & Advertising', 'type' => 'expense', 'parent_code' => null],
            ['code' => '5310', 'name' => 'Online Advertising', 'type' => 'expense', 'parent_code' => '5300'],
            ['code' => '5320', 'name' => 'Print Advertising', 'type' => 'expense', 'parent_code' => '5300'],
            ['code' => '5330', 'name' => 'SMS & WhatsApp Costs', 'type' => 'expense', 'parent_code' => '5300'],
            ['code' => '5400', 'name' => 'Insurance', 'type' => 'expense', 'parent_code' => null],
            ['code' => '5500', 'name' => 'Depreciation', 'type' => 'expense', 'parent_code' => null],
            ['code' => '5600', 'name' => 'Professional Fees', 'type' => 'expense', 'parent_code' => null],
            ['code' => '5610', 'name' => 'Accounting Fees', 'type' => 'expense', 'parent_code' => '5600'],
            ['code' => '5620', 'name' => 'Legal Fees', 'type' => 'expense', 'parent_code' => '5600'],
            ['code' => '5700', 'name' => 'Bank Charges', 'type' => 'expense', 'parent_code' => null],
            ['code' => '5800', 'name' => 'Maintenance & Repairs', 'type' => 'expense', 'parent_code' => null],
            ['code' => '5810', 'name' => 'Equipment Maintenance', 'type' => 'expense', 'parent_code' => '5800'],
            ['code' => '5820', 'name' => 'Facility Maintenance', 'type' => 'expense', 'parent_code' => '5800'],
            ['code' => '5900', 'name' => 'Other Expenses', 'type' => 'expense', 'parent_code' => null],
            ['code' => '5910', 'name' => 'Office Supplies', 'type' => 'expense', 'parent_code' => '5900'],
            ['code' => '5920', 'name' => 'Travel & Transportation', 'type' => 'expense', 'parent_code' => '5900'],
        ];

        $tenantId = $this->resolveTenantId();

        foreach ($accounts as $account) {
            $parentId = null;
            if ($account['parent_code']) {
                $parent = ChartOfAccount::where('code', $account['parent_code'])->first();
                $parentId = $parent?->id;
            }

            ChartOfAccount::firstOrCreate(
                ['code' => $account['code'], 'tenant_id' => $tenantId],
                [
                    'id' => Str::orderedUuid()->toString(),
                    'tenant_id' => $tenantId,
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'parent_id' => $parentId,
                    'is_active' => true,
                    'is_system' => true,
                ]
            );
        }
    }
}
