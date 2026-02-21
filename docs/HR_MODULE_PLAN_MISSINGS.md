# HR Module - Missing Features Analysis

## Overview

This document compares the Payroll/HR functionality between the backup project and the current X-Linic project to identify missing features for implementation.

| Component | Backup | Current | Gap |
|-----------|--------|---------|-----|
| Payroll Models | 9 | 2 | 7 models |
| Payroll Services | 2 | 1 | 1 service |
| Payroll Controllers | 10 | 0 | 10 controllers |
| Migrations | 17 | 2 | 15 migrations |
| Staff Models | 3+ | 3 | Similar |

---

## Current Implementation (What We Have)

### Payroll Module
- **PayrollRun** - Monthly payroll cycle management with workflow (Draft → Approved → Paid)
- **PayrollLine** - Individual employee salary records
- **SalarySlipPdfService** - PDF generation for payslips
- **Accounting Integration** - Journal entries on payroll payment
- Simple tax calculation (Egyptian brackets) and social insurance (11%)
- Commission aggregation from Staff module

### Staff Module
- **StaffProfile** - Employee records with commission settings
- **StaffCommission** - Commission rules (flat, percentage, tiered)
- **StaffCommissionRecord** - Commission transaction tracking
- Commission widget for dashboard
- Schedule assignment management

---

## Missing Components (To Implement)

### Phase 1: Salary Structure System (High Priority)

#### 1.1 Models to Create

**SalaryRule** (`modules/Payroll/Models/SalaryRule.php`)
```
Fields:
- id, tenant_id, odoo_id
- name, code, category_code
- amount_type: fixed | percentage | formula
- amount_fixed, amount_percentage, amount_formula
- condition_type, condition_formula
- percentage_base_id (self-reference for percentage calculations)
- field_mapping (e.g., contract.salary, employee.allowance)
- sequence, is_active
- created_at, updated_at, deleted_at
```

**SalaryRuleCategory** (`modules/Payroll/Models/SalaryRuleCategory.php`)
```
Fields:
- id, tenant_id
- name, code, description
- type: earning | deduction | allowance | benefit | gross | net
- is_active
- created_at, updated_at
```

**SalaryStructure** (`modules/Payroll/Models/SalaryStructure.php`)
```
Fields:
- id, tenant_id
- name, code, description
- pay_frequency: monthly | bi-weekly | weekly | daily | hourly
- currency (default: EGP)
- is_active
- created_by
- created_at, updated_at
```

**EmployeeSalaryStructure** (`modules/Payroll/Models/EmployeeSalaryStructure.php`)
```
Fields:
- id, tenant_id
- staff_profile_id
- salary_structure_id
- base_salary_minor
- effective_date, end_date
- is_current (only one per employee)
- assigned_by
- notes
- created_at, updated_at
```

**EmployeeSalaryComponent** (`modules/Payroll/Models/EmployeeSalaryComponent.php`)
```
Fields:
- id, tenant_id
- staff_profile_id
- salary_rule_id
- component_type: earning | deduction
- calculation_type: fixed | percentage | formula
- amount_minor, percentage, formula
- effective_date, end_date
- is_taxable, is_active
- loan_id (nullable, for loan repayment deductions)
- created_by
- created_at, updated_at
```

#### 1.2 Migrations to Create

1. `create_salary_rule_categories_table.php`
2. `create_salary_rules_table.php`
3. `create_salary_structures_table.php`
4. `create_employee_salary_structures_table.php`
5. `create_employee_salary_components_table.php`

#### 1.3 Filament Resources to Create

- `SalaryRuleCategoryResource` - Manage salary rule categories
- `SalaryRuleResource` - Manage salary rules with formula editor
- `SalaryStructureResource` - Manage salary structure templates
- `EmployeeSalaryStructureRelationManager` - Assign structures to employees (in StaffProfileResource)
- `EmployeeSalaryComponentRelationManager` - Custom components per employee (in StaffProfileResource)

---

### Phase 2: Advanced Payroll Calculation (High Priority)

#### 2.1 Services to Create

