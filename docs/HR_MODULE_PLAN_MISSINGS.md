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
- id (auto-increment BIGINT), tenant_id
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
- id (auto-increment BIGINT), tenant_id
- name, code, description
- type: earning | deduction | allowance | benefit | gross | net
- is_active
- created_at, updated_at
```

**SalaryStructure** (`modules/Payroll/Models/SalaryStructure.php`)
```
Fields:
- id (auto-increment BIGINT), tenant_id
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
- id (auto-increment BIGINT), tenant_id
- staff_profile_id (FK)
- salary_structure_id (FK)
- base_salary_minor
- effective_date, end_date
- is_current (only one per employee)
- assigned_by (FK)
- notes
- created_at, updated_at
```

**EmployeeSalaryComponent** (`modules/Payroll/Models/EmployeeSalaryComponent.php`)
```
Fields:
- id (auto-increment BIGINT), tenant_id
- staff_profile_id (FK)
- salary_rule_id (FK, nullable)
- component_type: earning | deduction
- calculation_type: fixed | percentage | formula
- amount_minor, percentage, formula
- effective_date, end_date
- is_taxable, is_active
- loan_id (FK, nullable, for loan repayment deductions)
- created_by (FK)
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
- id (auto-increment BIGINT), tenant_id
- staff_profile_id (FK)
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
- id (auto-increment BIGINT), attendance_id (FK)
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
- id (auto-increment BIGINT), tenant_id
- staff_profile_id (FK)
- loan_number
- loan_type: personal | advance | housing
- principal_amount_minor
- interest_rate
- total_amount_minor
- monthly_deduction_minor
- remaining_balance_minor
- start_date, end_date
- status: active | completed | cancelled
- approved_by (FK), approved_at
- notes
- created_at, updated_at
```

**LoanRepayment** (`modules/Payroll/Models/LoanRepayment.php`)
```
Fields:
- id (auto-increment BIGINT), tenant_id
- employee_loan_id (FK)
- payroll_line_id (FK)
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
  - [x] `$table->id()`
  - [x] `unsignedBigInteger('tenant_id')->index()`
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
- [x] Define `$casts`
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
  - [x] `$table->id()`
  - [x] `unsignedBigInteger('tenant_id')->index()`
  - [x] `string('name')`
  - [x] `string('code')->index()`
  - [x] `foreignId('category_id')->constrained('salary_rule_categories')`
  - [x] `string('amount_type')` (fixed, percentage, formula)
  - [x] `integer('amount_fixed_minor')->default(0)`
  - [x] `decimal('amount_percentage', 8, 4)->nullable()`
  - [x] `text('amount_formula')->nullable()`
  - [x] `string('condition_type')->nullable()`
  - [x] `text('condition_formula')->nullable()`
  - [x] `foreignId('percentage_base_id')->nullable()->constrained('salary_rules')` (self-reference)
  - [x] `string('field_mapping')->nullable()`
  - [x] `integer('sequence')->default(0)`
  - [x] `boolean('is_active')->default(true)`
  - [x] `timestamps()`
  - [x] `softDeletes()`
  - [x] Foreign keys with cascade/nullOnDelete

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
  - [x] `$table->id()`
  - [x] `unsignedBigInteger('tenant_id')->index()`
  - [x] `string('name')`
  - [x] `string('code')->index()`
  - [x] `text('description')->nullable()`
  - [x] `string('pay_frequency')->default('monthly')`
  - [x] `string('currency')->default('EGP')`
  - [x] `boolean('is_active')->default(true)`
  - [x] `foreignId('created_by')->nullable()->constrained('users')`
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
  - [x] `foreignId('salary_structure_id')->constrained()->cascadeOnDelete()`
  - [x] `foreignId('salary_rule_id')->constrained()->cascadeOnDelete()`
  - [x] `integer('sequence')->default(0)`
  - [x] `timestamps()`
  - [x] Primary key on both columns

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
  - [x] `$table->id()`
  - [x] `unsignedBigInteger('tenant_id')->index()`
  - [x] `foreignId('staff_profile_id')->constrained()`
  - [x] `foreignId('salary_structure_id')->constrained()`
  - [x] `integer('base_salary_minor')->default(0)`
  - [x] `date('effective_date')`
  - [x] `date('end_date')->nullable()`
  - [x] `boolean('is_current')->default(false)`
  - [x] `foreignId('assigned_by')->nullable()->constrained('users')`
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
  - [x] `$table->id()`
  - [x] `unsignedBigInteger('tenant_id')->index()`
  - [x] `foreignId('staff_profile_id')->constrained()`
  - [x] `foreignId('salary_rule_id')->nullable()->constrained()`
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
  - [x] `foreignId('loan_id')->nullable()->constrained('employee_loans')`
  - [x] `foreignId('created_by')->nullable()->constrained('users')`
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
  - [ ] `$table->id()`
  - [ ] `foreignId('staff_profile_id')->constrained()`
  - [ ] `string('change_type')` (increment, promotion, adjustment, new_hire, transfer)
  - [ ] `foreignId('previous_structure_id')->nullable()->constrained('salary_structures')`
  - [ ] `foreignId('new_structure_id')->nullable()->constrained('salary_structures')`
  - [ ] `integer('previous_base_salary_minor')->default(0)`
  - [ ] `integer('new_base_salary_minor')->default(0)`
  - [ ] `decimal('change_percentage', 5, 2)->nullable()`
  - [ ] `date('effective_date')`
  - [ ] `text('reason')->nullable()`
  - [ ] `text('notes')->nullable()`
  - [ ] `string('status')->default('pending')`
  - [ ] `foreignId('requested_by')->nullable()->constrained('users')`
  - [ ] `foreignId('approved_by')->nullable()->constrained('users')`
  - [ ] `foreignId('applied_by')->nullable()->constrained('users')`
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

