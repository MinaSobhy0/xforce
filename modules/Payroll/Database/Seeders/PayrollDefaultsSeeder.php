<?php

namespace Modules\Payroll\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Payroll\Models\SalaryRule;
use Modules\Payroll\Models\SalaryRuleCategory;

class PayrollDefaultsSeeder extends Seeder
{
    protected $connection;
    protected ?string $tenantId = null;

    public function run(): void
    {
        // Resolve connection and tenant
        $this->connection = $this->resolveConnection();
        $this->tenantId = $this->resolveTenantId();

        // Create categories first
        $categories = $this->seedCategories();

        // Create rules
        $rules = $this->seedRules($categories);

        // Create default structure with rules
        $this->seedStructures($rules);
    }

    /**
     * Resolve the database connection to use.
     */
    protected function resolveConnection()
    {
        // Check if we're in a tenant context
        try {
            $result = DB::connection('tenant')->select('SHOW search_path');
            $searchPath = $result[0]->search_path ?? 'public';

            if (str_starts_with($searchPath, 'tenant_') || str_starts_with($searchPath, '"tenant_')) {
                return DB::connection('tenant');
            }
        } catch (\Exception $e) {
            // Ignore
        }

        // Default to tenant connection
        return DB::connection('tenant');
    }

    /**
     * Resolve currency from tenant settings or branch.
     */
    protected function resolveCurrency(): string
    {
        try {
            $currency = $this->connection->table('branches')
                ->where('is_main', true)
                ->value('currency_code');

            if ($currency) {
                return $currency;
            }

            $currency = $this->connection->table('branches')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->value('currency_code');

            if ($currency) {
                return $currency;
            }
        } catch (\Exception $e) {
            // Ignore
        }

        return config('app.currency', 'USD');
    }

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