**PayrollCalculationService** (`modules/Payroll/Services/PayrollCalculationService.php`)
```php
Key Methods:
- calculatePayrollRun(PayrollRun $run): void
- calculateEmployeePayslip(StaffProfile $staff, PayrollRun $run): PayrollLine
- calculateSalaryComponents(PayrollLine $line): void
- buildCalculationContext(StaffProfile $staff, PayrollRun $run): array
- getEligibleEmployees(PayrollRun $run): Collection
- getEmployeeSalaryStructure(StaffProfile $staff): ?SalaryStructure
- getEmployeeBaseSalary(StaffProfile $staff): int
- getAttendanceData(StaffProfile $staff, Carbon $start, Carbon $end): array
- getLeaveData(StaffProfile $staff, Carbon $start, Carbon $end): array
- getCommissionData(StaffProfile $staff, Carbon $start, Carbon $end): array
- getWorkingDays(Carbon $start, Carbon $end): int
- processLoanRepayments(StaffProfile $staff, PayrollLine $line): void
- recalculatePayslip(PayrollLine $line): void

Context Variables to Support:
- BASIC, BASE_SALARY, base_salary
- EMPLOYEE_ID
- DAYS, WORKING_DAYS, total_days
- worked_days, worked_hours, overtime_hours, late_minutes
- paid_leave_days, unpaid_leave_days, sick_leave_days, annual_leave_days
- commission_amount, commission_count
- contract.salary, contract.allowances
- GROSS, NET, TOTAL_DEDUCTION, TOTAL_ALLOWANCE
```

**FormulaEvaluator** (`modules/Payroll/Services/FormulaEvaluator.php`)
```php
Key Methods:
- evaluate(string $formula, array $context): mixed
- validateFormula(string $formula): bool
- evaluateRangeCondition(string $condition, array $context): bool
- getAvailableVariables(): array
- getFormulaExamples(): array

Supported Formula Types:
- Fixed amounts: "500"
- Percentage of basic: "base_salary * 0.10"
- Conditional: "base_salary > 3000 ? 1000 : 500"
- Worked days based: "base_salary * (worked_days / total_days)"
- Overtime: "hourly_rate * overtime_hours * 1.5"
- Tax calculations: "gross_salary * tax_rate"
- Absence deductions: "(base_salary / total_days) * absence_days"
```

#### 2.2 Update PayrollRun Workflow

Add workflow states: `draft → calculating → review → approved → processing → completed`

---

### Phase 3: Compensation Management (Medium Priority)

#### 3.1 Models to Create

**CompensationHistory** (`modules/Payroll/Models/CompensationHistory.php`)
```
Fields:
- id, tenant_id
- staff_profile_id
- change_type: increment | promotion | adjustment | new_hire | transfer
- previous_structure_id, new_structure_id
- previous_base_salary_minor, new_base_salary_minor
- change_percentage
- effective_date
- reason, notes
- status: pending | approved | applied | rejected
- requested_by, approved_by, applied_by
- approved_at, applied_at, rejected_at
- attachment_path
- created_at, updated_at
```

#### 3.2 Filament Resources

- `CompensationHistoryResource` - Manage salary change requests
- Approval workflow UI
- Employee compensation history timeline

---

### Phase 4: Bulk Operations (Medium Priority)

#### 4.1 Services to Create

**BulkPayrollOperationsService** (`modules/Payroll/Services/BulkPayrollOperationsService.php`)
```php
Key Methods:
- bulkIncrement(array $filters, float $percentage, ?int $fixedAmount): array
- bulkAdjustment(array $filters, string $type, float|int $value): array
- previewAffectedEmployees(array $filters): Collection

Filters:
- department_id
- designation_id
- salary_structure_id
- specific_employee_ids
```

#### 4.2 Filament Pages

- `BulkSalaryIncrementPage` - Apply percentage/fixed increases to multiple employees
- `BulkSalaryAdjustmentPage` - Apply adjustments with preview

---

### Phase 5: Payroll Reports (Medium Priority)

#### 5.1 Reports to Implement

1. **Salary Register Report**
   - Per-employee salary breakdown
   - Filter by period, department, structure
   - Export to Excel/CSV

2. **Department Analysis Report**
   - Department-wise cost analysis
   - Totals by department
   - Charts/visualizations

3. **Compensation Changes Report**
   - Track all salary changes
   - Filter by change type, date range
   - Export capability

4. **Cost Analysis Report**
   - Group by month/department/structure
   - Trend analysis
   - Budget vs actual

5. **Payroll Summary Report**
   - Summary by payroll run
   - Status breakdown
   - Payment tracking

#### 5.2 Filament Pages

- `PayrollReportsPage` - Report selection and generation
- Individual report pages with filters and export

---

### Phase 6: Attendance Integration (Lower Priority)

#### 6.1 Models to Create (if not exists)