### Phase A1: Core Attendance System (High Priority) ✅ COMPLETED

#### A1.1 Module Structure ✅

**Create Module Structure** (`modules/Attendance/`) - COMPLETED
- [x] Config/config.php
- [x] Database/Migrations/
- [x] Filament/Resources/
- [x] Lang/en/attendance.php
- [x] Lang/ar/attendance.php
- [x] Models/
- [x] Providers/AttendanceServiceProvider.php
- [x] Providers/RouteServiceProvider.php
- [x] Routes/api.php, web.php
- [x] Services/
- [x] module.json (comprehensive with permissions, navigation, settings, events)

---

#### A1.2 Attendance Model & Migration ✅

**Migration** - COMPLETED
- [x] `2024_01_01_000001_create_attendances_table.php` with all fields

**Model** (`modules/Attendance/Models/Attendance.php`) - COMPLETED
- [x] Extends BaseModel, uses SoftDeletes
- [x] Constants: TYPE_*, STATUS_*, with TYPES, STATUSES, TYPE_COLORS, STATUS_COLORS
- [x] All relationships: staffProfile, branch, workingSchedule, logs, breaks, violations
- [x] Scopes: scopeForDate, scopeForPeriod, scopeForStaff, scopeToday, scopeThisMonth
- [x] Methods: isCheckedOut, isCheckedIn, latestLog, calculateWorkingHours, checkOut, approve

---

#### A1.3 AttendanceLog Model & Migration ✅

**Migration** - COMPLETED
- [x] `2024_01_01_000002_create_attendance_logs_table.php` with GPS fields

**Model** (`modules/Attendance/Models/AttendanceLog.php`) - COMPLETED
- [x] Constants: TYPE_CHECK_IN, TYPE_CHECK_OUT, TYPE_BREAK_START, TYPE_BREAK_END
- [x] Constants: SOURCE_MANUAL, SOURCE_MOBILE, SOURCE_BIOMETRIC, SOURCE_WEB
- [x] Accessors: getFormattedLocationAttribute, getGoogleMapsUrlAttribute

---

#### A1.4 AttendanceBreak Model & Migration ✅

