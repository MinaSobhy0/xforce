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
        foreach (['pgsql', 'tenant'] as $conn) {
            try {
                $result = \DB::connection($conn)->select('SHOW search_path');
                $searchPath = $result[0]->search_path ?? 'public';

                if (preg_match('/tenant[_-]([^,\s"]+)/', $searchPath, $matches)) {
                    $slug = str_replace('_', '-', $matches[1]);

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
        // Using ChartOfAccount TYPE_* constants for Odoo-compatible types
        $accounts = [
            // ═══════════════════════════════════════════════════════════════
            // ASSETS (1000-1999)
            // ═══════════════════════════════════════════════════════════════

            // Receivable
            ['code' => '1100', 'name' => ['en' => 'Accounts Receivable', 'ar' => 'المدينون'], 'type' => ChartOfAccount::TYPE_RECEIVABLE, 'parent_code' => null],
            ['code' => '1110', 'name' => ['en' => 'Patient Receivables', 'ar' => 'ذمم المرضى المدينة'], 'type' => ChartOfAccount::TYPE_RECEIVABLE, 'parent_code' => '1100'],
            ['code' => '1120', 'name' => ['en' => 'Supplier Receivables', 'ar' => 'ذمم الموردين المدينة'], 'type' => ChartOfAccount::TYPE_RECEIVABLE, 'parent_code' => '1100'],
            ['code' => '1130', 'name' => ['en' => 'Staff Receivables', 'ar' => 'ذمم الموظفين المدينة'], 'type' => ChartOfAccount::TYPE_RECEIVABLE, 'parent_code' => '1100'],
            ['code' => '1140', 'name' => ['en' => 'Tax Receivable (Input VAT)', 'ar' => 'ضريبة مستردة (مدخلات)'], 'type' => ChartOfAccount::TYPE_RECEIVABLE, 'parent_code' => '1100'],

            // Bank and Cash
            ['code' => '1000', 'name' => ['en' => 'Cash and Bank', 'ar' => 'النقدية والبنوك'], 'type' => ChartOfAccount::TYPE_BANK_CASH, 'parent_code' => null],
            ['code' => '1010', 'name' => ['en' => 'Cash on Hand', 'ar' => 'النقدية بالصندوق'], 'type' => ChartOfAccount::TYPE_BANK_CASH, 'parent_code' => '1000'],
            ['code' => '1020', 'name' => ['en' => 'Petty Cash', 'ar' => 'صندوق المصروفات النثرية'], 'type' => ChartOfAccount::TYPE_BANK_CASH, 'parent_code' => '1000'],
            ['code' => '1030', 'name' => ['en' => 'Bank Account', 'ar' => 'الحساب البنكي'], 'type' => ChartOfAccount::TYPE_BANK_CASH, 'parent_code' => '1000'],

            // Current Assets
            ['code' => '1200', 'name' => ['en' => 'Current Assets', 'ar' => 'الأصول المتداولة'], 'type' => ChartOfAccount::TYPE_CURRENT_ASSET, 'parent_code' => null],
            ['code' => '1210', 'name' => ['en' => 'Inventory', 'ar' => 'المخزون'], 'type' => ChartOfAccount::TYPE_CURRENT_ASSET, 'parent_code' => '1200'],
            ['code' => '1211', 'name' => ['en' => 'Medical Supplies', 'ar' => 'المستلزمات الطبية'], 'type' => ChartOfAccount::TYPE_CURRENT_ASSET, 'parent_code' => '1210'],
            ['code' => '1212', 'name' => ['en' => 'Consumables', 'ar' => 'المواد الاستهلاكية'], 'type' => ChartOfAccount::TYPE_CURRENT_ASSET, 'parent_code' => '1210'],
            ['code' => '1220', 'name' => ['en' => 'Short-term Investments', 'ar' => 'استثمارات قصيرة الأجل'], 'type' => ChartOfAccount::TYPE_CURRENT_ASSET, 'parent_code' => '1200'],

            // Prepayments
            ['code' => '1300', 'name' => ['en' => 'Prepayments', 'ar' => 'المصروفات المدفوعة مقدماً'], 'type' => ChartOfAccount::TYPE_PREPAYMENTS, 'parent_code' => null],
            ['code' => '1310', 'name' => ['en' => 'Prepaid Rent', 'ar' => 'إيجار مدفوع مقدماً'], 'type' => ChartOfAccount::TYPE_PREPAYMENTS, 'parent_code' => '1300'],
            ['code' => '1320', 'name' => ['en' => 'Prepaid Insurance', 'ar' => 'تأمين مدفوع مقدماً'], 'type' => ChartOfAccount::TYPE_PREPAYMENTS, 'parent_code' => '1300'],

            // Fixed Assets
            ['code' => '1500', 'name' => ['en' => 'Fixed Assets', 'ar' => 'الأصول الثابتة'], 'type' => ChartOfAccount::TYPE_FIXED_ASSET, 'parent_code' => null],
            ['code' => '1510', 'name' => ['en' => 'Medical Equipment', 'ar' => 'المعدات الطبية'], 'type' => ChartOfAccount::TYPE_FIXED_ASSET, 'parent_code' => '1500'],
            ['code' => '1520', 'name' => ['en' => 'Furniture & Fixtures', 'ar' => 'الأثاث والتجهيزات'], 'type' => ChartOfAccount::TYPE_FIXED_ASSET, 'parent_code' => '1500'],
            ['code' => '1530', 'name' => ['en' => 'Computers & Software', 'ar' => 'الحاسبات والبرمجيات'], 'type' => ChartOfAccount::TYPE_FIXED_ASSET, 'parent_code' => '1500'],
            ['code' => '1540', 'name' => ['en' => 'Vehicles', 'ar' => 'المركبات'], 'type' => ChartOfAccount::TYPE_FIXED_ASSET, 'parent_code' => '1500'],
            ['code' => '1550', 'name' => ['en' => 'Leasehold Improvements', 'ar' => 'تحسينات الإيجار'], 'type' => ChartOfAccount::TYPE_FIXED_ASSET, 'parent_code' => '1500'],
            ['code' => '1600', 'name' => ['en' => 'Accumulated Depreciation', 'ar' => 'مجمع الإهلاك'], 'type' => ChartOfAccount::TYPE_FIXED_ASSET, 'parent_code' => null],
            ['code' => '1610', 'name' => ['en' => 'Accum. Depr. - Equipment', 'ar' => 'مجمع إهلاك المعدات'], 'type' => ChartOfAccount::TYPE_FIXED_ASSET, 'parent_code' => '1600'],
            ['code' => '1620', 'name' => ['en' => 'Accum. Depr. - Furniture', 'ar' => 'مجمع إهلاك الأثاث'], 'type' => ChartOfAccount::TYPE_FIXED_ASSET, 'parent_code' => '1600'],

            // Non-current Assets
            ['code' => '1700', 'name' => ['en' => 'Non-current Assets', 'ar' => 'أصول غير متداولة'], 'type' => ChartOfAccount::TYPE_NON_CURRENT_ASSET, 'parent_code' => null],
            ['code' => '1710', 'name' => ['en' => 'Long-term Investments', 'ar' => 'استثمارات طويلة الأجل'], 'type' => ChartOfAccount::TYPE_NON_CURRENT_ASSET, 'parent_code' => '1700'],
            ['code' => '1720', 'name' => ['en' => 'Security Deposits', 'ar' => 'ودائع الضمان'], 'type' => ChartOfAccount::TYPE_NON_CURRENT_ASSET, 'parent_code' => '1700'],

            // ═══════════════════════════════════════════════════════════════
            // LIABILITIES (2000-2999)
            // ═══════════════════════════════════════════════════════════════

            // Payable
            ['code' => '2000', 'name' => ['en' => 'Accounts Payable', 'ar' => 'الدائنون'], 'type' => ChartOfAccount::TYPE_PAYABLE, 'parent_code' => null],
            ['code' => '2010', 'name' => ['en' => 'Supplier Payables', 'ar' => 'ذمم الموردين الدائنة'], 'type' => ChartOfAccount::TYPE_PAYABLE, 'parent_code' => '2000'],
            ['code' => '2020', 'name' => ['en' => 'Staff Payables', 'ar' => 'ذمم الموظفين الدائنة'], 'type' => ChartOfAccount::TYPE_PAYABLE, 'parent_code' => '2000'],
            ['code' => '2030', 'name' => ['en' => 'Patient Payables (Credits)', 'ar' => 'ذمم المرضى الدائنة'], 'type' => ChartOfAccount::TYPE_PAYABLE, 'parent_code' => '2000'],

            // Credit Card
            ['code' => '2050', 'name' => ['en' => 'Credit Cards', 'ar' => 'بطاقات الائتمان'], 'type' => ChartOfAccount::TYPE_CREDIT_CARD, 'parent_code' => null],

            // Current Liabilities
            ['code' => '2100', 'name' => ['en' => 'Current Liabilities', 'ar' => 'الالتزامات المتداولة'], 'type' => ChartOfAccount::TYPE_CURRENT_LIABILITY, 'parent_code' => null],
            ['code' => '2110', 'name' => ['en' => 'Accrued Salaries', 'ar' => 'رواتب مستحقة'], 'type' => ChartOfAccount::TYPE_CURRENT_LIABILITY, 'parent_code' => '2100'],
            ['code' => '2120', 'name' => ['en' => 'Accrued Expenses', 'ar' => 'مصروفات مستحقة'], 'type' => ChartOfAccount::TYPE_CURRENT_LIABILITY, 'parent_code' => '2100'],
            ['code' => '2200', 'name' => ['en' => 'Deferred Revenue', 'ar' => 'الإيرادات المؤجلة'], 'type' => ChartOfAccount::TYPE_CURRENT_LIABILITY, 'parent_code' => null],
            ['code' => '2210', 'name' => ['en' => 'Prepaid Treatments', 'ar' => 'علاجات مدفوعة مقدماً'], 'type' => ChartOfAccount::TYPE_CURRENT_LIABILITY, 'parent_code' => '2200'],
            ['code' => '2220', 'name' => ['en' => 'Gift Card Liability', 'ar' => 'التزام بطاقات الهدايا'], 'type' => ChartOfAccount::TYPE_CURRENT_LIABILITY, 'parent_code' => '2200'],
            ['code' => '2230', 'name' => ['en' => 'Membership Prepayments', 'ar' => 'مدفوعات العضوية المقدمة'], 'type' => ChartOfAccount::TYPE_CURRENT_LIABILITY, 'parent_code' => '2200'],
            ['code' => '2240', 'name' => ['en' => 'Package Prepayments', 'ar' => 'مدفوعات الباقات المقدمة'], 'type' => ChartOfAccount::TYPE_CURRENT_LIABILITY, 'parent_code' => '2200'],
            ['code' => '2300', 'name' => ['en' => 'Taxes Payable', 'ar' => 'الضرائب المستحقة'], 'type' => ChartOfAccount::TYPE_CURRENT_LIABILITY, 'parent_code' => null],
            ['code' => '2310', 'name' => ['en' => 'VAT Payable', 'ar' => 'ضريبة القيمة المضافة'], 'type' => ChartOfAccount::TYPE_CURRENT_LIABILITY, 'parent_code' => '2300'],
            ['code' => '2320', 'name' => ['en' => 'WHT Payable', 'ar' => 'ضريبة الخصم والإضافة'], 'type' => ChartOfAccount::TYPE_CURRENT_LIABILITY, 'parent_code' => '2300'],
            ['code' => '2330', 'name' => ['en' => 'Income Tax Payable', 'ar' => 'ضريبة الدخل المستحقة'], 'type' => ChartOfAccount::TYPE_CURRENT_LIABILITY, 'parent_code' => '2300'],

            // Non-current Liabilities
            ['code' => '2400', 'name' => ['en' => 'Non-current Liabilities', 'ar' => 'التزامات غير متداولة'], 'type' => ChartOfAccount::TYPE_NON_CURRENT_LIABILITY, 'parent_code' => null],
            ['code' => '2410', 'name' => ['en' => 'Loans Payable', 'ar' => 'القروض'], 'type' => ChartOfAccount::TYPE_NON_CURRENT_LIABILITY, 'parent_code' => '2400'],
            ['code' => '2420', 'name' => ['en' => 'Long-term Lease Liability', 'ar' => 'التزام الإيجار طويل الأجل'], 'type' => ChartOfAccount::TYPE_NON_CURRENT_LIABILITY, 'parent_code' => '2400'],

            // ═══════════════════════════════════════════════════════════════
            // EQUITY (3000-3999)
            // ═══════════════════════════════════════════════════════════════

            ['code' => '3000', 'name' => ['en' => 'Equity', 'ar' => 'حقوق الملكية'], 'type' => ChartOfAccount::TYPE_EQUITY, 'parent_code' => null],
            ['code' => '3100', 'name' => ['en' => 'Owner\'s Capital', 'ar' => 'رأس مال المالك'], 'type' => ChartOfAccount::TYPE_EQUITY, 'parent_code' => '3000'],
            ['code' => '3200', 'name' => ['en' => 'Retained Earnings', 'ar' => 'الأرباح المحتجزة'], 'type' => ChartOfAccount::TYPE_EQUITY, 'parent_code' => '3000'],
            ['code' => '3300', 'name' => ['en' => 'Current Year Earnings', 'ar' => 'أرباح السنة الحالية'], 'type' => ChartOfAccount::TYPE_CURRENT_YEAR_EARNINGS, 'parent_code' => null],
            ['code' => '3400', 'name' => ['en' => 'Owner\'s Drawings', 'ar' => 'مسحوبات المالك'], 'type' => ChartOfAccount::TYPE_EQUITY, 'parent_code' => '3000'],

            // ═══════════════════════════════════════════════════════════════
            // REVENUE (4000-4999)
            // ═══════════════════════════════════════════════════════════════

            // Income
            ['code' => '4000', 'name' => ['en' => 'Revenue', 'ar' => 'الإيرادات'], 'type' => ChartOfAccount::TYPE_INCOME, 'parent_code' => null],
            ['code' => '4100', 'name' => ['en' => 'Service Revenue', 'ar' => 'إيرادات الخدمات'], 'type' => ChartOfAccount::TYPE_INCOME, 'parent_code' => '4000'],
            ['code' => '4110', 'name' => ['en' => 'Treatment Revenue', 'ar' => 'إيرادات العلاجات'], 'type' => ChartOfAccount::TYPE_INCOME, 'parent_code' => '4100'],
            ['code' => '4120', 'name' => ['en' => 'Consultation Revenue', 'ar' => 'إيرادات الاستشارات'], 'type' => ChartOfAccount::TYPE_INCOME, 'parent_code' => '4100'],
            ['code' => '4130', 'name' => ['en' => 'Package Revenue', 'ar' => 'إيرادات الباقات'], 'type' => ChartOfAccount::TYPE_INCOME, 'parent_code' => '4100'],
            ['code' => '4200', 'name' => ['en' => 'Product Sales', 'ar' => 'مبيعات المنتجات'], 'type' => ChartOfAccount::TYPE_INCOME, 'parent_code' => '4000'],
            ['code' => '4300', 'name' => ['en' => 'Membership Revenue', 'ar' => 'إيرادات العضويات'], 'type' => ChartOfAccount::TYPE_INCOME, 'parent_code' => '4000'],

            // Other Income
            ['code' => '4500', 'name' => ['en' => 'Other Income', 'ar' => 'إيرادات أخرى'], 'type' => ChartOfAccount::TYPE_OTHER_INCOME, 'parent_code' => null],
            ['code' => '4510', 'name' => ['en' => 'Late Payment Fees', 'ar' => 'غرامات التأخير'], 'type' => ChartOfAccount::TYPE_OTHER_INCOME, 'parent_code' => '4500'],
            ['code' => '4520', 'name' => ['en' => 'Cancellation Fees', 'ar' => 'رسوم الإلغاء'], 'type' => ChartOfAccount::TYPE_OTHER_INCOME, 'parent_code' => '4500'],
            ['code' => '4530', 'name' => ['en' => 'Interest Income', 'ar' => 'إيرادات الفوائد'], 'type' => ChartOfAccount::TYPE_OTHER_INCOME, 'parent_code' => '4500'],
            ['code' => '4540', 'name' => ['en' => 'Discounts Received', 'ar' => 'خصومات مكتسبة'], 'type' => ChartOfAccount::TYPE_OTHER_INCOME, 'parent_code' => '4500'],

            // ═══════════════════════════════════════════════════════════════
            // EXPENSES (5000-5999)
            // ═══════════════════════════════════════════════════════════════

            // Cost of Revenue
            ['code' => '5000', 'name' => ['en' => 'Cost of Revenue', 'ar' => 'تكلفة الإيرادات'], 'type' => ChartOfAccount::TYPE_COST_OF_REVENUE, 'parent_code' => null],
            ['code' => '5010', 'name' => ['en' => 'Medical Supplies Used', 'ar' => 'المستلزمات الطبية المستخدمة'], 'type' => ChartOfAccount::TYPE_COST_OF_REVENUE, 'parent_code' => '5000'],
            ['code' => '5020', 'name' => ['en' => 'Consumables Used', 'ar' => 'المواد الاستهلاكية المستخدمة'], 'type' => ChartOfAccount::TYPE_COST_OF_REVENUE, 'parent_code' => '5000'],
            ['code' => '5030', 'name' => ['en' => 'Direct Labor', 'ar' => 'العمالة المباشرة'], 'type' => ChartOfAccount::TYPE_COST_OF_REVENUE, 'parent_code' => '5000'],

            // Expenses
            ['code' => '5100', 'name' => ['en' => 'Operating Expenses', 'ar' => 'المصروفات التشغيلية'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => null],
            ['code' => '5110', 'name' => ['en' => 'Salaries & Wages', 'ar' => 'الرواتب والأجور'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5100'],
            ['code' => '5111', 'name' => ['en' => 'Staff Salaries', 'ar' => 'رواتب الموظفين'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5110'],
            ['code' => '5112', 'name' => ['en' => 'Commissions', 'ar' => 'العمولات'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5110'],
            ['code' => '5113', 'name' => ['en' => 'Bonuses', 'ar' => 'المكافآت'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5110'],
            ['code' => '5114', 'name' => ['en' => 'Social Insurance', 'ar' => 'التأمينات الاجتماعية'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5110'],
            ['code' => '5120', 'name' => ['en' => 'Rent Expense', 'ar' => 'مصروف الإيجار'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5100'],
            ['code' => '5130', 'name' => ['en' => 'Utilities', 'ar' => 'المرافق'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5100'],
            ['code' => '5131', 'name' => ['en' => 'Electricity', 'ar' => 'الكهرباء'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5130'],
            ['code' => '5132', 'name' => ['en' => 'Water', 'ar' => 'المياه'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5130'],
            ['code' => '5133', 'name' => ['en' => 'Internet & Phone', 'ar' => 'الإنترنت والهاتف'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5130'],
            ['code' => '5140', 'name' => ['en' => 'Marketing & Advertising', 'ar' => 'التسويق والإعلان'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5100'],
            ['code' => '5141', 'name' => ['en' => 'Online Advertising', 'ar' => 'الإعلان الإلكتروني'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5140'],
            ['code' => '5142', 'name' => ['en' => 'Print Advertising', 'ar' => 'الإعلان المطبوع'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5140'],
            ['code' => '5143', 'name' => ['en' => 'SMS & WhatsApp Costs', 'ar' => 'تكاليف الرسائل'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5140'],
            ['code' => '5150', 'name' => ['en' => 'Insurance Expense', 'ar' => 'مصروف التأمين'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5100'],
            ['code' => '5160', 'name' => ['en' => 'Professional Fees', 'ar' => 'أتعاب مهنية'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5100'],
            ['code' => '5161', 'name' => ['en' => 'Accounting Fees', 'ar' => 'أتعاب المحاسبة'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5160'],
            ['code' => '5162', 'name' => ['en' => 'Legal Fees', 'ar' => 'أتعاب قانونية'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5160'],
            ['code' => '5170', 'name' => ['en' => 'Bank Charges', 'ar' => 'مصاريف بنكية'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5100'],
            ['code' => '5180', 'name' => ['en' => 'Maintenance & Repairs', 'ar' => 'الصيانة والإصلاحات'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5100'],
            ['code' => '5181', 'name' => ['en' => 'Equipment Maintenance', 'ar' => 'صيانة المعدات'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5180'],
            ['code' => '5182', 'name' => ['en' => 'Facility Maintenance', 'ar' => 'صيانة المنشأة'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5180'],
            ['code' => '5190', 'name' => ['en' => 'Office Supplies', 'ar' => 'مستلزمات المكتب'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5100'],
            ['code' => '5191', 'name' => ['en' => 'Travel & Transportation', 'ar' => 'السفر والمواصلات'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5100'],
            ['code' => '5192', 'name' => ['en' => 'Training & Development', 'ar' => 'التدريب والتطوير'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5100'],
            ['code' => '5193', 'name' => ['en' => 'Subscriptions & Licenses', 'ar' => 'الاشتراكات والتراخيص'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5100'],
            ['code' => '5194', 'name' => ['en' => 'Discounts Given', 'ar' => 'خصومات ممنوحة'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5100'],
            ['code' => '5195', 'name' => ['en' => 'Rounding Differences', 'ar' => 'فروقات التقريب'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5100'],
            ['code' => '5196', 'name' => ['en' => 'Exchange Rate Differences', 'ar' => 'فروقات سعر الصرف'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5100'],
            ['code' => '5199', 'name' => ['en' => 'Miscellaneous Expenses', 'ar' => 'مصروفات متنوعة'], 'type' => ChartOfAccount::TYPE_EXPENSE, 'parent_code' => '5100'],

            // Depreciation
            ['code' => '5200', 'name' => ['en' => 'Depreciation Expense', 'ar' => 'مصروف الإهلاك'], 'type' => ChartOfAccount::TYPE_DEPRECIATION, 'parent_code' => null],
            ['code' => '5210', 'name' => ['en' => 'Depreciation - Equipment', 'ar' => 'إهلاك المعدات'], 'type' => ChartOfAccount::TYPE_DEPRECIATION, 'parent_code' => '5200'],
            ['code' => '5220', 'name' => ['en' => 'Depreciation - Furniture', 'ar' => 'إهلاك الأثاث'], 'type' => ChartOfAccount::TYPE_DEPRECIATION, 'parent_code' => '5200'],
            ['code' => '5230', 'name' => ['en' => 'Depreciation - Vehicles', 'ar' => 'إهلاك المركبات'], 'type' => ChartOfAccount::TYPE_DEPRECIATION, 'parent_code' => '5200'],
        ];

        $tenantId = $this->resolveTenantId();

        // First pass: create all accounts without parents to handle forward references
        $createdAccounts = [];
        foreach ($accounts as $account) {
            $existing = ChartOfAccount::where('code', $account['code'])
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$existing) {
                $createdAccounts[$account['code']] = ChartOfAccount::create([
                    'id' => Str::orderedUuid()->toString(),
                    'tenant_id' => $tenantId,
                    'code' => $account['code'],
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'parent_id' => null,
                    'is_active' => true,
                    'is_system' => true,
                ]);
            } else {
                // Update existing account with new type and name
                $existing->update([
                    'name' => $account['name'],
                    'type' => $account['type'],
                ]);
                $createdAccounts[$account['code']] = $existing;
            }
        }

        // Second pass: set parent relationships
        foreach ($accounts as $account) {
            if ($account['parent_code']) {
                $child = $createdAccounts[$account['code']] ?? ChartOfAccount::where('code', $account['code'])
                    ->where('tenant_id', $tenantId)
                    ->first();
                $parent = $createdAccounts[$account['parent_code']] ?? ChartOfAccount::where('code', $account['parent_code'])
                    ->where('tenant_id', $tenantId)
                    ->first();

                if ($child && $parent && $child->parent_id !== $parent->id) {
                    $child->update(['parent_id' => $parent->id]);
                }
            }
        }
    }
}