**Attendance** (`app/Models/Attendance.php`)
```
Fields:
- id, tenant_id
- staff_profile_id
- date
- check_in, check_out
- worked_hours, overtime_hours
- late_minutes
- status: present | absent | half_day | leave
- notes
- created_at, updated_at
```

**AttendanceLog** (`app/Models/AttendanceLog.php`)
```
Fields:
- id, attendance_id
- type: check_in | check_out | break_start | break_end
- timestamp
- source: manual | biometric | mobile
- location_data (JSON)
- created_at
```

#### 6.2 Integration Points

- PayrollCalculationService integration
- Attendance data in calculation context
- Late deductions calculation
- Overtime pay calculation

---

### Phase 7: Leave Management Integration (Lower Priority)

#### 7.1 Models (if not exists in existing module)

**LeaveType** - Leave type definitions (Annual, Sick, Unpaid, etc.)
**LeaveBalance** - Employee leave balances
**LeaveRequest** - Leave request workflow

#### 7.2 Integration Points

- Leave days in payroll calculation context
- Paid vs unpaid leave handling
- Leave balance deduction on approval

---

### Phase 8: Loan Management Integration (Lower Priority)

#### 8.1 Models to Create

**EmployeeLoan** (`modules/Payroll/Models/EmployeeLoan.php`)
```
Fields:
- id, tenant_id
- staff_profile_id
- loan_number
- loan_type: personal | advance | housing
- principal_amount_minor
- interest_rate
- total_amount_minor
- monthly_deduction_minor
- remaining_balance_minor
- start_date, end_date
- status: active | completed | cancelled
- approved_by, approved_at
- notes
- created_at, updated_at
```

**LoanRepayment** (`modules/Payroll/Models/LoanRepayment.php`)
```
Fields:
- id, tenant_id
- employee_loan_id
- payroll_line_id
- amount_minor
- repayment_date
- notes
- created_at
```

#### 8.2 Integration

- Auto-create EmployeeSalaryComponent for loan deductions
- Track repayments in payroll
- Update remaining balance on payment

---

### Phase 9: Odoo Integration (Optional)

#### 9.1 Services

**OdooPayrollConnector** (`modules/Payroll/Services/OdooPayrollConnector.php`)
```php
Key Methods:
- syncSalaryRules(): array
- fetchPayslips(array $filters): Collection
- fetchPayslipDetails(string $odooId): array
- pushPayrollData(PayrollRun $run): bool
```

---

## Implementation Priority

### High Priority (Phase 1-2)
1. Salary Rule Categories
2. Salary Rules with formula support
3. Salary Structures
4. Employee-Structure assignments
5. PayrollCalculationService
6. FormulaEvaluator

### Medium Priority (Phase 3-5)
1. Compensation History tracking
2. Bulk salary operations
3. Payroll reports with export

### Lower Priority (Phase 6-8)
1. Attendance integration
2. Leave management integration
3. Loan management

### Optional (Phase 9)
1. Odoo ERP integration

---

## Database Schema Changes

### New Tables Required
1. `salary_rule_categories`
2. `salary_rules`
3. `salary_structures`
4. `employee_salary_structures`
5. `employee_salary_components`
6. `compensation_history`
7. `employee_loans`
8. `loan_repayments`

### Existing Table Modifications
- `payroll_runs` - Add: calculating, review, processing states
- `payroll_lines` - Add: salary_structure_id, calculation_details_json

---

## Estimated Work

| Phase | Description | Effort |
|-------|-------------|--------|
| 1 | Salary Structure System | Large |
| 2 | Advanced Calculation | Large |
| 3 | Compensation Management | Medium |
| 4 | Bulk Operations | Medium |
| 5 | Payroll Reports | Medium |
| 6 | Attendance Integration | Medium |
| 7 | Leave Integration | Small |
| 8 | Loan Management | Medium |
| 9 | Odoo Integration | Large |

---

## Files to Reference from Backup

Key files in `/var/www/html/var/www/x_linic_staging_backup_20260220_184120/`:

### Models
- `Modules/Payroll/Models/SalaryRule.php`
- `Modules/Payroll/Models/SalaryRuleCategory.php`
- `Modules/Payroll/Models/SalaryStructure.php`
- `Modules/Payroll/Models/EmployeeSalaryStructure.php`
- `Modules/Payroll/Models/EmployeeSalaryComponent.php`
- `Modules/Payroll/Models/PayrollRun.php`
- `Modules/Payroll/Models/Payslip.php`
- `Modules/Payroll/Models/PayslipLine.php`
- `Modules/Payroll/Models/CompensationHistory.php`