**Migration** - COMPLETED
- [x] `2024_01_01_000003_create_attendance_breaks_table.php`

**Model** (`modules/Attendance/Models/AttendanceBreak.php`) - COMPLETED
- [x] Methods: calculateDuration, endBreak, isOngoing, isCompleted
- [x] Accessors: getFormattedDurationAttribute, getTimeRangeAttribute
- [x] Auto-calculate duration on save

---

### Phase A2: Working Schedules (High Priority) ✅ COMPLETED

#### A2.1 WorkingSchedule Model & Migration ✅

**Migration** - COMPLETED
- [x] `2024_01_01_000004_create_working_schedules_table.php` with 40+ fields:
  - [x] Fixed schedule: start_time, end_time, hours_per_day, hours_per_week
  - [x] Grace periods: grace_period_late_minutes, grace_period_early_minutes
  - [x] Flexible schedule: flexible_start_from/to, flexible_end_from/to, min/max hours
  - [x] Core hours: core_hours_required, core_hours_start, core_hours_end
  - [x] Working days: JSON array, days_per_week
  - [x] Break settings: has_break, break_duration_minutes, break_start/end, flexible_break
  - [x] Overtime: allow_overtime, max_overtime_per_day, max_overtime_per_week
  - [x] Shift rotation: is_rotating_shift, rotation_cycle_days, shift_pattern
  - [x] Remote/Hybrid: is_remote, is_hybrid, remote_days_per_week, remote_days

**Model** (`modules/Attendance/Models/WorkingSchedule.php`) - COMPLETED
- [x] Constants: TYPE_FIXED, TYPE_FLEXIBLE, TYPE_SHIFT, TYPE_COMPRESSED, TYPE_REMOTE
- [x] Constants: STATUS_ACTIVE, STATUS_INACTIVE with colors
- [x] Constants: DAY_SUNDAY through DAY_SATURDAY
- [x] All relationships: branch, attendances, rules, createdBy, updatedBy
- [x] Scopes: scopeActive, scopeInactive, scopeDefault, scopeFixed, scopeFlexible, scopeShift
- [x] Methods: isLateCheckIn, isEarlyCheckOut, calculateLateMinutes, calculateEarlyMinutes
- [x] Methods: isWorkingDay, isWorkingDate, isWithinWorkingHours, isWithinCoreHours
- [x] Methods: setAsDefault, calculateExpectedHours, countWorkingDays, duplicate
- [x] Accessors: getFormattedWorkingHoursAttribute, getFormattedTimeRangeAttribute
- [x] Accessors: getWorkingDaysListAttribute, getTypeLabelAttribute, getBreakDescriptionAttribute

**Filament Resource** (`modules/Attendance/Filament/Resources/WorkingScheduleResource.php`) - COMPLETED
- [x] ChecksResourcePermissions trait, moduleCode, permissionKey
- [x] Form with all sections: Basic Info, Fixed Schedule, Grace Periods, Flexible Schedule
- [x] Form sections: Core Hours, Working Days (CheckboxList), Break Settings, Overtime, Status
- [x] Table with columns: code, name, type (badge), time_range, working_days, is_default, status
- [x] Filters: type, status, branch_id, is_default
- [x] Actions: Set Default, Duplicate (with form)
- [x] RulesRelationManager for attendance rules
- [x] Pages: ListWorkingSchedules, CreateWorkingSchedule, ViewWorkingSchedule, EditWorkingSchedule

---

### Phase A3: Attendance Rules & Violations (High Priority) ✅ COMPLETED

#### A3.1 AttendanceRule Model & Migration ✅

**Migration** - COMPLETED
- [x] `2024_01_01_000005_create_attendance_rules_table.php` with all fields:
  - [x] UUID primary key, tenant_id
  - [x] working_schedule_id (nullable)
  - [x] name, code, description
  - [x] category (late_checkin, early_checkout, missed_checkin, missed_checkout, overstay, unauthorized_absence)
  - [x] is_active, sequence, auto_apply
  - [x] Notification flags: send_notification, notify_manager, notify_hr
  - [x] notes, created_by, updated_by
  - [x] timestamps, softDeletes