        // Try to resolve from database search_path (for CLI seeding)
        try {
            $result = DB::connection('tenant')->select('SHOW search_path');
            $searchPath = $result[0]->search_path ?? 'public';

            // Extract tenant slug from search path (e.g., "tenant_clinic" -> "clinic")
            if (preg_match('/tenant[_-]([^,\s"]+)/', $searchPath, $matches)) {
                $slug = str_replace('_', '-', $matches[1]);

                // Find tenant by slug using central connection
                $tenant = DB::connection('pgsql')
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

        return null;
    }

    protected function seedCategories(): array
    {
        $categories = [
            ['code' => 'BASIC', 'name' => 'Basic Salary', 'description' => 'Base salary component', 'type' => SalaryRuleCategory::TYPE_EARNING],
            ['code' => 'ALW', 'name' => 'Allowances', 'description' => 'Various allowances', 'type' => SalaryRuleCategory::TYPE_ALLOWANCE],
            ['code' => 'BEN', 'name' => 'Benefits', 'description' => 'Employee benefits', 'type' => SalaryRuleCategory::TYPE_BENEFIT],
            ['code' => 'GROSS', 'name' => 'Gross Salary', 'description' => 'Total gross earnings', 'type' => SalaryRuleCategory::TYPE_GROSS],
            ['code' => 'DED', 'name' => 'Deductions', 'description' => 'Salary deductions', 'type' => SalaryRuleCategory::TYPE_DEDUCTION],
            ['code' => 'NET', 'name' => 'Net Salary', 'description' => 'Net take-home pay', 'type' => SalaryRuleCategory::TYPE_NET],
        ];

        $result = [];
        foreach ($categories as $cat) {
            // Check if exists
            $existing = $this->connection->table('salary_rule_categories')
                ->where('code', $cat['code'])
                ->where('tenant_id', $this->tenantId)
                ->first();

            if ($existing) {
                $result[$cat['code']] = $existing->id;
                continue;
            }

            $id = Str::orderedUuid()->toString();
            $this->connection->table('salary_rule_categories')->insert([
                'id' => $id,
                'tenant_id' => $this->tenantId,
                'code' => $cat['code'],
                'name' => $cat['name'],
                'description' => $cat['description'],
                'type' => $cat['type'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $result[$cat['code']] = $id;
        }

        return $result;
    }

    protected function seedRules(array $categories): array
    {
        $rules = [
            // BASIC SALARY
            ['code' => 'BASIC', 'name' => 'Basic Salary', 'category' => 'BASIC', 'sequence' => 1, 'amount_type' => SalaryRule::AMOUNT_TYPE_FIXED, 'field_mapping' => 'base_salary', 'condition_type' => 'always'],

            // ALLOWANCES
            ['code' => 'HRA', 'name' => 'Housing Allowance', 'category' => 'ALW', 'sequence' => 10, 'amount_type' => SalaryRule::AMOUNT_TYPE_FIXED, 'field_mapping' => 'housing_allowance', 'condition_type' => 'formula', 'condition_formula' => 'housing_allowance > 0'],
            ['code' => 'TA', 'name' => 'Transport Allowance', 'category' => 'ALW', 'sequence' => 11, 'amount_type' => SalaryRule::AMOUNT_TYPE_FIXED, 'field_mapping' => 'transport_allowance', 'condition_type' => 'formula', 'condition_formula' => 'transport_allowance > 0'],
            ['code' => 'MEAL', 'name' => 'Meal Allowance', 'category' => 'ALW', 'sequence' => 12, 'amount_type' => SalaryRule::AMOUNT_TYPE_FIXED, 'field_mapping' => 'meal_allowance', 'condition_type' => 'formula', 'condition_formula' => 'meal_allowance > 0'],
            ['code' => 'PHONE', 'name' => 'Phone Allowance', 'category' => 'ALW', 'sequence' => 13, 'amount_type' => SalaryRule::AMOUNT_TYPE_FIXED, 'field_mapping' => 'phone_allowance', 'condition_type' => 'formula', 'condition_formula' => 'phone_allowance > 0'],

            // BENEFITS
            ['code' => 'COMM', 'name' => 'Commission', 'category' => 'BEN', 'sequence' => 20, 'amount_type' => SalaryRule::AMOUNT_TYPE_FIXED, 'field_mapping' => 'commission_amount', 'condition_type' => 'formula', 'condition_formula' => 'commission_amount > 0'],
            ['code' => 'BONUS', 'name' => 'Bonus', 'category' => 'BEN', 'sequence' => 21, 'amount_type' => SalaryRule::AMOUNT_TYPE_FIXED, 'field_mapping' => 'bonus_amount', 'condition_type' => 'formula', 'condition_formula' => 'bonus_amount > 0'],
            ['code' => 'OT', 'name' => 'Overtime Pay', 'category' => 'BEN', 'sequence' => 22, 'amount_type' => SalaryRule::AMOUNT_TYPE_FORMULA, 'amount_formula' => '(base_salary / 30 / 8) * overtime_hours * 1.5', 'condition_type' => 'formula', 'condition_formula' => 'overtime_hours > 0'],

            // GROSS
            ['code' => 'GROSS', 'name' => 'Gross Salary', 'category' => 'GROSS', 'sequence' => 100, 'amount_type' => SalaryRule::AMOUNT_TYPE_FORMULA, 'amount_formula' => 'BASIC + HRA + TA + MEAL + PHONE + COMM + BONUS + OT', 'condition_type' => 'always'],

            // DEDUCTIONS
            ['code' => 'SI_EMP', 'name' => 'Social Insurance (Employee)', 'category' => 'DED', 'sequence' => 110, 'amount_type' => SalaryRule::AMOUNT_TYPE_PERCENTAGE, 'amount_percentage' => 11.00, 'percentage_base_code' => 'BASIC', 'condition_type' => 'always'],
            ['code' => 'TAX', 'name' => 'Income Tax', 'category' => 'DED', 'sequence' => 111, 'amount_type' => SalaryRule::AMOUNT_TYPE_FORMULA, 'amount_formula' => 'taxable_income <= 15000 ? 0 : (taxable_income <= 30000 ? (taxable_income - 15000) * 0.025 : (taxable_income <= 45000 ? 375 + (taxable_income - 30000) * 0.10 : (taxable_income <= 60000 ? 1875 + (taxable_income - 45000) * 0.15 : (taxable_income <= 200000 ? 4125 + (taxable_income - 60000) * 0.20 : (taxable_income <= 400000 ? 32125 + (taxable_income - 200000) * 0.225 : 77125 + (taxable_income - 400000) * 0.25)))))', 'condition_type' => 'always'],
            ['code' => 'ABSENCE', 'name' => 'Absence Deduction', 'category' => 'DED', 'sequence' => 112, 'amount_type' => SalaryRule::AMOUNT_TYPE_FORMULA, 'amount_formula' => '(base_salary / working_days) * absence_days', 'condition_type' => 'formula', 'condition_formula' => 'absence_days > 0'],
            ['code' => 'LATE', 'name' => 'Late Deduction', 'category' => 'DED', 'sequence' => 113, 'amount_type' => SalaryRule::AMOUNT_TYPE_FORMULA, 'amount_formula' => '(base_salary / working_days / 8 / 60) * late_minutes', 'condition_type' => 'formula', 'condition_formula' => 'late_minutes > 0'],
            ['code' => 'LOAN', 'name' => 'Loan Repayment', 'category' => 'DED', 'sequence' => 114, 'amount_type' => SalaryRule::AMOUNT_TYPE_FIXED, 'field_mapping' => 'loan_deduction', 'condition_type' => 'formula', 'condition_formula' => 'loan_deduction > 0'],
            ['code' => 'OTHER_DED', 'name' => 'Other Deductions', 'category' => 'DED', 'sequence' => 119, 'amount_type' => SalaryRule::AMOUNT_TYPE_FIXED, 'field_mapping' => 'other_deductions', 'condition_type' => 'formula', 'condition_formula' => 'other_deductions > 0'],

            // NET
            ['code' => 'NET', 'name' => 'Net Salary', 'category' => 'NET', 'sequence' => 200, 'amount_type' => SalaryRule::AMOUNT_TYPE_FORMULA, 'amount_formula' => 'GROSS - SI_EMP - TAX - ABSENCE - LATE - LOAN - OTHER_DED', 'condition_type' => 'always'],
        ];

        $result = [];
        $ruleIds = [];

        foreach ($rules as $rule) {
            // Check if exists
            $existing = $this->connection->table('salary_rules')
                ->where('code', $rule['code'])
                ->where('tenant_id', $this->tenantId)
                ->first();

            if ($existing) {
                $result[$rule['code']] = $existing->id;
                $ruleIds[$rule['code']] = $existing->id;
                continue;
            }

            $id = Str::orderedUuid()->toString();

            // Get percentage base ID if specified
            $percentageBaseId = null;
            if (!empty($rule['percentage_base_code']) && isset($ruleIds[$rule['percentage_base_code']])) {
                $percentageBaseId = $ruleIds[$rule['percentage_base_code']];
            }

            $this->connection->table('salary_rules')->insert([
                'id' => $id,
                'tenant_id' => $this->tenantId,
                'code' => $rule['code'],
                'name' => $rule['name'],
                'category_id' => $categories[$rule['category']] ?? null,
                'sequence' => $rule['sequence'],
                'amount_type' => $rule['amount_type'],
                'amount_fixed_minor' => 0,
                'amount_percentage' => $rule['amount_percentage'] ?? null,
                'amount_formula' => $rule['amount_formula'] ?? null,
                'condition_type' => $rule['condition_type'] ?? 'always',
                'condition_formula' => $rule['condition_formula'] ?? null,
                'percentage_base_id' => $percentageBaseId,
                'field_mapping' => $rule['field_mapping'] ?? null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $result[$rule['code']] = $id;
            $ruleIds[$rule['code']] = $id;
        }

        // Update percentage_base_id for rules that reference other rules
        foreach ($rules as $rule) {
            if (!empty($rule['percentage_base_code']) && isset($ruleIds[$rule['percentage_base_code']])) {
                $this->connection->table('salary_rules')
                    ->where('id', $ruleIds[$rule['code']])
                    ->whereNull('percentage_base_id')
                    ->update(['percentage_base_id' => $ruleIds[$rule['percentage_base_code']]]);
            }
        }

        return $result;
    }

    protected function seedStructures(array $rules): void
    {
        $currency = $this->resolveCurrency();

        $structures = [
            ['code' => 'MONTHLY_BASIC', 'name' => 'Monthly Salary Structure', 'description' => 'Standard monthly salary structure with all standard rules', 'pay_frequency' => 'monthly', 'rules' => ['BASIC', 'HRA', 'TA', 'MEAL', 'PHONE', 'COMM', 'BONUS', 'OT', 'GROSS', 'SI_EMP', 'TAX', 'ABSENCE', 'LATE', 'LOAN', 'OTHER_DED', 'NET']],
            ['code' => 'MONTHLY_SIMPLE', 'name' => 'Simple Monthly Structure', 'description' => 'Simple structure with basic salary and standard deductions', 'pay_frequency' => 'monthly', 'rules' => ['BASIC', 'GROSS', 'SI_EMP', 'TAX', 'NET']],
            ['code' => 'COMMISSION_BASED', 'name' => 'Commission-Based Structure', 'description' => 'Structure focused on commission earnings', 'pay_frequency' => 'monthly', 'rules' => ['BASIC', 'COMM', 'BONUS', 'GROSS', 'SI_EMP', 'TAX', 'NET']],
        ];

        foreach ($structures as $structure) {
            // Check if exists
            $existing = $this->connection->table('salary_structures')
                ->where('code', $structure['code'])
                ->where('tenant_id', $this->tenantId)
                ->first();

            if ($existing) {
                $structureId = $existing->id;
            } else {
                $structureId = Str::orderedUuid()->toString();
                $this->connection->table('salary_structures')->insert([
                    'id' => $structureId,
                    'tenant_id' => $this->tenantId,
                    'code' => $structure['code'],
                    'name' => $structure['name'],
                    'description' => $structure['description'],
                    'pay_frequency' => $structure['pay_frequency'],
                    'currency' => $currency,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Attach rules (pivot table)
            $sequence = 0;
            foreach ($structure['rules'] as $ruleCode) {
                if (!isset($rules[$ruleCode])) {
                    continue;
                }

                $ruleId = $rules[$ruleCode];

                // Check if already attached
                $exists = $this->connection->table('salary_structure_rules')
                    ->where('salary_structure_id', $structureId)
                    ->where('salary_rule_id', $ruleId)
                    ->exists();

                if (!$exists) {
                    $this->connection->table('salary_structure_rules')->insert([
                        'salary_structure_id' => $structureId,
                        'salary_rule_id' => $ruleId,
                        'sequence' => $sequence,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $sequence++;
            }
        }
    }
}