### Services
- `Modules/Payroll/Services/PayrollCalculationService.php`
- `Modules/Payroll/Services/FormulaEvaluator.php`

### Controllers
- `Modules/Payroll/Http/Controllers/PayrollRunController.php`
- `Modules/Payroll/Http/Controllers/SalaryRuleController.php`
- `Modules/Payroll/Http/Controllers/SalaryStructureController.php`
- `Modules/Payroll/Http/Controllers/BulkOperationsController.php`
- `Modules/Payroll/Http/Controllers/PayrollReportsController.php`

### Migrations
- All files in `Modules/Payroll/database/migrations/`

---

## Notes

1. The backup uses a traditional Controller-based approach while current uses Filament exclusively
2. Need to convert Controller logic to Filament Resources/Pages/Actions
3. Formula evaluation requires Symfony Expression Language package
4. Consider creating Filament custom form components for formula editor
5. Reports should use Filament Tables with export functionality

---

## Implementation Checklist

Following X-Linic implementation standards (Filament-based, BaseModel traits, minor units, i18n).

### Phase 1: Salary Structure System

#### 1.1 SalaryRuleCategory ✅ COMPLETED

**Migration**
- [x] Create `2024_01_01_000003_create_salary_rule_categories_table.php`
  - [x] `uuid('id')->primary()`
  - [x] `uuid('tenant_id')->index()`
  - [x] `string('name')`
  - [x] `string('code')->index()`
  - [x] `text('description')->nullable()`
  - [x] `string('type')` (earning, deduction, allowance, benefit, gross, net)
  - [x] `boolean('is_active')->default(true)`
  - [x] `timestamps()`
  - [x] Multi-column index: `(tenant_id, code)`

**Model** (`modules/Payroll/Models/SalaryRuleCategory.php`)
- [x] Extend `BaseModel`
- [x] Use traits: `HasTenancy`
- [x] Define `$fillable` array
- [x] Define `$casts` (id as string)
- [x] Add constants: `TYPE_EARNING`, `TYPE_DEDUCTION`, `TYPES`, `TYPE_COLORS`
- [x] Add relationship: `hasMany(SalaryRule::class)`
- [x] Add scope: `scopeActive($query)`
- [x] Add scope: `scopeOfType($query, string $type)`

**Filament Resource** (`modules/Payroll/Filament/Resources/SalaryRuleCategoryResource.php`)
- [x] Use `ChecksTenantModuleAccess` trait
- [x] Set `$moduleCode = 'payroll'`
- [x] Set navigation icon, group, sort
- [x] Create form with sections:
  - [x] Basic Info: name, code, type (select), description
  - [x] Settings: is_active toggle
- [x] Create table with columns:
  - [x] code (searchable, sortable)
  - [x] name (searchable)
  - [x] type (badge with colors)
  - [x] is_active (icon)
- [x] Add filters: type, is_active
- [x] Add actions: View, Edit, Delete
- [x] Create pages: List, Create, Edit, View

**Language Files**
- [x] Add to `Lang/en/payroll.php`: labels, types, messages
- [x] Add to `Lang/ar/payroll.php`: Arabic translations

---

#### 1.2 SalaryRule ✅ COMPLETED

**Migration**
- [x] Create `2024_01_01_000004_create_salary_rules_table.php`
  - [x] `uuid('id')->primary()`
  - [x] `uuid('tenant_id')->index()`
  - [x] `string('name')`
  - [x] `string('code')->index()`
  - [x] `uuid('category_id')->index()`
  - [x] `string('amount_type')` (fixed, percentage, formula)
  - [x] `integer('amount_fixed_minor')->default(0)`
  - [x] `decimal('amount_percentage', 8, 4)->nullable()`
  - [x] `text('amount_formula')->nullable()`
  - [x] `string('condition_type')->nullable()`
  - [x] `text('condition_formula')->nullable()`
  - [x] `uuid('percentage_base_id')->nullable()` (self-reference)
  - [x] `string('field_mapping')->nullable()`
  - [x] `integer('sequence')->default(0)`
  - [x] `boolean('is_active')->default(true)`
  - [x] `timestamps()`
  - [x] `softDeletes()`
  - [x] Foreign keys with cascade/set null