**Model** (`modules/Attendance/Models/AttendanceRule.php`) - COMPLETED
- [x] Extends BaseModel, uses SoftDeletes
- [x] Constants: CATEGORY_LATE_CHECKIN, CATEGORY_EARLY_CHECKOUT, etc.
- [x] CATEGORIES and CATEGORY_COLORS arrays
- [x] All relationships: workingSchedule, actions, violations, createdBy, updatedBy
- [x] Scopes: scopeActive, scopeByCategory, scopeGlobal
- [x] Methods: appliesToStaff, getApplicableAction

**Filament Resource** (`modules/Attendance/Filament/Resources/AttendanceRuleResource.php`) - COMPLETED
- [x] ChecksResourcePermissions trait
- [x] Form: name, code, category (select), working_schedule_id, auto_apply, notifications, is_active
- [x] Table: code, name, category (badge), working_schedule, is_active
- [x] Relation Manager: ActionsRelationManager for tiered actions
- [x] Pages: List, Create, View, Edit

---

#### A3.2 AttendanceRuleAction Model & Migration ✅

**Migration** - COMPLETED
- [x] `2024_01_01_000006_create_attendance_rule_actions_table.php` with all fields:
  - [x] UUID primary key, attendance_rule_id
  - [x] occurrence_number (1st, 2nd, 3rd offense)
  - [x] action_type (deduction, warning, approval_required, notification, block_attendance)
  - [x] severity (minor, moderate, severe)
  - [x] Threshold: threshold_type, threshold_value, threshold_period
  - [x] Penalty: penalty_type, penalty_amount_minor, penalty_percentage, penalty_formula
  - [x] Workflow: requires_approval, notification_enabled, notify_manager, notify_hr
  - [x] message_template, notes, is_active
  - [x] timestamps, foreign keys with cascade

**Model** (`modules/Attendance/Models/AttendanceRuleAction.php`) - COMPLETED
- [x] Extends BaseModel
- [x] Constants: ACTION_DEDUCTION, ACTION_WARNING, ACTION_APPROVAL_REQUIRED, ACTION_NOTIFICATION, ACTION_BLOCK
- [x] Constants: PENALTY_FIXED, PENALTY_PERCENTAGE, PENALTY_HOURLY, PENALTY_FORMULA
- [x] Constants: THRESHOLD_TIME, THRESHOLD_OCCURRENCE
- [x] Constants: SEVERITY_MINOR, SEVERITY_MODERATE, SEVERITY_SEVERE
- [x] All TYPES, COLORS arrays
- [x] Relationships: rule, violations
- [x] Methods: calculatePenalty, calculatePercentagePenalty, calculateHourlyPenalty, evaluateFormula
- [x] Accessor: getThresholdDescriptionAttribute

---

#### A3.3 AttendanceViolation Model & Migration ✅

**Migration** - COMPLETED
- [x] `2024_01_01_000007_create_attendance_violations_table.php` with all fields:
  - [x] UUID primary key, tenant_id
  - [x] attendance_id, staff_profile_id, attendance_rule_id, attendance_rule_action_id
  - [x] violation_type, violation_date, scheduled_time, actual_time
  - [x] grace_period_minutes, violation_minutes
  - [x] penalty_amount_minor, penalty_type, penalty_calculation_details (JSON)
  - [x] status (pending, approved, waived, disputed, applied, cancelled)
  - [x] reason, employee_notes, manager_notes
  - [x] Approval workflow: approved_by, approved_at
  - [x] Waiver workflow: waived_by, waived_reason, waived_at
  - [x] Dispute workflow: dispute_reason, disputed_at
  - [x] Payroll link: payroll_line_id, applied_at
  - [x] timestamps, softDeletes

