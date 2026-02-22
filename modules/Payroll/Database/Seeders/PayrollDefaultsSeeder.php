<?php

namespace Modules\Payroll\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Payroll\Models\SalaryRule;
use Modules\Payroll\Models\SalaryRuleCategory;
use Modules\Payroll\Models\SalaryStructure;

class PayrollDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = $this->resolveTenantId();

        // Create categories first
        $categories = $this->seedCategories($tenantId);

        // Create rules
        $rules = $this->seedRules($tenantId, $categories);

        // Create default structure with rules
        $this->seedStructures($tenantId, $rules);
    }

    /**
     * Resolve the tenant ID from various sources.
     */
    protected function resolveTenantId(): ?string
    {
        // Try TenantManager first
        $tenantManager = app(\XLinic\Framework\Core\Tenancy\TenantManager::class);
        if ($tenantManager->current()) {
            return $tenantManager->current()->id;
        }

        // Try to resolve from database search_path (for CLI seeding)
        try {
            $result = \DB::connection('tenant')->select('SHOW search_path');
            $searchPath = $result[0]->search_path ?? 'public';

            // Extract tenant slug from search path (e.g., "tenant_clinic" -> "clinic")
            if (preg_match('/tenant[_-]([^,\s"]+)/', $searchPath, $matches)) {
                $slug = str_replace('_', '-', $matches[1]);

                // Find tenant by slug
                $tenant = \Modules\Core\Models\Tenant::where('slug', $slug)
                    ->orWhere('slug', $matches[1]) // Try original form too
                    ->first();

                if ($tenant) {
                    return $tenant->id;
                }
            }
        } catch (\Exception $e) {
            // Ignore errors
        }

        return null;
    }

    protected function seedCategories(?string $tenantId): array
    {
        $categories = [
            // Basic
            [
                'code' => 'BASIC',
                'name' => ['en' => 'Basic Salary', 'ar' => 'الراتب الأساسي'],
                'type' => SalaryRuleCategory::TYPE_EARNING,
                'description' => ['en' => 'Base salary component', 'ar' => 'مكون الراتب الأساسي'],
            ],
            // Allowances
            [
                'code' => 'ALW',
                'name' => ['en' => 'Allowances', 'ar' => 'البدلات'],
                'type' => SalaryRuleCategory::TYPE_ALLOWANCE,
                'description' => ['en' => 'Various allowances', 'ar' => 'بدلات متنوعة'],
            ],
            // Benefits
            [
                'code' => 'BEN',
                'name' => ['en' => 'Benefits', 'ar' => 'المزايا'],
                'type' => SalaryRuleCategory::TYPE_BENEFIT,
                'description' => ['en' => 'Employee benefits', 'ar' => 'مزايا الموظفين'],
            ],
            // Gross
            [
                'code' => 'GROSS',
                'name' => ['en' => 'Gross Salary', 'ar' => 'إجمالي الراتب'],
                'type' => SalaryRuleCategory::TYPE_GROSS,
                'description' => ['en' => 'Total gross earnings', 'ar' => 'إجمالي الإيرادات'],
            ],
            // Deductions
            [
                'code' => 'DED',
                'name' => ['en' => 'Deductions', 'ar' => 'الخصومات'],
                'type' => SalaryRuleCategory::TYPE_DEDUCTION,
                'description' => ['en' => 'Salary deductions', 'ar' => 'خصومات الراتب'],
            ],
            // Net
            [
                'code' => 'NET',
                'name' => ['en' => 'Net Salary', 'ar' => 'صافي الراتب'],
                'type' => SalaryRuleCategory::TYPE_NET,
                'description' => ['en' => 'Net take-home pay', 'ar' => 'صافي الراتب المستحق'],
            ],
        ];

        $result = [];
        foreach ($categories as $cat) {
            $record = SalaryRuleCategory::firstOrCreate(
                ['code' => $cat['code'], 'tenant_id' => $tenantId],
                [
                    'id' => Str::orderedUuid()->toString(),
                    'tenant_id' => $tenantId,
                    'name' => $cat['name']['en'], // Store English for now
                    'description' => $cat['description']['en'],
                    'type' => $cat['type'],
                    'is_active' => true,
                ]
            );
            $result[$cat['code']] = $record;
        }

        return $result;
    }

    protected function seedRules(?string $tenantId, array $categories): array
    {
        $rules = [
            // ===== BASIC SALARY =====
            [
                'code' => 'BASIC',
                'name' => ['en' => 'Basic Salary', 'ar' => 'الراتب الأساسي'],
                'category' => 'BASIC',
                'sequence' => 1,
                'amount_type' => SalaryRule::AMOUNT_TYPE_FIXED,
                'field_mapping' => 'base_salary',
                'condition_type' => 'always',
            ],

            // ===== ALLOWANCES =====
            [
                'code' => 'HRA',
                'name' => ['en' => 'Housing Allowance', 'ar' => 'بدل السكن'],
                'category' => 'ALW',
                'sequence' => 10,
                'amount_type' => SalaryRule::AMOUNT_TYPE_PERCENTAGE,
                'amount_percentage' => 25.00, // 25% of basic
                'percentage_base_code' => 'BASIC',
                'condition_type' => 'always',
            ],
            [
                'code' => 'TA',
                'name' => ['en' => 'Transport Allowance', 'ar' => 'بدل المواصلات'],
                'category' => 'ALW',
                'sequence' => 11,
                'amount_type' => SalaryRule::AMOUNT_TYPE_FIXED,
                'amount_fixed_minor' => 50000, // 500 EGP
                'condition_type' => 'always',
            ],
            [
                'code' => 'MEAL',
                'name' => ['en' => 'Meal Allowance', 'ar' => 'بدل الوجبات'],
                'category' => 'ALW',
                'sequence' => 12,
                'amount_type' => SalaryRule::AMOUNT_TYPE_FIXED,
                'amount_fixed_minor' => 30000, // 300 EGP
                'condition_type' => 'always',
            ],
            [
                'code' => 'PHONE',
                'name' => ['en' => 'Phone Allowance', 'ar' => 'بدل الهاتف'],
                'category' => 'ALW',
                'sequence' => 13,
                'amount_type' => SalaryRule::AMOUNT_TYPE_FIXED,
                'amount_fixed_minor' => 20000, // 200 EGP
                'condition_type' => 'always',
            ],

            // ===== BENEFITS =====
            [
                'code' => 'COMM',
                'name' => ['en' => 'Commission', 'ar' => 'العمولة'],
                'category' => 'BEN',
                'sequence' => 20,
                'amount_type' => SalaryRule::AMOUNT_TYPE_FIXED,
                'field_mapping' => 'commission_amount',
                'condition_type' => 'formula',
                'condition_formula' => 'commission_amount > 0',
            ],
            [
                'code' => 'BONUS',
                'name' => ['en' => 'Bonus', 'ar' => 'المكافأة'],
                'category' => 'BEN',
                'sequence' => 21,
                'amount_type' => SalaryRule::AMOUNT_TYPE_FIXED,
                'field_mapping' => 'bonus_amount',
                'condition_type' => 'formula',
                'condition_formula' => 'bonus_amount > 0',
            ],
            [
                'code' => 'OT',
                'name' => ['en' => 'Overtime Pay', 'ar' => 'أجر إضافي'],
                'category' => 'BEN',
                'sequence' => 22,
                'amount_type' => SalaryRule::AMOUNT_TYPE_FORMULA,
                'amount_formula' => '(base_salary / 30 / 8) * overtime_hours * 1.5',
                'condition_type' => 'formula',
                'condition_formula' => 'overtime_hours > 0',
            ],

            // ===== GROSS SALARY =====
            [
                'code' => 'GROSS',
                'name' => ['en' => 'Gross Salary', 'ar' => 'إجمالي الراتب'],
                'category' => 'GROSS',
                'sequence' => 100,
                'amount_type' => SalaryRule::AMOUNT_TYPE_FORMULA,
                'amount_formula' => 'BASIC + HRA + TA + MEAL + PHONE + COMM + BONUS + OT',
                'condition_type' => 'always',
            ],

            // ===== DEDUCTIONS =====
            [
                'code' => 'SI_EMP',
                'name' => ['en' => 'Social Insurance (Employee)', 'ar' => 'التأمينات الاجتماعية (الموظف)'],
                'category' => 'DED',
                'sequence' => 110,
                'amount_type' => SalaryRule::AMOUNT_TYPE_PERCENTAGE,
                'amount_percentage' => 11.00, // 11% employee share
                'percentage_base_code' => 'BASIC',
                'condition_type' => 'always',
            ],
            [
                'code' => 'TAX',
                'name' => ['en' => 'Income Tax', 'ar' => 'ضريبة الدخل'],
                'category' => 'DED',
                'sequence' => 111,
                'amount_type' => SalaryRule::AMOUNT_TYPE_FORMULA,
                // Egyptian tax brackets (simplified)
                'amount_formula' => 'taxable_income <= 15000 ? 0 : (taxable_income <= 30000 ? (taxable_income - 15000) * 0.025 : (taxable_income <= 45000 ? 375 + (taxable_income - 30000) * 0.10 : (taxable_income <= 60000 ? 1875 + (taxable_income - 45000) * 0.15 : (taxable_income <= 200000 ? 4125 + (taxable_income - 60000) * 0.20 : (taxable_income <= 400000 ? 32125 + (taxable_income - 200000) * 0.225 : 77125 + (taxable_income - 400000) * 0.25)))))',
                'condition_type' => 'always',
            ],
            [
                'code' => 'ABSENCE',
                'name' => ['en' => 'Absence Deduction', 'ar' => 'خصم الغياب'],
                'category' => 'DED',
                'sequence' => 112,
                'amount_type' => SalaryRule::AMOUNT_TYPE_FORMULA,
                'amount_formula' => '(base_salary / working_days) * absence_days',
                'condition_type' => 'formula',
                'condition_formula' => 'absence_days > 0',
            ],
            [
                'code' => 'LATE',
                'name' => ['en' => 'Late Deduction', 'ar' => 'خصم التأخير'],
                'category' => 'DED',
                'sequence' => 113,
                'amount_type' => SalaryRule::AMOUNT_TYPE_FORMULA,
                'amount_formula' => '(base_salary / working_days / 8 / 60) * late_minutes',
                'condition_type' => 'formula',
                'condition_formula' => 'late_minutes > 0',
            ],
            [
                'code' => 'LOAN',
                'name' => ['en' => 'Loan Repayment', 'ar' => 'قسط السلف'],
                'category' => 'DED',
                'sequence' => 114,
                'amount_type' => SalaryRule::AMOUNT_TYPE_FIXED,
                'field_mapping' => 'loan_deduction',
                'condition_type' => 'formula',
                'condition_formula' => 'loan_deduction > 0',
            ],
            [
                'code' => 'OTHER_DED',
                'name' => ['en' => 'Other Deductions', 'ar' => 'خصومات أخرى'],
                'category' => 'DED',
                'sequence' => 119,
                'amount_type' => SalaryRule::AMOUNT_TYPE_FIXED,
                'field_mapping' => 'other_deductions',
                'condition_type' => 'formula',
                'condition_formula' => 'other_deductions > 0',
            ],

            // ===== NET SALARY =====
            [
                'code' => 'NET',
                'name' => ['en' => 'Net Salary', 'ar' => 'صافي الراتب'],
                'category' => 'NET',
                'sequence' => 200,
                'amount_type' => SalaryRule::AMOUNT_TYPE_FORMULA,
                'amount_formula' => 'GROSS - SI_EMP - TAX - ABSENCE - LATE - LOAN - OTHER_DED',
                'condition_type' => 'always',
            ],
        ];

        $result = [];
        $ruleIds = []; // Store rule IDs by code for percentage_base references

        foreach ($rules as $rule) {
            $categoryId = $categories[$rule['category']]->id ?? null;

            // Get percentage base ID if specified
            $percentageBaseId = null;
            if (!empty($rule['percentage_base_code']) && isset($ruleIds[$rule['percentage_base_code']])) {
                $percentageBaseId = $ruleIds[$rule['percentage_base_code']];
            }

            $record = SalaryRule::firstOrCreate(
                ['code' => $rule['code'], 'tenant_id' => $tenantId],
                [
                    'id' => Str::orderedUuid()->toString(),
                    'tenant_id' => $tenantId,
                    'name' => $rule['name']['en'],
                    'category_id' => $categoryId,
                    'amount_type' => $rule['amount_type'],
                    'amount_fixed_minor' => $rule['amount_fixed_minor'] ?? 0,
                    'amount_percentage' => $rule['amount_percentage'] ?? null,
                    'amount_formula' => $rule['amount_formula'] ?? null,
                    'condition_type' => $rule['condition_type'] ?? 'always',
                    'condition_formula' => $rule['condition_formula'] ?? null,
                    'percentage_base_id' => $percentageBaseId,
                    'field_mapping' => $rule['field_mapping'] ?? null,
                    'sequence' => $rule['sequence'],
                    'is_active' => true,
                ]
            );

            $result[$rule['code']] = $record;
            $ruleIds[$rule['code']] = $record->id;
        }

        // Update percentage_base_id for rules that reference other rules
        foreach ($rules as $rule) {
            if (!empty($rule['percentage_base_code']) && isset($ruleIds[$rule['percentage_base_code']])) {
                $ruleRecord = $result[$rule['code']];
                if (!$ruleRecord->percentage_base_id) {
                    $ruleRecord->update([
                        'percentage_base_id' => $ruleIds[$rule['percentage_base_code']],
                    ]);
                }
            }
        }

        return $result;
    }

    protected function seedStructures(?string $tenantId, array $rules): void
    {
        $structures = [
            [
                'code' => 'MONTHLY_BASIC',
                'name' => ['en' => 'Monthly Salary Structure', 'ar' => 'هيكل الراتب الشهري'],
                'description' => ['en' => 'Standard monthly salary structure with all standard rules', 'ar' => 'هيكل الراتب الشهري القياسي مع جميع القواعد القياسية'],
                'pay_frequency' => 'monthly',
                'rules' => ['BASIC', 'HRA', 'TA', 'MEAL', 'PHONE', 'COMM', 'BONUS', 'OT', 'GROSS', 'SI_EMP', 'TAX', 'ABSENCE', 'LATE', 'LOAN', 'OTHER_DED', 'NET'],
            ],
            [
                'code' => 'MONTHLY_SIMPLE',
                'name' => ['en' => 'Simple Monthly Structure', 'ar' => 'هيكل الراتب الشهري البسيط'],
                'description' => ['en' => 'Simple structure with basic salary and standard deductions', 'ar' => 'هيكل بسيط مع الراتب الأساسي والخصومات القياسية'],
                'pay_frequency' => 'monthly',
                'rules' => ['BASIC', 'GROSS', 'SI_EMP', 'TAX', 'NET'],
            ],
            [
                'code' => 'COMMISSION_BASED',
                'name' => ['en' => 'Commission-Based Structure', 'ar' => 'هيكل قائم على العمولة'],
                'description' => ['en' => 'Structure focused on commission earnings', 'ar' => 'هيكل يركز على إيرادات العمولة'],
                'pay_frequency' => 'monthly',
                'rules' => ['BASIC', 'COMM', 'BONUS', 'GROSS', 'SI_EMP', 'TAX', 'NET'],
            ],
        ];

        foreach ($structures as $structure) {
            $record = SalaryStructure::firstOrCreate(
                ['code' => $structure['code'], 'tenant_id' => $tenantId],
                [
                    'id' => Str::orderedUuid()->toString(),
                    'tenant_id' => $tenantId,
                    'name' => $structure['name']['en'],
                    'description' => $structure['description']['en'],
                    'pay_frequency' => $structure['pay_frequency'],
                    'currency' => 'EGP',
                    'is_active' => true,
                    'created_by' => auth()->id(),
                ]
            );

            // Attach rules with sequence
            $sequence = 0;
            foreach ($structure['rules'] as $ruleCode) {
                if (isset($rules[$ruleCode])) {
                    $record->rules()->syncWithoutDetaching([
                        $rules[$ruleCode]->id => ['sequence' => $sequence++],
                    ]);
                }
            }
        }
    }
}
