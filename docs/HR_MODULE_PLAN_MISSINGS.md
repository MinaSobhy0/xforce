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

#### 1.4 EmployeeSalaryStructure ✅ COMPLETED

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
- [x] Form: salary_structure_id, base_salary_minor, effective_date, end_date, is_current, notes
- [x] Table: structure name, base_salary, effective_date, end_date, is_current (badge)
- [x] Actions: Create, Edit, Delete (with confirmations)
- [x] Header action: "Make Current"

---

#### 1.5 EmployeeSalaryComponent ✅ COMPLETED

**Migration**
- [x] Create `2024_01_01_000007_create_employee_salary_components_table.php`
  - [x] `uuid('id')->primary()`
  - [x] `uuid('tenant_id')->index()`
  - [x] `uuid('staff_profile_id')->index()`
  - [x] `uuid('salary_rule_id')->nullable()->index()`
  - [x] `string('name')` - custom name if no salary rule
  - [x] `string('component_type')` (earning, deduction)
  - [x] `string('calculation_type')` (fixed, percentage, formula)
  - [x] `integer('amount_minor')->default(0)`
  - [x] `decimal('percentage', 8, 4)->nullable()`
  - [x] `text('formula')->nullable()`
  - [x] `date('effective_date')`
  - [x] `date('end_date')->nullable()`
  - [x] `boolean('is_taxable')->default(true)`
  - [x] `boolean('is_active')->default(true)`
  - [x] `uuid('loan_id')->nullable()`
  - [x] `uuid('created_by')->nullable()`
  - [x] `timestamps()`

**Model** (`modules/Payroll/Models/EmployeeSalaryComponent.php`)
- [x] Extend `BaseModel`
- [x] Use traits: `HasTenancy`
- [x] Define `$fillable`, `$casts`
- [x] Add constants: `COMPONENT_TYPE_*`, `CALCULATION_TYPE_*`
- [x] Add relationships: staffProfile, salaryRule, createdBy
- [x] Add accessor: `getAmountAttribute()` (major units)
- [x] Add scope: `scopeActive($query)`
- [x] Add scope: `scopeEffective($query)`
- [x] Add scope: `scopeEarnings($query)`
- [x] Add scope: `scopeDeductions($query)`
- [x] Add scope: `scopeTaxable($query)`
- [x] Add method: `calculateValue(array $context): int`
- [x] Add method: `isEffective(): bool`
- [x] Add accessor: `getDisplayNameAttribute(): string`
- [x] Add accessor: `getSignAttribute(): int`
- [x] Add accessor: `getSignedAmountMinorAttribute(): int`

**Relation Manager** (`StaffProfileResource/RelationManagers/SalaryComponentsRelationManager.php`)
- [x] Form with reactive fields based on calculation_type
- [x] Table: display_name, component_type (badge), calculation_type, value, is_active
- [x] Actions: Create, Edit, Delete, Toggle Active

---

### Phase 2: Advanced Payroll Calculation ✅ COMPLETED

#### 2.1 PayrollCalculationService ✅ COMPLETED

**Service** (`modules/Payroll/Services/PayrollCalculationService.php`)
- [x] Create singleton service
- [x] Inject FormulaEvaluator dependency
- [x] Implement `calculatePayrollRun(PayrollRun $run): void`
  - [x] Get eligible employees
  - [x] Loop and calculate each payslip
  - [x] Update run totals
- [x] Implement `calculateEmployeePayslip(StaffProfile $staff, PayrollRun $run): PayrollLine`
  - [x] Get employee salary structure
  - [x] Build calculation context
  - [x] Apply salary rules in sequence
  - [x] Calculate tax and social insurance
  - [x] Return PayrollLine
- [x] Implement `buildCalculationContext(StaffProfile $staff, PayrollRun $run): array`
  - [x] Include base salary, worked days, leave days
  - [x] Include commission data from StaffCommissionRecord
  - [x] Include attendance data (placeholder for future)
- [x] Implement helper methods:
  - [x] `getWorkingDaysInPeriod(Carbon $start, Carbon $end): int`
  - [x] `getCommissionData(StaffProfile $staff, Carbon $start, Carbon $end): array`
- [x] Register in PayrollServiceProvider as singleton

#### 2.2 FormulaEvaluator ✅ COMPLETED

**Service** (`modules/Payroll/Services/FormulaEvaluator.php`)
- [x] Install Symfony Expression Language: `composer require symfony/expression-language`
- [x] Create service class
- [x] Implement `evaluate(string $formula, array $context): mixed`
  - [x] Handle exceptions gracefully
  - [x] Return 0 on error with logging
- [x] Implement `validateFormula(string $formula): array`
- [x] Implement `evaluateCondition(string $condition, array $context): bool`
- [x] Implement `getAvailableVariables(): array`
- [x] Implement `getFormulaExamples(): array`
- [x] Register custom functions (min, max, abs, round, floor, ceil, if_else, percentage)
- [x] Register in PayrollServiceProvider

#### 2.3 Update PayrollRun Model ✅ COMPLETED

**Model Updates**
- [x] Add new status constants: `STATUS_CALCULATING`, `STATUS_REVIEW`, `STATUS_PROCESSING`
- [x] Update `STATUSES` array
- [x] Update `STATUS_COLORS` array
- [x] Update `canTransitionTo()` method with new workflow
- [x] Add method: `startCalculation(): bool`
- [x] Add method: `markAsReview(): bool`
- [x] Add method: `startProcessing(): bool`
- [x] Add method: `resetToDraft(): bool`
- [x] Update `markAsPaid()` to handle processing state
- [x] Add `canCalculate()` and `canRecalculate()` helper methods

