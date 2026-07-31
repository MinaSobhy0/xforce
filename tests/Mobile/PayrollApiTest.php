<?php

use Modules\Payroll\Models\PayrollLine;
use Modules\Payroll\Models\SalaryRule;

/**
 * Mobile payroll API: the employee sees only the payslip content the tenant
 * allows, and only the salary rules flagged appears_on_payslip in Odoo —
 * never the full rule set.
 */

/**
 * Seed a full Odoo payroll world for one employee and import it:
 * categories, rules (one flagged appears_on_payslip=false), and one payslip
 * for the current month. Returns [User, StaffProfile, PayrollLine].
 */
function seedMobilePayslip($test, array $ruleVisibility = []): array
{
    [$user, $staff] = $test->createStaffUser();
    $staff->update(['odoo_id' => 91000 + $staff->id]);
    $catMapping = $test->entityMapping('salaryRuleCategories');
    $cats = [
        'BASIC' => $test->odoo->seed('hr.salary.rule.category', ['name' => 'Basic', 'code' => 'BASIC']),
        'DED' => $test->odoo->seed('hr.salary.rule.category', ['name' => 'Deduction', 'code' => 'DED']),
        'COMP' => $test->odoo->seed('hr.salary.rule.category', ['name' => 'Company', 'code' => 'COMP']),
        'NET' => $test->odoo->seed('hr.salary.rule.category', ['name' => 'Net', 'code' => 'NET']),
    ];
    runOdooSync($catMapping);

    $ruleMapping = $test->entityMapping('salaryRules');
    $rules = [
        'BASIC' => $test->odoo->seed('hr.salary.rule', [
            'name' => 'Basic Salary', 'code' => 'BASIC', 'sequence' => 1,
            'category_id' => [$cats['BASIC'], 'Basic'], 'amount_select' => 'fix', 'active' => true,
            'appears_on_payslip' => $ruleVisibility['BASIC'] ?? true,
        ]),
        'TAX' => $test->odoo->seed('hr.salary.rule', [
            'name' => 'Income Tax', 'code' => 'TAX', 'sequence' => 50,
            'category_id' => [$cats['DED'], 'Deduction'], 'amount_select' => 'code', 'active' => true,
            'appears_on_payslip' => $ruleVisibility['TAX'] ?? true,
        ]),
        // Employer-side contribution: computed on the slip but NOT for
        // employee eyes — appears_on_payslip = false in Odoo.
        'EMP_SI' => $test->odoo->seed('hr.salary.rule', [
            'name' => 'Employer Social Insurance', 'code' => 'EMP_SI', 'sequence' => 60,
            'category_id' => [$cats['COMP'], 'Company'], 'amount_select' => 'code', 'active' => true,
            'appears_on_payslip' => $ruleVisibility['EMP_SI'] ?? false,
        ]),
        'NET' => $test->odoo->seed('hr.salary.rule', [
            'name' => 'Net Salary', 'code' => 'NET', 'sequence' => 100,
            'category_id' => [$cats['NET'], 'Net'], 'amount_select' => 'code', 'active' => true,
            'appears_on_payslip' => $ruleVisibility['NET'] ?? true,
        ]),
    ];
    runOdooSync($ruleMapping);

    $lineIds = [
        $test->odoo->seed('hr.payslip.line', [
            'code' => 'BASIC', 'name' => 'Basic Salary',
            'category_id' => [$cats['BASIC'], 'Basic'],
            'salary_rule_id' => [$rules['BASIC'], 'Basic Salary'],
            'total' => 8000.0,
        ]),
        $test->odoo->seed('hr.payslip.line', [
            'code' => 'TAX', 'name' => 'Income Tax',
            'category_id' => [$cats['DED'], 'Deduction'],
            'salary_rule_id' => [$rules['TAX'], 'Income Tax'],
            'total' => 900.0,
        ]),
        $test->odoo->seed('hr.payslip.line', [
            'code' => 'EMP_SI', 'name' => 'Employer Social Insurance',
            'category_id' => [$cats['COMP'], 'Company'],
            'salary_rule_id' => [$rules['EMP_SI'], 'Employer Social Insurance'],
            'total' => 1500.0,
        ]),
        $test->odoo->seed('hr.payslip.line', [
            'code' => 'NET', 'name' => 'Net Salary',
            'category_id' => [$cats['NET'], 'Net'],
            'salary_rule_id' => [$rules['NET'], 'Net Salary'],
            'total' => 7100.0,
        ]),
    ];

    $odooSlipId = $test->odoo->seed('hr.payslip', [
        'employee_id' => [$staff->odoo_id, 'Employee'],
        'state' => 'done',
        'date_from' => now()->startOfMonth()->toDateString(),
        'date_to' => now()->endOfMonth()->toDateString(),
        'number' => 'SLIP/TEST/001',
        'line_ids' => $lineIds,
    ]);

    runOdooSync($test->entityMapping('payslips'));

    return [$user, $staff, PayrollLine::where('odoo_id', $odooSlipId)->firstOrFail()];
}

