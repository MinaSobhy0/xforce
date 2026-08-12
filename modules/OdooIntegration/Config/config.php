<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Odoo Integration Settings
    |--------------------------------------------------------------------------
    */

    // Default API protocol (xmlrpc or rest)
    'default_protocol' => env('ODOO_DEFAULT_PROTOCOL', 'xmlrpc'),

    // Default connection timeout in seconds
    'default_timeout' => env('ODOO_DEFAULT_TIMEOUT', 30),

    // Default rate limit per minute
    'default_rate_limit' => env('ODOO_RATE_LIMIT', 60),

    // Enable debug logging
    'debug' => env('ODOO_DEBUG', false),

    // Default batch size for sync operations
    'batch_size' => env('ODOO_BATCH_SIZE', 100),

    // Queue connection for sync jobs
    'queue_connection' => env('ODOO_QUEUE_CONNECTION', 'database'),

    // Queue name for sync jobs
    'queue_name' => env('ODOO_QUEUE_NAME', 'odoo-sync'),

    // Sync log retention in days
    'log_retention_days' => env('ODOO_LOG_RETENTION_DAYS', 30),

    // Enable automatic conflict detection
    'auto_detect_conflicts' => true,

    // Default conflict resolution strategy
    'default_conflict_resolution' => 'manual',

    // Supported field transformers
    'transformers' => [
        'direct' => \Modules\OdooIntegration\Services\Transform\Transformers\DirectTransformer::class,
        'date' => \Modules\OdooIntegration\Services\Transform\Transformers\DateTransformer::class,
        'datetime' => \Modules\OdooIntegration\Services\Transform\Transformers\DateTimeTransformer::class,
        'money' => \Modules\OdooIntegration\Services\Transform\Transformers\MoneyTransformer::class,
        'relation' => \Modules\OdooIntegration\Services\Transform\Transformers\RelationTransformer::class,
        'enum' => \Modules\OdooIntegration\Services\Transform\Transformers\EnumTransformer::class,
        'boolean' => \Modules\OdooIntegration\Services\Transform\Transformers\BooleanTransformer::class,
        'json' => \Modules\OdooIntegration\Services\Transform\Transformers\JsonTransformer::class,
        'translatable' => \Modules\OdooIntegration\Services\Transform\Transformers\TranslatableTransformer::class,
        'split_name' => \Modules\OdooIntegration\Services\Transform\Transformers\SplitNameTransformer::class,
        'many2many' => \Modules\OdooIntegration\Services\Transform\Transformers\Many2ManyTransformer::class,
        'percentage' => \Modules\OdooIntegration\Services\Transform\Transformers\PercentageTransformer::class,
    ],

    // Entity sync priorities (lower = synced first)
    // Order: Companies -> Departments -> Users -> Employees -> Other entities
    'entity_priorities' => [
        'res.company' => 1,        // Branches first
        'hr.department' => 2,      // Departments second
        'res.users' => 3,          // Users third
        'hr.employee' => 4,        // Employees fourth (depends on users, departments)
        'resource.calendar' => 5,  // Work schedules (assignments reconcile post-sync)
        'hr.leave.type' => 10,
        'hr.leave.allocation' => 11,
        'hr.leave' => 12,
        'hr.salary.rule.category' => 20,
        'hr.payroll.structure' => 21,
        'hr.salary.rule' => 22,
        'hr.payslip' => 30,
        'hr.payslip.line' => 31,
        'hr.attendance' => 40,
        'project.project' => 50,
        'project.task' => 51,
        'account.analytic.line' => 52,
    ],

    // Odoo model to XForce model mapping
    'model_mapping' => [
        // Organization
        'res.company' => 'Modules\\Core\\Models\\Branch',
        'res.users' => 'Modules\\Auth\\Models\\User',

        // HR
        'hr.department' => 'Modules\\Core\\Models\\Department',
        'hr.employee' => 'Modules\\Staff\\Models\\StaffProfile',

        // Work schedules
        'resource.calendar' => 'Modules\\Booking\\Models\\WorkSchedule',

        // Time Off
        'hr.leave.type' => 'Modules\\Booking\\Models\\TimeOffType',
        'hr.leave.allocation' => 'Modules\\Booking\\Models\\TimeOffAllocation',
        'hr.leave' => 'Modules\\Booking\\Models\\PractitionerTimeOff',

        // Payroll
        'hr.salary.rule.category' => 'Modules\\Payroll\\Models\\SalaryRuleCategory',
        'hr.payroll.structure' => 'Modules\\Payroll\\Models\\SalaryStructure',
        'hr.salary.rule' => 'Modules\\Payroll\\Models\\SalaryRule',
        'hr.payslip' => 'Modules\\Payroll\\Models\\PayrollLine',
        // hr.payslip.line is NOT synced standalone; folded into PayrollLine via the parent slip's applyOdooImport.

        // Attendance
        'hr.attendance' => 'Modules\\Attendance\\Models\\Attendance',

        // Projects
        'project.project' => 'Modules\\Projects\\Models\\Project',
        'project.task' => 'Modules\\Projects\\Models\\ProjectTask',
        'account.analytic.line' => 'Modules\\Projects\\Models\\ProjectTimeEntry',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Field Mappings (keyed by Odoo model)
    |--------------------------------------------------------------------------
    | These mappings are automatically created when a new entity mapping is added.
    | Format: 'odoo_model' => [ ['local_field' => '', 'odoo_field' => '', ...], ... ]
    */
    'default_mappings' => [
        // =====================================================================
        // Users (res.users -> User)
        // =====================================================================
        'res.users' => [
            ['local_field' => 'odoo_id', 'odoo_field' => 'id', 'is_key_field' => true],
            ['local_field' => 'first_name', 'odoo_field' => 'name', 'transform_type' => 'split_name', 'transform_config' => ['part' => 'first']],
            ['local_field' => 'last_name', 'odoo_field' => 'name', 'transform_type' => 'split_name', 'transform_config' => ['part' => 'last']],
            ['local_field' => 'email', 'odoo_field' => 'login', 'is_required' => true],
            ['local_field' => 'password', 'odoo_field' => null, 'default_value' => 'ChangeMe123!', 'direction' => 'import'],
            ['local_field' => 'is_active', 'odoo_field' => 'active', 'transform_type' => 'boolean'],
        ],

        // =====================================================================
        // Departments (hr.department -> Department)
        // =====================================================================
        'hr.department' => [
            ['local_field' => 'odoo_id', 'odoo_field' => 'id', 'is_key_field' => true],
            ['local_field' => 'name', 'odoo_field' => 'name', 'is_required' => true],
            ['local_field' => 'code', 'odoo_field' => 'code'],
            // skip_on_missing=false: departments sync BEFORE employees (and
            // before their own parents within a batch), so unresolved
            // parent/manager relations must import as null — they backfill on
            // the next full sync — instead of skipping the whole department.
            ['local_field' => 'parent_id', 'odoo_field' => 'parent_id', 'transform_type' => 'relation', 'transform_config' => ['model' => 'Modules\\Core\\Models\\Department', 'skip_on_missing' => false]],
            ['local_field' => 'manager_id', 'odoo_field' => 'manager_id', 'transform_type' => 'relation', 'transform_config' => ['model' => 'Modules\\Staff\\Models\\StaffProfile', 'skip_on_missing' => false]],
            ['local_field' => 'is_active', 'odoo_field' => 'active', 'transform_type' => 'boolean', 'default_value' => true],
        ],

        // =====================================================================
        // Employees / Staff (hr.employee -> StaffProfile)
        // =====================================================================
        'hr.employee' => [
            ['local_field' => 'odoo_id', 'odoo_field' => 'id', 'is_key_field' => true],
            ['local_field' => 'user_id', 'odoo_field' => 'user_id', 'transform_type' => 'relation', 'transform_config' => ['model' => 'Modules\\Auth\\Models\\User']],
            ['local_field' => 'department_id', 'odoo_field' => 'department_id', 'transform_type' => 'relation', 'transform_config' => ['model' => 'Modules\\Core\\Models\\Department']],
            ['local_field' => 'job_title', 'odoo_field' => 'job_title'],
            ['local_field' => 'work_email', 'odoo_field' => 'work_email'],
            ['local_field' => 'work_phone', 'odoo_field' => 'work_phone'],
            ['local_field' => 'mobile_phone', 'odoo_field' => 'mobile_phone'],
            ['local_field' => 'hire_date', 'odoo_field' => 'date_start', 'transform_type' => 'date'],
            ['local_field' => 'is_active', 'odoo_field' => 'active', 'transform_type' => 'boolean'],
            // Approvers — both are res.users many2one in Odoo
            ['local_field' => 'time_off_approver_user_id', 'odoo_field' => 'leave_manager_id', 'transform_type' => 'relation', 'transform_config' => ['model' => 'Modules\\Auth\\Models\\User']],
            ['local_field' => 'attendance_approver_user_id', 'odoo_field' => 'attendance_manager_id', 'transform_type' => 'relation', 'transform_config' => ['model' => 'Modules\\Auth\\Models\\User']],
        ],

        // =====================================================================
        // Time Off Types (hr.leave.type -> TimeOffType)
        // =====================================================================
        'hr.leave.type' => [
            ['local_field' => 'odoo_id', 'odoo_field' => 'id', 'is_key_field' => true],
            ['local_field' => 'name', 'odoo_field' => 'name', 'is_required' => true, 'transform_type' => 'translatable'],
            ['local_field' => 'code', 'odoo_field' => 'code'],
            ['local_field' => 'is_active', 'odoo_field' => 'active', 'transform_type' => 'boolean'],
            ['local_field' => 'requires_approval', 'odoo_field' => 'leave_validation_type', 'transform_type' => 'enum', 'transform_config' => ['mapping' => ['no_validation' => false, 'hr' => true, 'manager' => true, 'both' => true]]],
        ],

        // =====================================================================
        // Time Off Allocations (hr.leave.allocation -> TimeOffAllocation)
        // =====================================================================
        'hr.leave.allocation' => [
            ['local_field' => 'odoo_id', 'odoo_field' => 'id', 'is_key_field' => true],
            ['local_field' => 'staff_profile_id', 'odoo_field' => 'employee_id', 'transform_type' => 'relation', 'transform_config' => ['model' => 'Modules\\Staff\\Models\\StaffProfile']],
            ['local_field' => 'time_off_type_id', 'odoo_field' => 'holiday_status_id', 'transform_type' => 'relation', 'transform_config' => ['model' => 'Modules\\Booking\\Models\\TimeOffType']],
            ['local_field' => 'allocated_days', 'odoo_field' => 'number_of_days'],
            ['local_field' => 'used_days', 'odoo_field' => 'leaves_taken'],
            ['local_field' => 'date_from', 'odoo_field' => 'date_from', 'transform_type' => 'date'],
            ['local_field' => 'date_to', 'odoo_field' => 'date_to', 'transform_type' => 'date'],
        ],

        // =====================================================================
        // Time Off Requests (hr.leave -> PractitionerTimeOff)
        // =====================================================================
        'hr.leave' => [
            ['local_field' => 'odoo_id', 'odoo_field' => 'id', 'is_key_field' => true],
            ['local_field' => 'staff_profile_id', 'odoo_field' => 'employee_id', 'transform_type' => 'relation', 'transform_config' => ['model' => 'Modules\\Staff\\Models\\StaffProfile']],
            ['local_field' => 'time_off_type_id', 'odoo_field' => 'holiday_status_id', 'transform_type' => 'relation', 'transform_config' => ['model' => 'Modules\\Booking\\Models\\TimeOffType']],
            ['local_field' => 'start_date', 'odoo_field' => 'date_from', 'transform_type' => 'datetime'],
            ['local_field' => 'end_date', 'odoo_field' => 'date_to', 'transform_type' => 'datetime'],
            ['local_field' => 'reason', 'odoo_field' => 'name'],
            ['local_field' => 'status', 'odoo_field' => 'state', 'transform_type' => 'enum', 'transform_config' => ['mapping' => ['draft' => 'pending', 'confirm' => 'pending', 'validate1' => 'approved', 'validate' => 'approved', 'refuse' => 'rejected', 'cancel' => 'cancelled']]],
        ],

        // =====================================================================
        // Salary Rule Categories (hr.salary.rule.category -> SalaryRuleCategory)
        // Local table is plain varchar; not translatable. parent_id has no local column.
        // The local 'type' column is derived from the code via SalaryRuleCategory::applyOdooImport().
        // =====================================================================
        'hr.salary.rule.category' => [
            // Note: odoo_id is NOT a key_field — it's null on pre-existing local rows.
            // The business key is `code`, used to LINK existing rows on first sync.
            ['local_field' => 'odoo_id', 'odoo_field' => 'id'],
            ['local_field' => 'code', 'odoo_field' => 'code', 'is_required' => true, 'is_key_field' => true],
            ['local_field' => 'name', 'odoo_field' => 'name', 'is_required' => true],
        ],

        // =====================================================================
        // Salary Structures (hr.payroll.structure -> SalaryStructure)
        // pay_frequency and currency are NOT NULL locally; defaulted in
        // SalaryStructure::applyOdooImport().
        // =====================================================================
        'hr.payroll.structure' => [
            ['local_field' => 'odoo_id', 'odoo_field' => 'id'],
            ['local_field' => 'code', 'odoo_field' => 'code', 'is_key_field' => true],
            ['local_field' => 'name', 'odoo_field' => 'name', 'is_required' => true],
            // Note: hr.payroll.structure has no 'active' field in Odoo; is_active defaults to true via the model.
        ],

        // =====================================================================
        // Work Schedules (resource.calendar -> WorkSchedule)
        // weekly_hours/working_days are built from the calendar's attendance
        // lines in WorkSchedule::applyOdooImport().
        // =====================================================================
        'resource.calendar' => [
            ['local_field' => 'odoo_id', 'odoo_field' => 'id', 'is_key_field' => true],
            ['local_field' => 'name', 'odoo_field' => 'name', 'is_required' => true],
            ['local_field' => 'is_active', 'odoo_field' => 'active', 'transform_type' => 'boolean', 'default_value' => true],
        ],

        // =====================================================================
        // Salary Rules (hr.salary.rule -> SalaryRule)
        // structure_id has no local column. amount_type values normalised in
        // SalaryRule::applyOdooImport() (Odoo amount_select is fix/percentage/code).
        // =====================================================================
        'hr.salary.rule' => [
            ['local_field' => 'odoo_id', 'odoo_field' => 'id'],
            ['local_field' => 'code', 'odoo_field' => 'code', 'is_required' => true, 'is_key_field' => true],
            ['local_field' => 'name', 'odoo_field' => 'name', 'is_required' => true],
            ['local_field' => 'category_id', 'odoo_field' => 'category_id', 'transform_type' => 'relation', 'transform_config' => ['model' => 'Modules\\Payroll\\Models\\SalaryRuleCategory']],
            ['local_field' => 'sequence', 'odoo_field' => 'sequence'],
            ['local_field' => 'amount_type', 'odoo_field' => 'amount_select'],
            ['local_field' => 'is_active', 'odoo_field' => 'active', 'transform_type' => 'boolean'],
            ['local_field' => 'appears_on_payslip', 'odoo_field' => 'appears_on_payslip', 'transform_type' => 'boolean'],
            ['local_field' => 'show_in_mobile_app', 'odoo_field' => 'show_in_mobile_app', 'transform_type' => 'boolean'],
        ],

        // =====================================================================
        // Payslips (hr.payslip -> PayrollLine)
        // Odoo's hr.payslip = one slip per employee per period; XForce's PayrollLine
        // is one row per (run, employee). PayrollLine::applyOdooImport() finds-or-
        // creates the parent PayrollRun (matched by year+month) and buckets the
        // hr.payslip.line items by SalaryRuleCategory.code into the local aggregate
        // columns plus rule_amounts_json. Payslip lines are NOT synced standalone.
        // =====================================================================
        'hr.payslip' => [
            // odoo_id is the primary discriminator (line 46 of ImportService) — not a business key.
            // Business key for LINK-on-collision is (staff_profile_id, payroll_run_id), which is
            // also the local UNIQUE constraint. payroll_run_id is computed in
            // PayrollLine::applyOdooImport() from the slip's date_from, so its mapping has no
            // odoo_field — it exists purely so findByKeyFields includes it in the composite.
            ['local_field' => 'odoo_id', 'odoo_field' => 'id'],
            ['local_field' => 'staff_profile_id', 'odoo_field' => 'employee_id', 'is_key_field' => true, 'transform_type' => 'relation', 'transform_config' => ['model' => 'Modules\\Staff\\Models\\StaffProfile']],
            ['local_field' => 'payroll_run_id', 'odoo_field' => '', 'is_key_field' => true],
            // hr.payslip.state: draft → verify → done (validated/paid) → cancel.
            // "verify" = waiting for HR approval; surfaced locally as "pending".
            ['local_field' => 'status', 'odoo_field' => 'state', 'transform_type' => 'enum', 'transform_config' => ['mapping' => ['draft' => 'draft', 'verify' => 'pending', 'done' => 'approved', 'paid' => 'paid', 'cancel' => 'cancelled']]],
        ],

        // =====================================================================
        // Attendance (hr.attendance -> Attendance)
        // =====================================================================
        'hr.attendance' => [
            ['local_field' => 'odoo_id', 'odoo_field' => 'id', 'is_key_field' => true],
            ['local_field' => 'staff_profile_id', 'odoo_field' => 'employee_id', 'transform_type' => 'relation', 'transform_config' => ['model' => 'Modules\\Staff\\Models\\StaffProfile']],
            ['local_field' => 'check_in', 'odoo_field' => 'check_in', 'transform_type' => 'datetime'],
            ['local_field' => 'check_out', 'odoo_field' => 'check_out', 'transform_type' => 'datetime'],
            ['local_field' => 'worked_hours', 'odoo_field' => 'worked_hours'],
        ],

        // =====================================================================
        // Projects (project.project -> Project)
        // =====================================================================
        'project.project' => [
            ['local_field' => 'odoo_id', 'odoo_field' => 'id', 'is_key_field' => true],
            ['local_field' => 'name', 'odoo_field' => 'name', 'is_required' => true, 'transform_type' => 'translatable'],
            ['local_field' => 'description', 'odoo_field' => 'description', 'transform_type' => 'translatable'],
            ['local_field' => 'code', 'odoo_field' => 'sequence_code'],
            ['local_field' => 'manager_id', 'odoo_field' => 'user_id', 'transform_type' => 'relation', 'transform_config' => ['model' => 'Modules\\Auth\\Models\\User']],
            ['local_field' => 'start_date', 'odoo_field' => 'date_start', 'transform_type' => 'date'],
            ['local_field' => 'end_date', 'odoo_field' => 'date', 'transform_type' => 'date'],
            ['local_field' => 'is_active', 'odoo_field' => 'active', 'transform_type' => 'boolean'],
            ['local_field' => 'allow_timesheets', 'odoo_field' => 'allow_timesheets', 'transform_type' => 'boolean'],
        ],

        // =====================================================================
        // Project Tasks (project.task -> ProjectTask)
        // =====================================================================
        'project.task' => [
            ['local_field' => 'odoo_id', 'odoo_field' => 'id', 'is_key_field' => true],
            ['local_field' => 'project_id', 'odoo_field' => 'project_id', 'transform_type' => 'relation', 'transform_config' => ['model' => 'Modules\\Projects\\Models\\Project'], 'is_required' => true],
            ['local_field' => 'parent_id', 'odoo_field' => 'parent_id', 'transform_type' => 'relation', 'transform_config' => ['model' => 'Modules\\Projects\\Models\\ProjectTask']],
            ['local_field' => 'name', 'odoo_field' => 'name', 'is_required' => true, 'transform_type' => 'translatable'],
            ['local_field' => 'description', 'odoo_field' => 'description', 'transform_type' => 'translatable'],
            ['local_field' => 'assigned_to', 'odoo_field' => 'user_ids', 'transform_type' => 'relation', 'transform_config' => ['model' => 'Modules\\Auth\\Models\\User']],
            ['local_field' => 'due_date', 'odoo_field' => 'date_deadline', 'transform_type' => 'date'],
            ['local_field' => 'estimated_hours', 'odoo_field' => 'planned_hours'],
            ['local_field' => 'priority', 'odoo_field' => 'priority', 'transform_type' => 'enum', 'transform_config' => ['mapping' => ['0' => 'low', '1' => 'normal', '2' => 'high', '3' => 'urgent']]],
            ['local_field' => 'is_active', 'odoo_field' => 'active', 'transform_type' => 'boolean'],
        ],

        // =====================================================================
        // Timesheets (account.analytic.line -> ProjectTimeEntry)
        // =====================================================================
        'account.analytic.line' => [
            ['local_field' => 'odoo_id', 'odoo_field' => 'id', 'is_key_field' => true],
            ['local_field' => 'project_id', 'odoo_field' => 'project_id', 'transform_type' => 'relation', 'transform_config' => ['model' => 'Modules\\Projects\\Models\\Project']],
            ['local_field' => 'task_id', 'odoo_field' => 'task_id', 'transform_type' => 'relation', 'transform_config' => ['model' => 'Modules\\Projects\\Models\\ProjectTask']],
            ['local_field' => 'user_id', 'odoo_field' => 'user_id', 'transform_type' => 'relation', 'transform_config' => ['model' => 'Modules\\Auth\\Models\\User']],
            ['local_field' => 'staff_profile_id', 'odoo_field' => 'employee_id', 'transform_type' => 'relation', 'transform_config' => ['model' => 'Modules\\Staff\\Models\\StaffProfile']],
            ['local_field' => 'date', 'odoo_field' => 'date', 'transform_type' => 'date', 'is_required' => true],
            ['local_field' => 'hours', 'odoo_field' => 'unit_amount', 'is_required' => true],
            ['local_field' => 'description', 'odoo_field' => 'name'],
        ],

        // =====================================================================
        // Branches/Companies (res.company -> Branch)
        // =====================================================================
        'res.company' => [
            ['local_field' => 'odoo_id', 'odoo_field' => 'id', 'is_key_field' => true],
            ['local_field' => 'name', 'odoo_field' => 'name', 'is_required' => true, 'transform_type' => 'translatable'],
            ['local_field' => 'code', 'odoo_field' => 'company_registry'],
            ['local_field' => 'email', 'odoo_field' => 'email'],
            ['local_field' => 'phone', 'odoo_field' => 'phone'],
            ['local_field' => 'is_active', 'odoo_field' => 'active', 'transform_type' => 'boolean'],
        ],
    ],
];