**Migration**
- [x] Status stored as string, no migration needed

#### 2.4 Update PayrollRunResource ✅ COMPLETED

**Resource Updates**
- [x] Add "Calculate" action (visible when draft)
  - [x] Show confirmation modal with description
  - [x] Call PayrollCalculationService
  - [x] Show success notification with count
- [x] Add "Recalculate" action (visible when review)
  - [x] Show confirmation modal with warning about overwrites
  - [x] Call PayrollCalculationService
- [x] Update status badge colors (new colors for new statuses)
- [x] Update language files (en/ar) with new translations

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
- [x] Add `employee_salary_structures` fields (effective_date, end_date, is_current)
- [x] Add `employee_salary_components` section (component_types, calculation_types)
- [ ] Add `compensation_history` section
- [ ] Add `bulk_operations` section
- [ ] Add `reports` section
- [ ] Add `change_types` array

**Arabic** (`modules/Payroll/Lang/ar/payroll.php`)
- [x] Translate Phase 1.1-1.3 strings (categories, rules, structures)
- [x] Translate Phase 1.4-1.5 strings (employee structures, components)

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

---

## Attendance Module (New Module)

### Overview

Complete attendance tracking system with GPS-enabled check-in/out, violation management, and payroll integration.

| Component | Description |
|-----------|-------------|
| Attendance | Daily attendance records with check-in/out times |
| AttendanceLog | GPS-enabled logs with location data |
| AttendanceBreak | Break time tracking |
| WorkingSchedule | Complex scheduling (fixed, flexible, shift, remote, hybrid) |
| AttendanceRule | Configurable violation rules by category |
| AttendanceRuleAction | Tiered penalties based on thresholds |
| AttendanceViolation | Violation tracking with approval workflow |

---

### Phase A1: Core Attendance System (High Priority)

#### A1.1 Module Structure

**Create Module Structure** (`modules/Attendance/`)
```
modules/Attendance/
├── Config/
│   └── config.php
├── Database/
│   ├── Migrations/
│   └── Seeders/
├── Filament/
│   ├── Pages/
│   ├── Resources/
│   └── Widgets/
├── Lang/
│   ├── ar/
│   │   └── attendance.php
│   └── en/
│       └── attendance.php
├── Listeners/
├── Models/
├── Providers/
│   ├── AttendanceServiceProvider.php
│   └── RouteServiceProvider.php
├── Routes/
│   ├── api.php
│   └── web.php
├── Services/
└── module.json
```

**module.json**
```json
{
    "name": "Attendance",
    "alias": "attendance",
    "description": "Attendance tracking, working schedules, and violation management",
    "keywords": ["attendance", "hr", "check-in", "violations", "schedules"],
    "priority": 0,
    "providers": [
        "Modules\\Attendance\\Providers\\AttendanceServiceProvider",
        "Modules\\Attendance\\Providers\\RouteServiceProvider"
    ],
    "files": []
}
```

---

#### A1.2 Attendance Model & Migration

**Migration** (`modules/Attendance/Database/Migrations/`)

- [ ] Create `YYYY_MM_DD_create_attendances_table.php`
  - [ ] `uuid('id')->primary()`
  - [ ] `uuid('tenant_id')->index()`
  - [ ] `uuid('staff_profile_id')->index()`
  - [ ] `uuid('branch_id')->nullable()->index()`
  - [ ] `uuid('working_schedule_id')->nullable()->index()`
  - [ ] `date('attendance_date')`
  - [ ] `time('check_in_time')->nullable()`
  - [ ] `time('check_out_time')->nullable()`
  - [ ] `string('attendance_type')->default('manual')` (from AttendanceType enum)
  - [ ] `string('status')->default('present')` (present, absent, half_day, leave)
  - [ ] `decimal('working_hours', 5, 2)->default(0)`
  - [ ] `decimal('late_hours', 5, 2)->default(0)`
  - [ ] `decimal('early_hours', 5, 2)->default(0)`
  - [ ] `decimal('overtime_hours', 5, 2)->default(0)`
  - [ ] `text('late_reason')->nullable()`
  - [ ] `text('early_checkout_reason')->nullable()`
  - [ ] `text('notes')->nullable()`
  - [ ] `uuid('approved_by')->nullable()`
  - [ ] `timestamp('approved_at')->nullable()`
  - [ ] `uuid('created_by')->nullable()`
  - [ ] `uuid('updated_by')->nullable()`
  - [ ] `timestamps()`
  - [ ] `softDeletes()`
  - [ ] Unique: `(tenant_id, staff_profile_id, attendance_date)`

**Model** (`modules/Attendance/Models/Attendance.php`)
- [ ] Extend `BaseModel` from `XLinic\Framework\Core\Model\BaseModel`
- [ ] Use traits: `SoftDeletes` (HasTenancy is in BaseModel)
- [ ] Define constants (following PayrollRun pattern):
  ```php
  // Attendance Types
  public const TYPE_MANUAL = 'manual';
  public const TYPE_GEOFENCE = 'geofence';
  public const TYPE_QR_STATIC = 'qr_static';
  public const TYPE_QR_DYNAMIC = 'qr_dynamic';
  public const TYPE_BIOMETRIC = 'biometric';
  public const TYPE_MOBILE = 'mobile';

  public const TYPES = [
      self::TYPE_MANUAL => 'Manual',
      self::TYPE_GEOFENCE => 'Geofence',
      self::TYPE_QR_STATIC => 'Static QR',
      self::TYPE_QR_DYNAMIC => 'Dynamic QR',
      self::TYPE_BIOMETRIC => 'Biometric',
      self::TYPE_MOBILE => 'Mobile App',
  ];

  // Status
  public const STATUS_PRESENT = 'present';
  public const STATUS_ABSENT = 'absent';
  public const STATUS_HALF_DAY = 'half_day';
  public const STATUS_LEAVE = 'leave';

  public const STATUSES = [
      self::STATUS_PRESENT => 'Present',
      self::STATUS_ABSENT => 'Absent',
      self::STATUS_HALF_DAY => 'Half Day',
      self::STATUS_LEAVE => 'On Leave',
  ];
  ```