**Model** (`modules/Payroll/Models/SalaryRule.php`)
- [x] Extend `BaseModel`
- [x] Use traits: `HasTenancy`, `SoftDeletes`
- [x] Define `$fillable` array
- [x] Define `$casts`
- [x] Add constants: `AMOUNT_TYPE_*`, `AMOUNT_TYPES`, `CONDITION_TYPES`
- [x] Add relationships:
  - [x] `belongsTo(SalaryRuleCategory::class, 'category_id')`
  - [x] `belongsTo(SalaryRule::class, 'percentage_base_id')`
  - [x] `hasMany(SalaryRule::class, 'percentage_base_id')` (dependents)
  - [x] `belongsToMany(SalaryStructure::class)` via pivot
- [x] Add accessor: `getAmountFixedAttribute()` (major units)
- [x] Add scope: `scopeActive($query)`
- [x] Add scope: `scopeEarnings($query)`
- [x] Add scope: `scopeDeductions($query)`
- [x] Add method: `calculateAmount(array $context): int`

**Filament Resource** (`modules/Payroll/Filament/Resources/SalaryRuleResource.php`)
- [x] Use `ChecksTenantModuleAccess` trait
- [x] Create form with sections:
  - [x] Basic Info: name, code, category_id (select with relationship)
  - [x] Calculation: amount_type (reactive select), conditional fields:
    - [x] amount_fixed (if fixed) - converts to minor in page
    - [x] amount_percentage + percentage_base_id (if percentage)
    - [x] amount_formula with helper text (if formula)
  - [x] Conditions: condition_type, condition_formula
  - [x] Advanced: field_mapping, sequence
  - [x] Settings: is_active
- [x] Create table with columns:
  - [x] code (searchable, sortable, bold)
  - [x] name (searchable)
  - [x] category.name (badge)
  - [x] amount_type (badge)
  - [x] sequence (sortable)
  - [x] is_active (icon)
- [x] Add filters: category_id, amount_type, is_active
- [x] Add actions: View, Edit, Delete
- [x] Create pages: List, Create, Edit, View

**Language Files**
- [x] Add salary rule translations to payroll.php

---

#### 1.3 SalaryStructure ✅ COMPLETED

**Migration**
- [x] Create `2024_01_01_000005_create_salary_structures_table.php`
  - [x] `uuid('id')->primary()`
  - [x] `uuid('tenant_id')->index()`
  - [x] `string('name')`
  - [x] `string('code')->index()`
  - [x] `text('description')->nullable()`
  - [x] `string('pay_frequency')->default('monthly')`
  - [x] `string('currency')->default('EGP')`
  - [x] `boolean('is_active')->default(true)`
  - [x] `uuid('created_by')->nullable()`
  - [x] `timestamps()`
  - [x] Unique: `(tenant_id, code)`

**Model** (`modules/Payroll/Models/SalaryStructure.php`)
- [x] Extend `BaseModel`
- [x] Use traits: `HasTenancy`
- [x] Define `$fillable`, `$casts`
- [x] Add constants: `PAY_FREQUENCY_*`, `PAY_FREQUENCIES`, `PAY_FREQUENCY_COLORS`
- [x] Add relationships:
  - [x] `belongsTo(User::class, 'created_by')`
  - [x] `belongsToMany(SalaryRule::class)` via pivot with sequence
  - [x] `hasMany(EmployeeSalaryStructure::class)`
  - [x] `earningRules()` and `deductionRules()` filtered relationships
- [x] Add scope: `scopeActive($query)`
- [x] Add method: `getActiveEmployeeCountAttribute(): int`
- [x] Add method: `calculatePayroll(array $context): array`
- [x] Add method: `duplicate(string $name, string $code): self`

**Pivot Migration** (included in salary_structures migration)
- [x] Create `salary_structure_rules` table
  - [x] `uuid('salary_structure_id')`
  - [x] `uuid('salary_rule_id')`
  - [x] `integer('sequence')->default(0)`
  - [x] `timestamps()`
  - [x] Primary key on both columns
  - [x] Foreign keys with cascade

**Filament Resource** (`modules/Payroll/Filament/Resources/SalaryStructureResource.php`)
- [x] Create form with sections:
  - [x] Basic Info: name, code, description
  - [x] Settings: pay_frequency (select), currency, is_active
- [x] Create table with columns:
  - [x] code (searchable, sortable, bold)
  - [x] name (searchable)
  - [x] pay_frequency (badge)
  - [x] rules_count (computed)
  - [x] employees_count (computed)
  - [x] is_active (icon)
- [x] Add Relation Manager: `RulesRelationManager`
  - [x] Attach/detach salary rules
  - [x] Edit sequence action
  - [x] Reorderable by sequence
