<?php

use Modules\OdooIntegration\Enums\SyncStatus;
use Modules\Payroll\Models\PayrollLine;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\SalaryRule;
use Modules\Payroll\Models\SalaryRuleCategory;
use Modules\Payroll\Models\SalaryStructure;
use Modules\Staff\Models\StaffProfile;
use Tests\Odoo\Support\OdooScenario;

/**
 * Import sync: salary rule categories / structures / rules and hr.payslip →
 * payroll_runs + payroll_lines.
 */
function seedPayrollCatalog($test, $connection): array
{
    // Categories
    $catMapping = OdooScenario::salaryRuleCategories($connection);
    $odooCats = [
        'BASIC' => $test->odoo->seed('hr.salary.rule.category', ['name' => 'Basic', 'code' => 'BASIC']),
        'ALW' => $test->odoo->seed('hr.salary.rule.category', ['name' => 'Allowance', 'code' => 'ALW']),
        'DED' => $test->odoo->seed('hr.salary.rule.category', ['name' => 'Deduction', 'code' => 'DED']),
        'NET' => $test->odoo->seed('hr.salary.rule.category', ['name' => 'Net', 'code' => 'NET']),
    ];
    runOdooSync($catMapping);

    // Rules
    $ruleMapping = OdooScenario::salaryRules($connection);
    $odooRules = [
        'BASIC' => $test->odoo->seed('hr.salary.rule', [
            'name' => 'Basic Salary', 'code' => 'BASIC', 'sequence' => 1,
            'category_id' => [$odooCats['BASIC'], 'Basic'], 'amount_select' => 'fix', 'active' => true,
        ]),
        'HRA' => $test->odoo->seed('hr.salary.rule', [
            'name' => 'Housing Allowance', 'code' => 'HRA', 'sequence' => 5,
            'category_id' => [$odooCats['ALW'], 'Allowance'], 'amount_select' => 'percentage', 'active' => true,
        ]),
        'NET' => $test->odoo->seed('hr.salary.rule', [
            'name' => 'Net Salary', 'code' => 'NET', 'sequence' => 100,
            'category_id' => [$odooCats['NET'], 'Net'], 'amount_select' => 'code', 'active' => true,
        ]),
    ];
    runOdooSync($ruleMapping);

    return [$odooCats, $odooRules];
}

test('imports salary rule categories, structures and rules with relations', function () {
    $connection = OdooScenario::connection();

    [$odooCats, $odooRules] = seedPayrollCatalog($this, $connection);

    $structMapping = OdooScenario::salaryStructures($connection);
    $odooStructId = $this->odoo->seed('hr.payroll.structure', ['name' => 'Clinic Staff Structure', 'code' => 'CLINIC']);
    $log = runOdooSync($structMapping);

    expect($log->status)->toBe(SyncStatus::COMPLETED);

    $basicCat = SalaryRuleCategory::where('code', 'BASIC')->first();
    expect($basicCat)->not->toBeNull()
        ->and($basicCat->odoo_id)->toBe($odooCats['BASIC']);

    $structure = SalaryStructure::where('code', 'CLINIC')->first();
    expect($structure)->not->toBeNull()
        ->and($structure->odoo_id)->toBe($odooStructId);

    $hra = SalaryRule::where('code', 'HRA')->first();
    expect($hra)->not->toBeNull()
        ->and($hra->odoo_id)->toBe($odooRules['HRA'])
        ->and($hra->category_id)->toBe(SalaryRuleCategory::where('code', 'ALW')->first()->id)
        ->and($hra->amount_type)->toBe('percentage')
        ->and($hra->sequence)->toBe(5)
        ->and($hra->is_active)->toBeTrue();
});

test('re-running the catalog import links by code instead of duplicating', function () {
    $connection = OdooScenario::connection();

    seedPayrollCatalog($this, $connection);

    $catMapping = \Modules\OdooIntegration\Models\OdooEntityMapping::query()
        ->where('odoo_model', 'hr.salary.rule.category')
        ->firstOrFail();

    $log = runOdooSync($catMapping);

    // Same odoo records, second mapping run — all should update in place
    expect(SalaryRuleCategory::count())->toBe(4)
        ->and($log->records_failed)->toBe(0);
});