- [ ] Define `$fillable`, `$casts`
- [ ] Add relationships:
  - [ ] `belongsTo(StaffProfile::class, 'staff_profile_id')`
  - [ ] `belongsTo(Branch::class)`
  - [ ] `belongsTo(WorkingSchedule::class)`
  - [ ] `hasMany(AttendanceLog::class)`
  - [ ] `hasMany(AttendanceViolation::class)`
  - [ ] `belongsTo(User::class, 'approved_by')`
  - [ ] `belongsTo(User::class, 'created_by')`
- [ ] Add scopes: `scopeForDate()`, `scopeForPeriod()`, `scopeForStaff()`
- [ ] Add methods:
  - [ ] `isCheckedOut(): bool`
  - [ ] `latestLog(): ?AttendanceLog`
  - [ ] `calculateWorkingHours(): float`

---

#### A1.3 AttendanceLog Model & Migration

**Migration**
- [ ] Create `YYYY_MM_DD_create_attendance_logs_table.php`
  - [ ] `uuid('id')->primary()`
  - [ ] `uuid('tenant_id')->index()`
  - [ ] `uuid('attendance_id')->index()`
  - [ ] `string('type')` (check_in, check_out, break_start, break_end)
  - [ ] `decimal('latitude', 10, 8)->nullable()`
  - [ ] `decimal('longitude', 11, 8)->nullable()`
  - [ ] `decimal('altitude', 10, 2)->nullable()`
  - [ ] `decimal('horizontal_accuracy', 8, 2)->nullable()`
  - [ ] `decimal('vertical_accuracy', 8, 2)->nullable()`
  - [ ] `decimal('speed', 8, 2)->nullable()`
  - [ ] `text('address')->nullable()`
  - [ ] `string('source')->default('manual')` (manual, mobile, biometric, web)
  - [ ] `json('device_info')->nullable()`
  - [ ] `text('notes')->nullable()`
  - [ ] `uuid('created_by')->nullable()`
  - [ ] `timestamps()`
  - [ ] `softDeletes()`
  - [ ] Foreign key: `attendance_id` → `attendances.id` cascade

**Model** (`modules/Attendance/Models/AttendanceLog.php`)
- [ ] Extend `BaseModel`
- [ ] Use traits: `SoftDeletes`
- [ ] Define constants:
  ```php
  // Log Types
  public const TYPE_CHECK_IN = 'check_in';
  public const TYPE_CHECK_OUT = 'check_out';
  public const TYPE_BREAK_START = 'break_start';
  public const TYPE_BREAK_END = 'break_end';

  public const TYPES = [
      self::TYPE_CHECK_IN => 'Check In',
      self::TYPE_CHECK_OUT => 'Check Out',
      self::TYPE_BREAK_START => 'Break Start',
      self::TYPE_BREAK_END => 'Break End',
  ];

  // Source
  public const SOURCE_MANUAL = 'manual';
  public const SOURCE_MOBILE = 'mobile';
  public const SOURCE_BIOMETRIC = 'biometric';
  public const SOURCE_WEB = 'web';

  public const SOURCES = [
      self::SOURCE_MANUAL => 'Manual Entry',
      self::SOURCE_MOBILE => 'Mobile App',
      self::SOURCE_BIOMETRIC => 'Biometric Device',
      self::SOURCE_WEB => 'Web Portal',
  ];
  ```
- [ ] Define `$fillable`, `$casts`
- [ ] Add relationships: `attendance`, `createdBy`
- [ ] Add accessor: `getFormattedLocationAttribute()`

---

#### A1.4 AttendanceBreak Model & Migration

**Migration**
- [ ] Create `YYYY_MM_DD_create_attendance_breaks_table.php`
  - [ ] `uuid('id')->primary()`
  - [ ] `uuid('tenant_id')->index()`
  - [ ] `uuid('attendance_id')->index()`
  - [ ] `timestamp('start_time')`
  - [ ] `timestamp('end_time')->nullable()`
  - [ ] `integer('duration_minutes')->default(0)`
  - [ ] `text('reason')->nullable()`
  - [ ] `timestamps()`
  - [ ] `softDeletes()`

**Model** (`modules/Attendance/Models/AttendanceBreak.php`)
- [ ] Extend `BaseModel`
- [ ] Use traits: `HasTenancy`, `SoftDeletes`
- [ ] Add relationships: `attendance`
- [ ] Add method: `calculateDuration(): int`

---

### Phase A2: Working Schedules (High Priority)

#### A2.1 WorkingSchedule Model & Migration