- [x] Add Duplicate action with form
- [ ] Add Relation Manager: `EmployeesRelationManager` (pending - Phase 1.4)
- [x] Create pages: List, Create, Edit, View

---

#### 1.4 EmployeeSalaryStructure ✅ PARTIALLY COMPLETED

**Migration**
- [x] Create `2024_01_01_000006_create_employee_salary_structures_table.php`
  - [x] `uuid('id')->primary()`
  - [x] `uuid('tenant_id')->index()`
  - [x] `uuid('staff_profile_id')->index()`
  - [x] `uuid('salary_structure_id')->index()`
  - [x] `integer('base_salary_minor')->default(0)`
  - [x] `date('effective_date')`
  - [x] `date('end_date')->nullable()`
  - [x] `boolean('is_current')->default(false)`
  - [x] `uuid('assigned_by')->nullable()`
  - [x] `text('notes')->nullable()`
  - [x] `timestamps()`
  - [x] Index: `(tenant_id, staff_profile_id, is_current)`

**Model** (`modules/Payroll/Models/EmployeeSalaryStructure.php`)
- [x] Extend `BaseModel`
- [x] Use traits: `HasTenancy`
- [x] Define `$fillable`, `$casts`
- [x] Add relationships:
  - [x] `belongsTo(StaffProfile::class)`
  - [x] `belongsTo(SalaryStructure::class)`
  - [x] `belongsTo(User::class, 'assigned_by')`
- [x] Add accessor: `getBaseSalaryAttribute()` (major units)
- [x] Add scope: `scopeCurrent($query)`
- [x] Add scope: `scopeActive($query)`
- [x] Add boot logic: ensure only one `is_current` per employee
- [x] Add method: `isEffective(): bool`
- [x] Add method: `makeCurrent(): self`

**Relation Manager** (`StaffProfileResource/RelationManagers/SalaryStructuresRelationManager.php`)
- [ ] Form: salary_structure_id, base_salary_minor, effective_date, end_date, is_current, notes
- [ ] Table: structure name, base_salary, effective_date, end_date, is_current (badge)
- [ ] Actions: Create, Edit, Delete (with confirmations)
- [ ] Header action: "Assign Structure"

---

#### 1.5 EmployeeSalaryComponent

**Migration**
- [ ] Create `YYYY_MM_DD_create_employee_salary_components_table.php`
  - [ ] `uuid('id')->primary()`
  - [ ] `uuid('tenant_id')->index()`
  - [ ] `uuid('staff_profile_id')->index()`
  - [ ] `uuid('salary_rule_id')->index()`
  - [ ] `string('component_type')` (earning, deduction)
  - [ ] `string('calculation_type')` (fixed, percentage, formula)
  - [ ] `integer('amount_minor')->default(0)`
  - [ ] `decimal('percentage', 5, 2)->nullable()`
  - [ ] `text('formula')->nullable()`
  - [ ] `date('effective_date')`
  - [ ] `date('end_date')->nullable()`
  - [ ] `boolean('is_taxable')->default(true)`
  - [ ] `boolean('is_active')->default(true)`
  - [ ] `uuid('loan_id')->nullable()`
  - [ ] `uuid('created_by')->nullable()`
  - [ ] `timestamps()`

**Model** (`modules/Payroll/Models/EmployeeSalaryComponent.php`)
- [ ] Extend `BaseModel`
- [ ] Use traits: `HasTenancy`
- [ ] Define `$fillable`, `$casts`
- [ ] Add constants: `COMPONENT_TYPE_*`, `CALCULATION_TYPE_*`
- [ ] Add relationships
- [ ] Add accessor: `getAmountAttribute()` (major units)
- [ ] Add scope: `scopeActive($query)`
- [ ] Add scope: `scopeEarnings($query)`
- [ ] Add scope: `scopeDeductions($query)`
- [ ] Add method: `calculateValue(array $context): int`

**Relation Manager** (`StaffProfileResource/RelationManagers/SalaryComponentsRelationManager.php`)
- [ ] Form with reactive fields based on calculation_type
- [ ] Table: rule name, component_type (badge), calculation_type, amount/percentage, is_active
- [ ] Actions: Create, Edit, Delete, Toggle Active

---

### Phase 2: Advanced Payroll Calculation

#### 2.1 PayrollCalculationService