**Model** (`modules/Attendance/Models/AttendanceViolation.php`) - COMPLETED
- [x] Extends BaseModel, uses SoftDeletes
- [x] Constants: STATUS_PENDING, STATUS_APPROVED, STATUS_WAIVED, STATUS_DISPUTED, STATUS_APPLIED, STATUS_CANCELLED
- [x] STATUSES and STATUS_COLORS arrays
- [x] All relationships: attendance, staffProfile, rule, ruleAction, approvedBy, waivedBy, payrollLine
- [x] Scopes: scopePending, scopeApproved, scopeApplied, scopeNotApplied, scopeDateRange, scopeByStaff
- [x] Methods: canBeWaived, canBeApproved, approve, waive, dispute, markAsApplied
- [x] Accessors: getFormattedViolationTimeAttribute, getPenaltyAmountAttribute

**Filament Resource** (`modules/Attendance/Filament/Resources/AttendanceViolationResource.php`) - COMPLETED
- [x] ChecksResourcePermissions trait
- [x] Infolist: readonly fields showing violation details
- [x] Table: violation_date, staff name, violation_type (badge), violation_minutes, penalty_amount, status (badge)
- [x] Filters: status, violation_type, date range, staff
- [x] Actions: Approve, Waive (with reason modal)
- [x] Bulk actions: Bulk Approve
- [x] Pages: ListAttendanceViolations, ViewAttendanceViolation

---

### Phase A4: Services (High Priority) ✅ COMPLETED

#### A4.1 AttendanceService ✅

**Service** (`modules/Attendance/Services/AttendanceService.php`) - COMPLETED
- [x] Service class created
- [x] Registered in `AttendanceServiceProvider` as singleton
- [x] Injects AttendanceRuleService dependency
- [x] Implemented methods:
  - [x] `checkIn(StaffProfile $staff, array $locationData): Attendance`
  - [x] `checkOut(Attendance $attendance, array $locationData): Attendance`
  - [x] `startBreak(Attendance $attendance, ?string $type): AttendanceBreak`
  - [x] `endBreak(AttendanceBreak $break): AttendanceBreak`
  - [x] `createManualAttendance(array $data): Attendance`
  - [x] `getTodayAttendance(StaffProfile $staff): ?Attendance`
  - [x] `getStatus(StaffProfile $staff): array`
  - [x] `calculateWorkingHours(Attendance $attendance): float`
  - [x] `isCheckedIn(StaffProfile $staff): bool`
  - [x] Helper: createAttendanceLog, validateGpsLocation, isWithinGeofence

#### A4.2 AttendanceRuleService ✅

**Service** (`modules/Attendance/Services/AttendanceRuleService.php`) - COMPLETED
- [x] Service class created
- [x] Registered in `AttendanceServiceProvider` as singleton
- [x] Implemented methods:
  - [x] `evaluateLateCheckIn(Attendance $attendance): ?AttendanceViolation`
  - [x] `evaluateEarlyCheckOut(Attendance $attendance): ?AttendanceViolation`
  - [x] `evaluateMissedCheckIn(StaffProfile $staff, Carbon $date): ?AttendanceViolation`
  - [x] `evaluateMissedCheckOut(Attendance $attendance): ?AttendanceViolation`
  - [x] `findApplicableRule(StaffProfile $staff, string $category): ?AttendanceRule`
  - [x] `createViolation(Attendance $attendance, AttendanceRule $rule, AttendanceRuleAction $action, array $data): AttendanceViolation`
  - [x] `getMonthlyOccurrenceCount(StaffProfile $staff, string $category): int`
  - [x] `bulkApproveViolations(array $violationIds, User $approver): int`
  - [x] `bulkWaiveViolations(array $violationIds, User $waiver, string $reason): int`

#### A4.3 WorkingScheduleService ✅

**Service** (`modules/Attendance/Services/WorkingScheduleService.php`) - COMPLETED
- [x] Service class created
- [x] Registered in `AttendanceServiceProvider` as singleton
- [x] Implemented methods:
  - [x] `createWithDefaultRules(array $data): WorkingSchedule`
  - [x] `duplicateSchedule(WorkingSchedule $schedule, string $name, string $code): WorkingSchedule`
  - [x] `createDefaultRules(WorkingSchedule $schedule): void`
  - [x] `createDefaultRuleAction(AttendanceRule $rule, int $sequence, array $data): AttendanceRuleAction`
  - [x] Creates default rules for: late_checkin, early_checkout, missed_checkin, unauthorized_absence