**Migration**
- [ ] Create `YYYY_MM_DD_create_working_schedules_table.php`
  - [ ] `uuid('id')->primary()`
  - [ ] `uuid('tenant_id')->index()`
  - [ ] `uuid('branch_id')->nullable()->index()`
  - [ ] `string('name')`
  - [ ] `string('code')->unique()`
  - [ ] `text('description')->nullable()`
  - [ ] `string('type')->default('fixed')` (fixed, flexible, shift, compressed, remote)
  - [ ] **Fixed Schedule:**
    - [ ] `time('start_time')->nullable()`
    - [ ] `time('end_time')->nullable()`
    - [ ] `decimal('hours_per_day', 5, 2)->default(8.00)`
    - [ ] `decimal('hours_per_week', 5, 2)->default(40.00)`
  - [ ] **Grace Periods:**
    - [ ] `integer('grace_period_late_minutes')->default(0)`
    - [ ] `integer('grace_period_early_minutes')->default(0)`
  - [ ] **Flexible Schedule:**
    - [ ] `boolean('is_flexible')->default(false)`
    - [ ] `time('flexible_start_from')->nullable()`
    - [ ] `time('flexible_start_to')->nullable()`
    - [ ] `time('flexible_end_from')->nullable()`
    - [ ] `time('flexible_end_to')->nullable()`
    - [ ] `decimal('flexible_min_hours_per_day', 5, 2)->nullable()`
    - [ ] `decimal('flexible_max_hours_per_day', 5, 2)->nullable()`
  - [ ] **Core Hours:**
    - [ ] `boolean('core_hours_required')->default(false)`
    - [ ] `time('core_hours_start')->nullable()`
    - [ ] `time('core_hours_end')->nullable()`
  - [ ] **Working Days:**
    - [ ] `json('working_days')->nullable()` ([0,1,2,3,4,5,6] - Sunday=0)
    - [ ] `decimal('days_per_week', 3, 1)->default(5.0)`
  - [ ] **Break Settings:**
    - [ ] `boolean('has_break')->default(true)`
    - [ ] `integer('break_duration_minutes')->default(60)`
    - [ ] `time('break_start')->nullable()`
    - [ ] `time('break_end')->nullable()`
    - [ ] `boolean('flexible_break')->default(false)`
  - [ ] **Overtime:**
    - [ ] `boolean('allow_overtime')->default(true)`
    - [ ] `decimal('max_overtime_per_day', 5, 2)->nullable()`
    - [ ] `decimal('max_overtime_per_week', 5, 2)->nullable()`
  - [ ] **Shift Rotation:**
    - [ ] `boolean('is_rotating_shift')->default(false)`
    - [ ] `integer('rotation_cycle_days')->nullable()`
    - [ ] `json('shift_pattern')->nullable()`
  - [ ] **Remote/Hybrid:**
    - [ ] `boolean('is_remote')->default(false)`
    - [ ] `boolean('is_hybrid')->default(false)`
    - [ ] `integer('remote_days_per_week')->nullable()`
    - [ ] `json('remote_days')->nullable()`
  - [ ] **Status:**
    - [ ] `string('status')->default('active')` (active, inactive)
    - [ ] `boolean('is_default')->default(false)`
    - [ ] `text('notes')->nullable()`
  - [ ] `uuid('created_by')->nullable()`
  - [ ] `uuid('updated_by')->nullable()`
  - [ ] `timestamps()`
  - [ ] `softDeletes()`

**Model** (`modules/Attendance/Models/WorkingSchedule.php`)
- [ ] Extend `BaseModel`
- [ ] Use traits: `SoftDeletes`
- [ ] Define constants:
  ```php
  // Schedule Types
  public const TYPE_FIXED = 'fixed';
  public const TYPE_FLEXIBLE = 'flexible';
  public const TYPE_SHIFT = 'shift';
  public const TYPE_COMPRESSED = 'compressed';
  public const TYPE_REMOTE = 'remote';

  public const TYPES = [
      self::TYPE_FIXED => 'Fixed Hours',
      self::TYPE_FLEXIBLE => 'Flexible Hours',
      self::TYPE_SHIFT => 'Shift Work',
      self::TYPE_COMPRESSED => 'Compressed Week',
      self::TYPE_REMOTE => 'Remote',
  ];

  // Status
  public const STATUS_ACTIVE = 'active';
  public const STATUS_INACTIVE = 'inactive';

  public const STATUSES = [
      self::STATUS_ACTIVE => 'Active',
      self::STATUS_INACTIVE => 'Inactive',
  ];

  // Days of Week
  public const DAYS = [
      0 => 'Sunday',
      1 => 'Monday',
      2 => 'Tuesday',
      3 => 'Wednesday',
      4 => 'Thursday',
      5 => 'Friday',
      6 => 'Saturday',
  ];
  ```
- [ ] Define `$fillable`, `$casts`
- [ ] Add relationships:
  - [ ] `belongsTo(Branch::class)`
  - [ ] `hasMany(AttendanceRule::class)`
  - [ ] `hasMany(Attendance::class)`
- [ ] Add scopes: `scopeActive()`, `scopeDefault()`, `scopeFixed()`, `scopeFlexible()`
- [ ] Add methods:
  - [ ] `isLateCheckIn($checkInTime): bool`
  - [ ] `isEarlyCheckOut($checkOutTime): bool`
  - [ ] `calculateLateMinutes($checkInTime): int`
  - [ ] `calculateEarlyMinutes($checkOutTime): int`
  - [ ] `isWorkingDay($dayOfWeek): bool`
  - [ ] `isWithinWorkingHours($time): bool`
  - [ ] `setAsDefault(): void`
  - [ ] `createDefaultRules(): void`
- [ ] Add accessors:
  - [ ] `getFormattedWorkingHoursAttribute(): string`
  - [ ] `getFormattedTimeRangeAttribute(): string`
  - [ ] `getWorkingDaysListAttribute(): string`