**Service** (`modules/Payroll/Services/PayrollCalculationService.php`)
- [ ] Create singleton service
- [ ] Inject FormulaEvaluator dependency
- [ ] Implement `calculatePayrollRun(PayrollRun $run): void`
  - [ ] Get eligible employees
  - [ ] Loop and calculate each payslip
  - [ ] Update run totals
- [ ] Implement `calculateEmployeePayslip(StaffProfile $staff, PayrollRun $run): PayrollLine`
  - [ ] Get employee salary structure
  - [ ] Build calculation context
  - [ ] Apply salary rules in sequence
  - [ ] Calculate tax and social insurance
  - [ ] Return PayrollLine
- [ ] Implement `buildCalculationContext(StaffProfile $staff, PayrollRun $run): array`
  - [ ] Include base salary, worked days, leave days
  - [ ] Include commission data from StaffCommissionRecord
  - [ ] Include attendance data (if available)
- [ ] Implement helper methods:
  - [ ] `getWorkingDaysInPeriod(Carbon $start, Carbon $end): int`
  - [ ] `getCommissionForPeriod(StaffProfile $staff, Carbon $start, Carbon $end): int`
- [ ] Register in PayrollServiceProvider as singleton

#### 2.2 FormulaEvaluator

**Service** (`modules/Payroll/Services/FormulaEvaluator.php`)
- [ ] Install Symfony Expression Language: `composer require symfony/expression-language`
- [ ] Create service class
- [ ] Implement `evaluate(string $formula, array $context): mixed`
  - [ ] Handle exceptions gracefully
  - [ ] Return 0 on error with logging
- [ ] Implement `validateFormula(string $formula): bool`
- [ ] Implement `getAvailableVariables(): array`
- [ ] Implement `getFormulaExamples(): array`
- [ ] Register in PayrollServiceProvider

#### 2.3 Update PayrollRun Model

**Model Updates**
- [ ] Add new status constants: `STATUS_CALCULATING`, `STATUS_REVIEW`, `STATUS_PROCESSING`
- [ ] Update `STATUSES` array
- [ ] Update `STATUS_COLORS` array
- [ ] Update `canTransitionTo()` method
- [ ] Add method: `startCalculation(): bool`
- [ ] Add method: `markAsReview(): bool`
- [ ] Add method: `startProcessing(): bool`
- [ ] Add method: `complete(): bool`

**Migration**
- [ ] Create migration to update status enum/check constraint if needed

#### 2.4 Update PayrollRunResource

**Resource Updates**
- [ ] Add "Calculate" action (visible when draft)
  - [ ] Show confirmation modal
  - [ ] Call PayrollCalculationService
  - [ ] Show success notification with count
- [ ] Add "Recalculate" action (visible when review)
- [ ] Update status badge colors
- [ ] Add calculation progress indicator (optional)

---

### Phase 3: Compensation Management

#### 3.1 CompensationHistory

**Migration**
- [ ] Create `YYYY_MM_DD_create_compensation_history_table.php`
  - [ ] UUID primary key
  - [ ] `uuid('staff_profile_id')->index()`
  - [ ] `string('change_type')` (increment, promotion, adjustment, new_hire, transfer)
  - [ ] `uuid('previous_structure_id')->nullable()`
  - [ ] `uuid('new_structure_id')->nullable()`
  - [ ] `integer('previous_base_salary_minor')->default(0)`
  - [ ] `integer('new_base_salary_minor')->default(0)`
  - [ ] `decimal('change_percentage', 5, 2)->nullable()`
  - [ ] `date('effective_date')`
  - [ ] `text('reason')->nullable()`
  - [ ] `text('notes')->nullable()`
  - [ ] `string('status')->default('pending')`
  - [ ] `uuid('requested_by')->nullable()`
  - [ ] `uuid('approved_by')->nullable()`
  - [ ] `uuid('applied_by')->nullable()`
  - [ ] `timestamp('approved_at')->nullable()`
  - [ ] `timestamp('applied_at')->nullable()`
  - [ ] `timestamp('rejected_at')->nullable()`
  - [ ] `string('attachment_path')->nullable()`
  - [ ] `timestamps()`
  - [ ] Indexes for tenant, staff, status

**Model** (`modules/Payroll/Models/CompensationHistory.php`)
- [ ] Extend `BaseModel`
- [ ] Use traits: `HasTenancy`
- [ ] Add constants: `CHANGE_TYPE_*`, `STATUS_*`
- [ ] Add relationships
- [ ] Add state machine methods: `approve()`, `apply()`, `reject()`
- [ ] Add accessors for salary amounts (major units)