function setPayslipDisplay($test, array $flags): void
{
    $test->tenant->setSetting('payslip_display', $flags);
    $test->tenant->save();
    // Re-bind so payslipDisplayConfig() reads the fresh settings.
    app()->instance('currentTenant', $test->tenant->fresh());
}

// ---------------------------------------------------------------------------
// Rule visibility
// ---------------------------------------------------------------------------

test('payslip detail exposes only rules flagged appears_on_payslip in Odoo', function () {
    [$user, $staff, $slip] = seedMobilePayslip($this);

    $data = $this->api('GET', "payroll/{$slip->id}", [], $user)->assertOk()->json('data');

    $codes = collect($data['rule_breakdown'])->pluck('rule_code')->all();

    expect($codes)->toContain('BASIC', 'TAX', 'NET')
        ->and($codes)->not->toContain('EMP_SI');

    // The internal visibility flag itself is not leaked to the client.
    expect(collect($data['rule_breakdown'])->first())->not->toHaveKey('visible');
});

test('the local appears_on_payslip column also syncs from Odoo', function () {
    seedMobilePayslip($this);

    expect(SalaryRule::where('code', 'EMP_SI')->firstOrFail()->appears_on_payslip)->toBeFalse()
        ->and(SalaryRule::where('code', 'BASIC')->firstOrFail()->appears_on_payslip)->toBeTrue();
});

test('legacy breakdown entries without a visible flag fall back to the local rule column', function () {
    [$user, $staff, $slip] = seedMobilePayslip($this);

    // Simulate a pre-flag import: strip 'visible' from every entry.
    $slip->update([
        'rule_amounts_json' => collect($slip->rule_amounts_json)
            ->map(function ($entry) {
                unset($entry['visible']);

                return $entry;
            })->all(),
    ]);

    $data = $this->api('GET', "payroll/{$slip->id}", [], $user)->assertOk()->json('data');
    $codes = collect($data['rule_breakdown'])->pluck('rule_code')->all();

    expect($codes)->not->toContain('EMP_SI')
        ->and($codes)->toContain('BASIC');
});

test('show_rule_breakdown=false removes the breakdown entirely', function () {
    [$user, $staff, $slip] = seedMobilePayslip($this);
    setPayslipDisplay($this, ['show_rule_breakdown' => false]);

    $data = $this->api('GET', "payroll/{$slip->id}", [], $user)->assertOk()->json('data');

    expect($data)->not->toHaveKey('rule_breakdown');
});

// ---------------------------------------------------------------------------
// Tenant display flags
// ---------------------------------------------------------------------------

test('gross salary is hidden by default and shown only when the tenant opts in', function () {
    [$user, $staff, $slip] = seedMobilePayslip($this);

    $data = $this->api('GET', "payroll/{$slip->id}", [], $user)->assertOk()->json('data');
    expect($data)->not->toHaveKey('gross_salary');

    setPayslipDisplay($this, ['show_gross_salary' => true]);

    $data = $this->api('GET', "payroll/{$slip->id}", [], $user)->assertOk()->json('data');
    expect($data)->toHaveKey('gross_salary');
});

test('earnings and deductions breakdowns honour their tenant flags', function () {
    [$user, $staff, $slip] = seedMobilePayslip($this);
    setPayslipDisplay($this, [
        'show_allowances_breakdown' => false,
        'show_deductions_breakdown' => false,
    ]);

    $data = $this->api('GET', "payroll/{$slip->id}", [], $user)->assertOk()->json('data');

    expect($data)->not->toHaveKey('earnings')
        ->and($data)->not->toHaveKey('deductions');
});

// ---------------------------------------------------------------------------
// Scoping
// ---------------------------------------------------------------------------

test('an employee cannot open another employee\'s payslip', function () {
    [$user, $staff, $slip] = seedMobilePayslip($this);
    [$intruder] = $this->createStaffUser();

    $this->api('GET', "payroll/{$slip->id}", [], $intruder)->assertStatus(404);
});

test('history lists only approved or paid slips for the employee', function () {
    [$user, $staff, $slip] = seedMobilePayslip($this);

    $items = $this->api('GET', 'payroll/history', [], $user)->assertOk()->json('data');

    expect(collect($items)->pluck('id')->all())->toBe([$slip->id]);
});