**Filament Resource** (`modules/Attendance/Filament/Resources/WorkingScheduleResource.php`)
- [ ] Use `ChecksResourcePermissions` trait
- [ ] Set `$moduleCode = 'attendance'`, `$permissionKey = 'working_schedules'`
- [ ] Create form with sections:
  - [ ] Basic Info: name, code, description, branch_id
  - [ ] Schedule Type: type (reactive select)
  - [ ] Fixed Schedule: start_time, end_time, hours_per_day (conditional)
  - [ ] Grace Periods: grace_period_late_minutes, grace_period_early_minutes
  - [ ] Flexible Schedule: flexible_start_from/to, min/max hours (conditional)
  - [ ] Core Hours: core_hours_required, core_hours_start/end
  - [ ] Working Days: CheckboxList (Sun-Sat)
  - [ ] Break Settings: has_break, break_duration, break_start/end
  - [ ] Overtime: allow_overtime, max_overtime_per_day/week
  - [ ] Remote/Hybrid: is_remote, is_hybrid, remote_days_per_week
  - [ ] Status: status, is_default
- [ ] Create table with columns: name, code, type (badge), time_range, working_days, is_active
- [ ] Add Relation Manager: `AttendanceRulesRelationManager`
- [ ] Add actions: Set Default, Duplicate
- [ ] Create pages: List, Create, Edit, View

---

### Phase A3: Attendance Rules & Violations (High Priority)

#### A3.1 AttendanceRule Model & Migration

**Migration**
- [ ] Create `YYYY_MM_DD_create_attendance_rules_table.php`
  - [ ] `uuid('id')->primary()`
  - [ ] `uuid('tenant_id')->index()`
  - [ ] `uuid('working_schedule_id')->nullable()->index()`
  - [ ] `string('name')`
  - [ ] `string('code')->index()`
  - [ ] `text('description')->nullable()`
  - [ ] `string('category')` (late_checkin, early_checkout, missed_checkin, missed_checkout, overstay, unauthorized_absence)
  - [ ] `boolean('is_active')->default(true)`
  - [ ] `integer('sequence')->default(0)`
  - [ ] `boolean('auto_apply')->default(false)`
  - [ ] `boolean('send_notification')->default(true)`
  - [ ] `boolean('notify_manager')->default(false)`
  - [ ] `boolean('notify_hr')->default(false)`
  - [ ] `text('notes')->nullable()`
  - [ ] `uuid('created_by')->nullable()`
  - [ ] `uuid('updated_by')->nullable()`
  - [ ] `timestamps()`
  - [ ] `softDeletes()`

**Model** (`modules/Attendance/Models/AttendanceRule.php`)
- [ ] Extend `BaseModel`
- [ ] Use traits: `SoftDeletes`
- [ ] Define constants:
  ```php
  // Rule Categories
  public const CATEGORY_LATE_CHECKIN = 'late_checkin';
  public const CATEGORY_EARLY_CHECKOUT = 'early_checkout';
  public const CATEGORY_MISSED_CHECKIN = 'missed_checkin';
  public const CATEGORY_MISSED_CHECKOUT = 'missed_checkout';
  public const CATEGORY_OVERSTAY = 'overstay';
  public const CATEGORY_UNAUTHORIZED_ABSENCE = 'unauthorized_absence';

  public const CATEGORIES = [
      self::CATEGORY_LATE_CHECKIN => 'Late Check-In',
      self::CATEGORY_EARLY_CHECKOUT => 'Early Check-Out',
      self::CATEGORY_MISSED_CHECKIN => 'Missed Check-In',
      self::CATEGORY_MISSED_CHECKOUT => 'Missed Check-Out',
      self::CATEGORY_OVERSTAY => 'Overstay',
      self::CATEGORY_UNAUTHORIZED_ABSENCE => 'Unauthorized Absence',
  ];
  ```
- [ ] Define `$fillable`, `$casts`
- [ ] Add relationships: `workingSchedule`, `actions`, `violations`
- [ ] Add scopes: `scopeActive()`, `scopeByCategory()`
- [ ] Add methods:
  - [ ] `appliesToStaff(StaffProfile $staff): bool`
  - [ ] `getApplicableAction($violationMinutes, $occurrenceCount): ?AttendanceRuleAction`

**Filament Resource** (`modules/Attendance/Filament/Resources/AttendanceRuleResource.php`)
- [ ] Form: name, code, category (select), working_schedule_id, auto_apply, notifications, is_active
- [ ] Table: code, name, category (badge), working_schedule, is_active
- [ ] Relation Manager: `ActionsRelationManager` for tiered actions

---

#### A3.2 AttendanceRuleAction Model & Migration

**Migration**
- [ ] Create `YYYY_MM_DD_create_attendance_rule_actions_table.php`
  - [ ] `uuid('id')->primary()`
  - [ ] `uuid('attendance_rule_id')->index()`
  - [ ] `integer('occurrence_number')->nullable()` (1st, 2nd, 3rd offense)
  - [ ] `string('action_type')` (deduction, warning, approval_required, notification, block_attendance)
  - [ ] `string('severity')->default('moderate')` (minor, moderate, severe)
  - [ ] **Threshold:**
    - [ ] `string('threshold_type')` (time_based, occurrence_based)
    - [ ] `integer('threshold_value')` (minutes or count)
    - [ ] `string('threshold_period')->nullable()` (day, week, month, year)
  - [ ] **Penalty:**
    - [ ] `string('penalty_type')->nullable()` (fixed, percentage, hourly_rate, formula)
    - [ ] `integer('penalty_amount_minor')->default(0)` (in minor units)
    - [ ] `decimal('penalty_percentage', 5, 2)->nullable()`
    - [ ] `text('penalty_formula')->nullable()`
  - [ ] **Workflow:**
    - [ ] `boolean('requires_approval')->default(false)`
    - [ ] `boolean('notification_enabled')->default(true)`
    - [ ] `boolean('notify_manager')->default(true)`
    - [ ] `boolean('notify_hr')->default(false)`
  - [ ] `text('message_template')->nullable()`
  - [ ] `text('notes')->nullable()`
  - [ ] `boolean('is_active')->default(true)`
  - [ ] `timestamps()`
  - [ ] Foreign key: `attendance_rule_id` → `attendance_rules.id` cascade