**Filament Resource** (`modules/Payroll/Filament/Resources/CompensationHistoryResource.php`)
- [ ] Form with all fields
- [ ] Table with status badges, change amounts
- [ ] Actions: View, Approve, Apply, Reject (with modals)
- [ ] Filters: change_type, status, date range
- [ ] Pages: List, Create, View

**Relation Manager** (`StaffProfileResource/RelationManagers/CompensationHistoryRelationManager.php`)
- [ ] Show employee's compensation history timeline
- [ ] Create new compensation change requests

---

### Phase 4: Bulk Operations

#### 4.1 Bulk Salary Operations

**Service** (`modules/Payroll/Services/BulkPayrollOperationsService.php`)
- [ ] Implement `previewEmployees(array $filters): Collection`
- [ ] Implement `bulkIncrement(array $employeeIds, float $percentage, ?int $fixedMinor): array`
  - [ ] Create CompensationHistory records
  - [ ] Update EmployeeSalaryStructure records
  - [ ] Return summary (count, total increase)
- [ ] Implement `bulkAdjustment()` method

**Filament Page** (`modules/Payroll/Filament/Pages/BulkSalaryOperationsPage.php`)
- [ ] Create custom Filament page
- [ ] Form for filters (branch, structure, specific employees)
- [ ] Preview table showing affected employees
- [ ] Input for percentage/fixed amount
- [ ] Confirmation modal
- [ ] Success notification with summary

---

### Phase 5: Payroll Reports

#### 5.1 Report Pages

**Filament Pages**
- [ ] Create `modules/Payroll/Filament/Pages/PayrollReportsPage.php`
  - [ ] Report type selector
  - [ ] Date range filters
  - [ ] Department/Structure filters
- [ ] Create `SalaryRegisterReport` - per-employee breakdown
- [ ] Create `PayrollSummaryReport` - summary by run
- [ ] Create `CompensationChangesReport` - salary changes

**Export Feature**
- [ ] Install: `composer require maatwebsite/excel` (if not installed)
- [ ] Create export classes for each report
- [ ] Add export buttons to report pages

---

### Phase 6-8: Integration Features

#### 6.1 Attendance Integration (if module exists)
- [ ] Update PayrollCalculationService to fetch attendance data
- [ ] Add worked_days, late_minutes to calculation context
- [ ] Create attendance deduction rules

#### 7.1 Leave Integration (if module exists)
- [ ] Update PayrollCalculationService to fetch leave data
- [ ] Add leave days (paid/unpaid) to calculation context
- [ ] Handle unpaid leave deductions

#### 8.1 Loan Management
- [ ] Create EmployeeLoan model and migration
- [ ] Create LoanRepayment model and migration
- [ ] Create EmployeeLoanResource
- [ ] Add loan deductions to payroll calculation
- [ ] Auto-update remaining balance on payroll payment

---

### Language Files Checklist

**English** (`modules/Payroll/Lang/en/payroll.php`)
- [x] Add `salary_rule_categories` section (navigation, labels)
- [x] Add `salary_rules` section (navigation, labels)
- [x] Add `salary_structures` section (navigation, labels)
- [x] Add shared fields (name, code, description, type, category, etc.)
- [x] Add `amount_types` array
- [x] Add `condition_types` array
- [x] Add `pay_frequencies` array
- [x] Add `help` section with helper texts
- [ ] Add `employee_salary_structures` section
- [ ] Add `employee_salary_components` section
- [ ] Add `compensation_history` section
- [ ] Add `bulk_operations` section
- [ ] Add `reports` section
- [ ] Add `change_types` array

**Arabic** (`modules/Payroll/Lang/ar/payroll.php`)
- [x] Translate Phase 1.1-1.3 strings (categories, rules, structures)
- [ ] Translate remaining Phase 1.4+ strings

---

### Config Updates

**Update** `modules/Payroll/Config/config.php`
- [ ] Add `formula_engine` settings
- [ ] Add `calculation` settings (rounding, precision)
- [ ] Add `bulk_operations` settings (max batch size)
- [ ] Add `reports` settings (export formats)

---

### Testing Checklist

- [ ] Test salary rule CRUD
- [ ] Test salary structure with rules assignment
- [ ] Test employee structure assignment
- [ ] Test payroll calculation with formula rules
- [ ] Test compensation history workflow
- [ ] Test bulk salary increment
- [ ] Test report generation and export
- [ ] Test multi-tenant isolation
- [ ] Test Arabic translations display correctly