test('imports payslips into payroll runs with bucketed rule amounts', function () {
    $connection = OdooScenario::connection();

    // Staff member (via employee sync)
    $staffMapping = OdooScenario::staffProfiles($connection);
    $odooEmpId = $this->odoo->seed('hr.employee', [
        'name' => 'Sara Ahmed', 'user_id' => false, 'work_email' => 'sara@clinic.test', 'active' => true,
    ]);
    runOdooSync($staffMapping);
    $staff = StaffProfile::where('odoo_id', $odooEmpId)->firstOrFail();

    [$odooCats, $odooRules] = seedPayrollCatalog($this, $connection);

    // Payslip with detail lines
    $lineIds = [
        $this->odoo->seed('hr.payslip.line', [
            'code' => 'BASIC', 'name' => 'Basic Salary',
            'category_id' => [$odooCats['BASIC'], 'Basic'],
            'salary_rule_id' => [$odooRules['BASIC'], 'Basic Salary'],
            'total' => 5000.0,
        ]),
        $this->odoo->seed('hr.payslip.line', [
            'code' => 'HRA', 'name' => 'Housing Allowance',
            'category_id' => [$odooCats['ALW'], 'Allowance'],
            'salary_rule_id' => [$odooRules['HRA'], 'Housing Allowance'],
            'total' => 1200.5,
        ]),
        $this->odoo->seed('hr.payslip.line', [
            'code' => 'LOAN', 'name' => 'Loan Repayment',
            'category_id' => [$odooCats['DED'], 'Deduction'],
            'salary_rule_id' => false,
            'total' => 300.0,
        ]),
        $this->odoo->seed('hr.payslip.line', [
            'code' => 'NET', 'name' => 'Net Salary',
            'category_id' => [$odooCats['NET'], 'Net'],
            'salary_rule_id' => [$odooRules['NET'], 'Net Salary'],
            'total' => 5900.5,
        ]),
    ];

    $odooSlipId = $this->odoo->seed('hr.payslip', [
        'employee_id' => [$odooEmpId, 'Sara Ahmed'],
        'state' => 'done',
        'date_from' => '2026-07-01',
        'date_to' => '2026-07-31',
        'number' => 'SLIP/2026/001',
        'line_ids' => $lineIds,
    ]);

    $slipMapping = OdooScenario::payslips($connection);
    $log = runOdooSync($slipMapping);

    expect($log->status)->toBe(SyncStatus::COMPLETED)
        ->and($log->records_created)->toBe(1)
        ->and($log->records_failed)->toBe(0);

    $line = PayrollLine::where('odoo_id', $odooSlipId)->first();
    expect($line)->not->toBeNull()
        ->and($line->staff_profile_id)->toBe($staff->id)
        ->and($line->status)->toBe('approved')                    // done → approved
        ->and($line->base_salary_minor)->toBe(500000)             // 5000.00 EGP
        ->and($line->allowances_minor)->toBe(120050)              // 1200.50 EGP
        ->and($line->deductions_minor)->toBe(30000)
        ->and($line->net_salary_minor)->toBe(590050);

    // Parent run created from the slip period
    $run = PayrollRun::find($line->payroll_run_id);
    expect($run)->not->toBeNull()
        ->and($run->period_year)->toBe(2026)
        ->and($run->period_month)->toBe(7);

    // Per-rule breakdown preserved, rule ids resolved to local rules
    $breakdown = collect($line->rule_amounts_json);
    expect($breakdown)->toHaveCount(4)
        ->and($breakdown->firstWhere('rule_code', 'BASIC')['amount_minor'])->toBe(500000)
        ->and($breakdown->firstWhere('rule_code', 'HRA')['rule_id'])
        ->toBe(SalaryRule::where('code', 'HRA')->first()->id);
});

test('payslips for the same run and employee update instead of duplicating', function () {
    $connection = OdooScenario::connection();

    $staffMapping = OdooScenario::staffProfiles($connection);
    $odooEmpId = $this->odoo->seed('hr.employee', [
        'name' => 'Sara Ahmed', 'user_id' => false, 'work_email' => 'sara@clinic.test', 'active' => true,
    ]);
    runOdooSync($staffMapping);

    [$odooCats, $odooRules] = seedPayrollCatalog($this, $connection);

    $odooSlipId = $this->odoo->seed('hr.payslip', [
        'employee_id' => [$odooEmpId, 'Sara Ahmed'],
        'state' => 'verify',
        'date_from' => '2026-07-01',
        'date_to' => '2026-07-31',
        'number' => 'SLIP/2026/002',
        'line_ids' => [],
    ]);

    $slipMapping = OdooScenario::payslips($connection);
    runOdooSync($slipMapping);

    expect(PayrollLine::count())->toBe(1)
        ->and(PayrollLine::first()->status)->toBe('pending');    // verify → pending

    // Slip is confirmed in Odoo, full sync again
    $this->odoo->write('hr.payslip', [$odooSlipId], ['state' => 'paid']);
    $log = runOdooSync($slipMapping);

    expect($log->records_updated)->toBe(1)
        ->and(PayrollLine::count())->toBe(1)
        ->and(PayrollRun::count())->toBe(1)
        ->and(PayrollLine::first()->status)->toBe('paid');
});

test('skips payslips for employees that are not synced locally', function () {
    $connection = OdooScenario::connection();

    $this->odoo->seed('hr.payslip', [
        'employee_id' => [123456, 'Ghost Employee'],
        'state' => 'done',
        'date_from' => '2026-07-01',
        'date_to' => '2026-07-31',
        'number' => 'SLIP/2026/003',
        'line_ids' => [],
    ]);

    $slipMapping = OdooScenario::payslips($connection);
    $log = runOdooSync($slipMapping);

    expect($log->status)->toBe(SyncStatus::COMPLETED)
        ->and($log->records_skipped)->toBe(1)
        ->and(PayrollLine::count())->toBe(0);
});

test('honors the payslip date filter from filter_conditions', function () {
    $connection = OdooScenario::connection();

    $staffMapping = OdooScenario::staffProfiles($connection);
    $odooEmpId = $this->odoo->seed('hr.employee', [
        'name' => 'Sara Ahmed', 'user_id' => false, 'work_email' => 'sara@clinic.test', 'active' => true,
    ]);
    runOdooSync($staffMapping);

    // Ancient payslip — excluded by date_from >= 2024-01-01
    $this->odoo->seed('hr.payslip', [
        'employee_id' => [$odooEmpId, 'Sara Ahmed'],
        'state' => 'done',
        'date_from' => '2023-06-01',
        'date_to' => '2023-06-30',
        'number' => 'SLIP/2023/001',
        'line_ids' => [],
    ]);

    $slipMapping = OdooScenario::payslips($connection);
    $log = runOdooSync($slipMapping);

    expect($this->odoo->lastDomain('hr.payslip'))->toBe([['date_from', '>=', '2024-01-01']])
        ->and($log->records_processed)->toBe(0)
        ->and(PayrollLine::count())->toBe(0);
});