**Model** (`modules/Attendance/Models/AttendanceRuleAction.php`)
- [ ] Extend `BaseModel`
- [ ] Define constants:
  ```php
  // Action Types
  public const ACTION_DEDUCTION = 'deduction';
  public const ACTION_WARNING = 'warning';
  public const ACTION_APPROVAL_REQUIRED = 'approval_required';
  public const ACTION_NOTIFICATION = 'notification';
  public const ACTION_BLOCK = 'block_attendance';

  public const ACTION_TYPES = [
      self::ACTION_DEDUCTION => 'Salary Deduction',
      self::ACTION_WARNING => 'Warning Notice',
      self::ACTION_APPROVAL_REQUIRED => 'Require Approval',
      self::ACTION_NOTIFICATION => 'Send Notification',
      self::ACTION_BLOCK => 'Block Attendance',
  ];

  // Penalty Types
  public const PENALTY_FIXED = 'fixed';
  public const PENALTY_PERCENTAGE = 'percentage';
  public const PENALTY_HOURLY = 'hourly_rate';
  public const PENALTY_FORMULA = 'formula';

  public const PENALTY_TYPES = [
      self::PENALTY_FIXED => 'Fixed Amount',
      self::PENALTY_PERCENTAGE => 'Percentage of Daily Salary',
      self::PENALTY_HOURLY => 'Hourly Rate Deduction',
      self::PENALTY_FORMULA => 'Custom Formula',
  ];

  // Threshold Types
  public const THRESHOLD_TIME = 'time_based';
  public const THRESHOLD_OCCURRENCE = 'occurrence_based';

  public const THRESHOLD_TYPES = [
      self::THRESHOLD_TIME => 'Time Based (Minutes)',
      self::THRESHOLD_OCCURRENCE => 'Occurrence Based (Count)',
  ];

  // Severity
  public const SEVERITY_MINOR = 'minor';
  public const SEVERITY_MODERATE = 'moderate';
  public const SEVERITY_SEVERE = 'severe';

  public const SEVERITIES = [
      self::SEVERITY_MINOR => 'Minor',
      self::SEVERITY_MODERATE => 'Moderate',
      self::SEVERITY_SEVERE => 'Severe',
  ];
  ```
- [ ] Define `$fillable`, `$casts`
- [ ] Add relationships: `rule`, `violations`
- [ ] Add methods:
  - [ ] `calculatePenalty(StaffProfile $staff, int $violationMinutes): int`
  - [ ] `calculatePercentagePenalty(StaffProfile $staff): int`
  - [ ] `calculateHourlyPenalty(StaffProfile $staff, int $violationMinutes): int`
  - [ ] `evaluateFormula(StaffProfile $staff, int $violationMinutes): int`
- [ ] Add accessor: `getThresholdDescriptionAttribute(): string`

---

#### A3.3 AttendanceViolation Model & Migration

**Migration**
- [ ] Create `YYYY_MM_DD_create_attendance_violations_table.php`
  - [ ] `uuid('id')->primary()`
  - [ ] `uuid('tenant_id')->index()`
  - [ ] `uuid('attendance_id')->index()`
  - [ ] `uuid('staff_profile_id')->index()`
  - [ ] `uuid('attendance_rule_id')->nullable()->index()`
  - [ ] `uuid('attendance_rule_action_id')->nullable()`
  - [ ] `string('violation_type')` (from AttendanceRuleCategory enum)
  - [ ] `date('violation_date')`
  - [ ] `time('scheduled_time')->nullable()`
  - [ ] `time('actual_time')->nullable()`
  - [ ] `integer('grace_period_minutes')->default(0)`
  - [ ] `integer('violation_minutes')->default(0)`
  - [ ] `integer('penalty_amount_minor')->default(0)`
  - [ ] `string('penalty_type')->nullable()`
  - [ ] `json('penalty_calculation_details')->nullable()`
  - [ ] `string('status')->default('pending')` (pending, approved, waived, disputed, applied, cancelled)
  - [ ] `text('reason')->nullable()`
  - [ ] `text('employee_notes')->nullable()`
  - [ ] `text('manager_notes')->nullable()`
  - [ ] `uuid('approved_by')->nullable()`
  - [ ] `timestamp('approved_at')->nullable()`
  - [ ] `uuid('waived_by')->nullable()`
  - [ ] `text('waived_reason')->nullable()`
  - [ ] `timestamp('waived_at')->nullable()`
  - [ ] `text('dispute_reason')->nullable()`
  - [ ] `timestamp('disputed_at')->nullable()`
  - [ ] `uuid('payroll_line_id')->nullable()` (linked when applied to payroll)
  - [ ] `timestamp('applied_at')->nullable()`
  - [ ] `timestamps()`
  - [ ] `softDeletes()`