---

### Phase A5: Filament Resources & Pages (Medium Priority) ✅ COMPLETED

#### A5.1 AttendanceResource ✅

**Filament Resource** (`modules/Attendance/Filament/Resources/AttendanceResource.php`) - COMPLETED
- [x] ChecksResourcePermissions trait
- [x] moduleCode = 'attendance', permissionKey = 'attendances'
- [x] Form with sections:
  - [x] Staff & Date: staff_profile_id, branch_id, working_schedule_id, attendance_date
  - [x] Times: check_in_time, check_out_time, expected_start_time, expected_end_time
  - [x] Hours: working_hours, overtime_hours, break_duration_minutes
  - [x] Type & Status: attendance_type, status, source
  - [x] GPS: check_in_latitude, check_in_longitude, check_out_latitude, check_out_longitude
  - [x] Notes & Approval: notes, approved_by, approved_at
- [x] Table columns:
  - [x] attendance_date (sortable, date format)
  - [x] staffProfile.user.name (searchable)
  - [x] check_in_time, check_out_time
  - [x] working_hours (suffix 'hrs')
  - [x] attendance_type (badge with colors)
  - [x] status (badge with colors)
  - [x] violations_count
- [x] Filters: attendance_date range, staff_profile_id, status, attendance_type, branch_id
- [x] Actions: View, Edit, Delete
- [x] Relation Managers: LogsRelationManager, BreaksRelationManager, ViolationsRelationManager
- [x] Pages: ListAttendances, CreateAttendance, ViewAttendance, EditAttendance

#### A5.2 FilamentServiceProvider ✅

**Provider** (`modules/Attendance/Providers/FilamentServiceProvider.php`) - COMPLETED
- [x] getResources(): AttendanceResource, WorkingScheduleResource, AttendanceRuleResource, AttendanceViolationResource
- [x] getPages(): empty (no custom pages yet)
- [x] getWidgets(): empty (pending - Phase A5.3)

#### A5.3 Dashboard Widget ✅

**Widget** (`modules/Attendance/Filament/Widgets/TodayAttendanceWidget.php`) - COMPLETED
- [x] Show today's attendance summary (Present/Total Staff)
- [x] Present, Absent, Late, On Leave counts with badges
- [x] Pending violations counter
- [x] Weekly trend chart
- [x] Average working hours this week
- [x] Comparison vs last week
- [x] Registered in FilamentServiceProvider

#### A5.4 Attendance Reports Page ✅

**Page** (`modules/Attendance/Filament/Pages/AttendanceReportsPage.php`) - COMPLETED
- [x] Report types: Daily, Weekly, Monthly, Custom Range
- [x] Filters: Branch, Staff
- [x] Export to CSV
- [x] Charts: Attendance trends (stacked bar chart)
- [x] Statistics cards: Total records, Present, Absent, Late, Attendance Rate, Violations
- [x] Secondary stats: Working hours, Overtime, Late minutes

---

### Phase A6: API & Mobile Support (Medium Priority) ✅ COMPLETED

> **Note:** API endpoints added to the central `modules/Api/` module following existing patterns.

#### A6.1 API Controllers ✅

**Controller** (`modules/Api/Http/Controllers/AttendanceController.php`) - COMPLETED
- [x] `AttendanceController extends BaseApiController`
  - [x] `status()` - Current attendance status with schedule info
  - [x] `checkIn(Request $request)` - Mobile check-in with GPS validation
  - [x] `checkOut(Request $request)` - Mobile check-out with GPS
  - [x] `startBreak(Request $request)` - Start break with type
  - [x] `endBreak(Request $request)` - End active break
  - [x] `history(Request $request)` - Paginated attendance history
  - [x] `schedule()` - Get staff working schedule
  - [x] `violations(Request $request)` - Get staff violations with filters
  - [x] `disputeViolation(Request $request, string $violationId)` - Dispute a pending violation
  - [x] `monthlySummary(Request $request)` - Monthly attendance summary
  - [x] Helper: `getStaffProfile()`, `formatSchedule()`

