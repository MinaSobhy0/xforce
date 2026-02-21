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