**Model** (`modules/Attendance/Models/AttendanceViolation.php`)
- [ ] Extend `BaseModel`
- [ ] Use traits: `SoftDeletes`
- [ ] Define constants:
  ```php
  // Status
  public const STATUS_PENDING = 'pending';
  public const STATUS_APPROVED = 'approved';
  public const STATUS_WAIVED = 'waived';
  public const STATUS_DISPUTED = 'disputed';
  public const STATUS_APPLIED = 'applied';
  public const STATUS_CANCELLED = 'cancelled';

  public const STATUSES = [
      self::STATUS_PENDING => 'Pending Review',
      self::STATUS_APPROVED => 'Approved',
      self::STATUS_WAIVED => 'Waived',
      self::STATUS_DISPUTED => 'Disputed',
      self::STATUS_APPLIED => 'Applied to Payslip',
      self::STATUS_CANCELLED => 'Cancelled',
  ];

  public const STATUS_COLORS = [
      self::STATUS_PENDING => 'warning',
      self::STATUS_APPROVED => 'success',
      self::STATUS_WAIVED => 'gray',
      self::STATUS_DISPUTED => 'danger',
      self::STATUS_APPLIED => 'info',
      self::STATUS_CANCELLED => 'gray',
  ];
  ```
- [ ] Define `$fillable`, `$casts`
- [ ] Add relationships: `attendance`, `staffProfile`, `rule`, `ruleAction`, `approvedBy`, `waivedBy`, `payrollLine`
- [ ] Add scopes: `scopePending()`, `scopeApproved()`, `scopeDateRange()`, `scopeByStaff()`
- [ ] Add methods:
  - [ ] `canBeWaived(): bool`
  - [ ] `canBeApproved(): bool`
  - [ ] `approve(User $approver, ?string $notes): bool`
  - [ ] `waive(User $waiver, string $reason): bool`
  - [ ] `dispute(string $reason): bool`
- [ ] Add accessor: `getFormattedViolationTimeAttribute(): string`
- [ ] Add accessor: `getPenaltyAmountAttribute(): float` (major units)

**Filament Resource** (`modules/Attendance/Filament/Resources/AttendanceViolationResource.php`)
- [ ] Form: readonly fields showing violation details
- [ ] Table: violation_date, staff name, violation_type (badge), violation_minutes, penalty_amount, status (badge)
- [ ] Filters: status, violation_type, date range, staff
- [ ] Actions: Approve, Waive (with reason modal), Dispute
- [ ] Bulk actions: Bulk Approve, Bulk Waive

---

### Phase A4: Services (High Priority)

#### A4.1 AttendanceService

**Service** (`modules/Attendance/Services/AttendanceService.php`)
- [ ] Create service class
- [ ] Register in `AttendanceServiceProvider` as singleton
- [ ] Implement methods:
  - [ ] `checkIn(StaffProfile $staff, array $locationData): Attendance`
  - [ ] `checkOut(StaffProfile $staff, array $locationData): Attendance`
  - [ ] `startBreak(Attendance $attendance): AttendanceBreak`
  - [ ] `endBreak(AttendanceBreak $break): AttendanceBreak`
  - [ ] `createManual(StaffProfile $staff, array $data): Attendance`
  - [ ] `getTodayAttendance(StaffProfile $staff): ?Attendance`
  - [ ] `getStatus(StaffProfile $staff): array`
  - [ ] `calculateWorkingHours(Attendance $attendance): float`
  - [ ] `isCheckedIn(StaffProfile $staff): bool`

#### A4.2 AttendanceRuleService

**Service** (`modules/Attendance/Services/AttendanceRuleService.php`)
- [ ] Create service class
- [ ] Inject `FormulaEvaluator` from Payroll module
- [ ] Implement methods:
  - [ ] `evaluateLateCheckIn(Attendance $attendance, $checkInTime, $scheduledTime): ?AttendanceViolation`
  - [ ] `evaluateEarlyCheckOut(Attendance $attendance, $checkOutTime, $scheduledTime): ?AttendanceViolation`
  - [ ] `evaluateMissedCheckIn(StaffProfile $staff, $date): ?AttendanceViolation`
  - [ ] `evaluateMissedCheckOut(Attendance $attendance): ?AttendanceViolation`
  - [ ] `findApplicableRule(StaffProfile $staff, $category): ?AttendanceRule`
  - [ ] `getStaffViolationStats(StaffProfile $staff, $startDate, $endDate): array`
  - [ ] `getPendingViolations(?int $managerId): Collection`
  - [ ] `bulkApproveViolations(array $violationIds, User $approver, ?string $notes): int`
  - [ ] `waiveViolation(int $violationId, User $waiver, string $reason): bool`

#### A4.3 WorkingScheduleService

**Service** (`modules/Attendance/Services/WorkingScheduleService.php`)
- [ ] Implement methods:
  - [ ] `createWithDefaultRules(array $data): WorkingSchedule`
  - [ ] `updateSchedule(WorkingSchedule $schedule, array $data): WorkingSchedule`
  - [ ] `updateScheduleRules(WorkingSchedule $schedule, array $rulesData): WorkingSchedule`
  - [ ] `canDeleteSchedule(WorkingSchedule $schedule): bool`
  - [ ] `deleteSchedule(WorkingSchedule $schedule): bool`
  - [ ] `duplicateSchedule(WorkingSchedule $schedule, string $name, string $code): WorkingSchedule`

---

### Phase A5: Filament Resources & Pages (Medium Priority)

#### A5.1 AttendanceResource

