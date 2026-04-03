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
    'entity_priorities' => [
        'res.users' => 1,
        'hr.employee' => 2,
        'hr.leave.type' => 5,
        'hr.leave.allocation' => 6,
        'hr.leave' => 7,
        'hr.salary.rule.category' => 10,
        'hr.payroll.structure' => 11,
        'hr.salary.rule' => 12,
        'hr.payslip' => 20,
        'hr.payslip.line' => 21,
        'hr.attendance' => 30,
        'project.project' => 40,
        'project.task' => 41,
        'account.analytic.line' => 42,
    ],

    // Odoo model to XForce model mapping
    'model_mapping' => [
        'res.users' => \Modules\Auth\Models\User::class,
        'hr.employee' => 'Modules\\Staff\\Models\\StaffProfile',
        'hr.leave.type' => 'Modules\\Booking\\Models\\TimeOffType',
        'hr.leave.allocation' => 'Modules\\Booking\\Models\\TimeOffAllocation',
        'hr.leave' => 'Modules\\Booking\\Models\\PractitionerTimeOff',
        'hr.salary.rule.category' => 'Modules\\Payroll\\Models\\SalaryRuleCategory',
        'hr.payroll.structure' => 'Modules\\Payroll\\Models\\SalaryStructure',
        'hr.salary.rule' => 'Modules\\Payroll\\Models\\SalaryRule',
        'hr.payslip' => 'Modules\\Payroll\\Models\\PayrollRun',
        'hr.payslip.line' => 'Modules\\Payroll\\Models\\PayrollLine',
        'hr.attendance' => 'Modules\\Attendance\\Models\\Attendance',
        'project.project' => 'Modules\\Projects\\Models\\Project',
        'project.task' => 'Modules\\Projects\\Models\\ProjectTask',
        'account.analytic.line' => 'Modules\\Projects\\Models\\ProjectTimeEntry',
    ],

    // Default field mappings per entity (used by seeder)
    'default_field_mappings' => [
        'res.users' => [
            ['local' => 'name', 'odoo' => 'name', 'direction' => 'import', 'transform' => 'direct'],
            ['local' => 'email', 'odoo' => 'login', 'direction' => 'import', 'transform' => 'direct', 'is_key' => true],
            ['local' => 'is_active', 'odoo' => 'active', 'direction' => 'import', 'transform' => 'boolean'],
        ],
        'hr.employee' => [
            ['local' => 'user_id', 'odoo' => 'user_id', 'direction' => 'import', 'transform' => 'relation'],
            ['local' => 'first_name', 'odoo' => 'name', 'direction' => 'import', 'transform' => 'split_name', 'config' => ['part' => 'first']],
            ['local' => 'last_name', 'odoo' => 'name', 'direction' => 'import', 'transform' => 'split_name', 'config' => ['part' => 'last']],
            ['local' => 'work_email', 'odoo' => 'work_email', 'direction' => 'import', 'transform' => 'direct'],
            ['local' => 'work_phone', 'odoo' => 'work_phone', 'direction' => 'import', 'transform' => 'direct'],
            ['local' => 'mobile_phone', 'odoo' => 'mobile_phone', 'direction' => 'import', 'transform' => 'direct'],
            ['local' => 'job_title', 'odoo' => 'job_title', 'direction' => 'import', 'transform' => 'direct'],
            ['local' => 'hire_date', 'odoo' => 'date_start', 'direction' => 'import', 'transform' => 'date'],
            ['local' => 'is_active', 'odoo' => 'active', 'direction' => 'import', 'transform' => 'boolean'],
        ],
        'project.project' => [
            ['local' => 'name', 'odoo' => 'name', 'direction' => 'bidirectional', 'transform' => 'translatable'],
            ['local' => 'description', 'odoo' => 'description', 'direction' => 'bidirectional', 'transform' => 'translatable'],
            ['local' => 'is_active', 'odoo' => 'active', 'direction' => 'bidirectional', 'transform' => 'boolean'],
            ['local' => 'start_date', 'odoo' => 'date_start', 'direction' => 'bidirectional', 'transform' => 'date'],
            ['local' => 'allow_timesheets', 'odoo' => 'allow_timesheets', 'direction' => 'bidirectional', 'transform' => 'boolean'],
        ],
        'project.task' => [
            ['local' => 'project_id', 'odoo' => 'project_id', 'direction' => 'bidirectional', 'transform' => 'relation'],
            ['local' => 'name', 'odoo' => 'name', 'direction' => 'bidirectional', 'transform' => 'translatable'],
            ['local' => 'description', 'odoo' => 'description', 'direction' => 'bidirectional', 'transform' => 'translatable'],
            ['local' => 'start_date', 'odoo' => 'date_deadline', 'direction' => 'bidirectional', 'transform' => 'date'],
            ['local' => 'estimated_hours', 'odoo' => 'planned_hours', 'direction' => 'bidirectional', 'transform' => 'direct'],
        ],
        'account.analytic.line' => [
            ['local' => 'task_id', 'odoo' => 'task_id', 'direction' => 'bidirectional', 'transform' => 'relation'],
            ['local' => 'user_id', 'odoo' => 'user_id', 'direction' => 'bidirectional', 'transform' => 'relation'],
            ['local' => 'hours', 'odoo' => 'unit_amount', 'direction' => 'bidirectional', 'transform' => 'direct'],
            ['local' => 'description', 'odoo' => 'name', 'direction' => 'bidirectional', 'transform' => 'direct'],
            ['local' => 'date', 'odoo' => 'date', 'direction' => 'bidirectional', 'transform' => 'date'],
        ],
    ],
];