**API Routes** (added to `modules/Api/Routes/api.php`) - COMPLETED
- [x] `GET /attendance/status`
- [x] `GET /attendance/schedule`
- [x] `GET /attendance/history`
- [x] `GET /attendance/summary`
- [x] `POST /attendance/check-in`
- [x] `POST /attendance/check-out`
- [x] `POST /attendance/break/start`
- [x] `POST /attendance/break/end`
- [x] `GET /attendance/violations`
- [x] `POST /attendance/violations/{violation}/dispute`

**Language Files** - COMPLETED
- [x] Added attendance translations to `modules/Api/Lang/en/api.php`
- [x] Added attendance translations to `modules/Api/Lang/ar/api.php`

---

### Phase A7: Payroll Integration (High Priority) ✅ COMPLETED

#### A7.1 Update PayrollCalculationService ✅

**Updates** (`modules/Payroll/Services/PayrollCalculationService.php`) - COMPLETED
- [x] Updated `getAttendanceData()` method to fetch real data from Attendance module
  - [x] Returns: worked_days, overtime_hours, late_minutes, early_minutes, absence_days, late_days, half_days
  - [x] Uses Attendance::class and AttendanceViolation::class
  - [x] Graceful fallback if Attendance module not available
- [x] Added `getViolationDeductions()` method
  - [x] Sums approved violation penalties not yet applied
  - [x] Returns: total_minor, count, violations collection
- [x] Updated `buildCalculationContext()` to include:
  - [x] violation_deduction, violation_deduction_minor, violations_count
  - [x] early_minutes, late_days, half_days
- [x] Updated `calculateEmployeePayslip()` to:
  - [x] Add violation deductions to total deductions
  - [x] Track violations in calculation_details
  - [x] Store pending violations reference on PayrollLine
- [x] Added `applyViolationsToPayrollLine()` method
  - [x] Links violations to payroll line
  - [x] Marks violations as APPLIED with timestamp

#### A7.2 Link Violations to PayrollLine ✅

- [x] AttendanceViolation already has `payroll_line_id` column
- [x] AttendanceViolation has `STATUS_APPLIED` constant
- [x] Added `applyViolationsToPayrollLine()` to mark violations when payroll is paid

---

### Phase A8: Language Files (Required) ✅ COMPLETED

**English** (`modules/Attendance/Lang/en/attendance.php`) - COMPLETED
- [x] Module info: module_name, module_description
- [x] Navigation labels for all resources
- [x] Model labels: attendance, working_schedule, violation, rule
- [x] Field labels for all models
- [x] Status labels with colors
- [x] Violation type labels
- [x] Penalty type labels
- [x] Action labels
- [x] Help texts
- [x] Attendance types, sources, schedule types
- [x] Working schedule fields (fixed, flexible, break, overtime)

**Arabic** (`modules/Attendance/Lang/ar/attendance.php`) - COMPLETED
- [x] Full translation of all strings

---

### Phase A9: Module Structure ✅ COMPLETED

**Module Files** - COMPLETED
- [x] `modules/Attendance/module.json` - Full config with permissions, navigation, settings, events
- [x] `modules/Attendance/Config/config.php` - Module configuration
- [x] `modules/Attendance/Providers/AttendanceServiceProvider.php` - Service bindings
- [x] `modules/Attendance/Providers/RouteServiceProvider.php` - Route loading
- [x] `modules/Attendance/Providers/FilamentServiceProvider.php` - Filament resources registration
- [x] `modules/Attendance/Routes/api.php` - API routes placeholder
- [x] `modules/Attendance/Routes/web.php` - Web routes placeholder
- [x] Module auto-discovered by nWidart/laravel-modules

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