**Filament Resource** (`modules/Attendance/Filament/Resources/AttendanceResource.php`)
- [ ] Use `ChecksResourcePermissions` trait
- [ ] Set `$moduleCode = 'attendance'`, `$permissionKey = 'attendances'`
- [ ] Create form:
  - [ ] staff_profile_id (select with search)
  - [ ] attendance_date
  - [ ] check_in_time, check_out_time
  - [ ] attendance_type (select)
  - [ ] status (select)
  - [ ] notes
- [ ] Create table:
  - [ ] attendance_date (sortable)
  - [ ] staff name (searchable)
  - [ ] check_in_time, check_out_time
  - [ ] working_hours
  - [ ] status (badge)
  - [ ] violations_count
- [ ] Filters: date range, staff, status, branch
- [ ] Actions: View, Edit, Delete, Create Manual
- [ ] Relation Manager: `LogsRelationManager`, `ViolationsRelationManager`

#### A5.2 Dashboard Widget

**Widget** (`modules/Attendance/Filament/Widgets/TodayAttendanceWidget.php`)
- [ ] Show today's attendance summary
- [ ] Present, Absent, Late, On Leave counts
- [ ] Quick check-in/out action for current user

#### A5.3 Attendance Reports Page

**Page** (`modules/Attendance/Filament/Pages/AttendanceReportsPage.php`)
- [ ] Report types: Daily, Weekly, Monthly, Custom Range
- [ ] Filters: Branch, Department, Staff
- [ ] Export to Excel (using `maatwebsite/excel`)
- [ ] Charts: Attendance trends, Violation statistics

---

### Phase A6: API & Mobile Support (Medium Priority)

> **Note:** API endpoints should be added to the central `modules/Api/` module following existing patterns (e.g., `BookingController`, `PatientController`).

#### A6.1 API Controllers

**Controller** (`modules/Api/Http/Controllers/AttendanceController.php`)
- [ ] Create `AttendanceController extends BaseApiController`
  - [ ] `checkIn(Request $request)` - Mobile check-in with GPS
  - [ ] `checkOut(Request $request)` - Mobile check-out with GPS
  - [ ] `startBreak(Request $request)` - Start break
  - [ ] `endBreak(Request $request)` - End break
  - [ ] `getStatus()` - Current attendance status
  - [ ] `getHistory(Request $request)` - Attendance history
  - [ ] `getSchedule()` - Get staff working schedule

**API Resource** (`modules/Api/Http/Resources/AttendanceResource.php`)
- [ ] Create resource with: id, staff, attendance_date, check_in_time, check_out_time, working_hours, status, violations_count

**API Routes** (add to `modules/Api/Routes/api.php`)
- [ ] `POST /attendance/check-in`
- [ ] `POST /attendance/check-out`
- [ ] `POST /attendance/break/start`
- [ ] `POST /attendance/break/end`
- [ ] `GET /attendance/status`
- [ ] `GET /attendance/history`
- [ ] `GET /attendance/schedule`

---

### Phase A7: Payroll Integration (High Priority)

#### A7.1 Update PayrollCalculationService

**Updates** (`modules/Payroll/Services/PayrollCalculationService.php`)
- [ ] Add method: `getAttendanceData(StaffProfile $staff, $startDate, $endDate): array`
  - [ ] Return: worked_days, absent_days, late_days, late_minutes, early_minutes, overtime_hours
- [ ] Update `buildCalculationContext()` to include attendance data
- [ ] Add method: `getViolationDeductions(StaffProfile $staff, $startDate, $endDate): int`
  - [ ] Sum approved violation penalties not yet applied
- [ ] Update payroll calculation to auto-apply violations

#### A7.2 Link Violations to PayrollLine

- [ ] Update `PayrollLine` to track applied violations
- [ ] Create relation: `PayrollLine->hasMany(AttendanceViolation::class, 'payroll_line_id')`
- [ ] Mark violations as `APPLIED` when payroll is paid

---

### Phase A8: Language Files (Required)

**English** (`modules/Attendance/Lang/en/attendance.php`)
- [ ] Module info: `module_name`, `module_description`
- [ ] Navigation labels
- [ ] Model labels: attendance, working_schedule, violation, rule
- [ ] Field labels for all models
- [ ] Status labels
- [ ] Violation type labels
- [ ] Penalty type labels
- [ ] Action labels
- [ ] Help texts

**Arabic** (`modules/Attendance/Lang/ar/attendance.php`)
- [ ] Translate all strings

---

### Phase A9: Module Structure

**Module Files**
- [ ] `modules/Attendance/module.json`
- [ ] `modules/Attendance/Config/config.php`
- [ ] `modules/Attendance/Providers/AttendanceServiceProvider.php`
- [ ] `modules/Attendance/Providers/FilamentServiceProvider.php`
- [ ] Register module in `modules_statuses.json`

---

### Attendance Module Testing Checklist

- [ ] Test manual attendance CRUD
- [ ] Test working schedule CRUD with all types (fixed, flexible, shift)
- [ ] Test attendance rule creation with actions
- [ ] Test late check-in violation auto-creation
- [ ] Test early check-out violation auto-creation
- [ ] Test violation approval workflow
- [ ] Test violation waive workflow
- [ ] Test violation dispute workflow
- [ ] Test penalty calculation (fixed, percentage, hourly, formula)
- [ ] Test payroll integration - violation deductions
- [ ] Test API check-in/out with GPS
- [ ] Test attendance reports and export
- [ ] Test multi-tenant isolation
- [ ] Test Arabic translations display correctly
