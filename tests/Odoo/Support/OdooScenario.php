<?php

namespace Tests\Odoo\Support;

use Modules\OdooIntegration\Models\OdooConnection;
use Modules\OdooIntegration\Models\OdooEntityMapping;
use Modules\OdooIntegration\Models\OdooFieldMapping;

/**
 * Builds the Odoo connection + entity/field mappings used by the sync tests.
 *
 * The canned mappings replicate the production configuration of the live
 * tenant (models, field maps, transforms, enum value maps, key fields,
 * conflict strategies) so the tests exercise exactly what runs in prod.
 *
 * sync_frequency defaults to `manual` here even where production uses
 * realtime/hourly — tests trigger syncs explicitly; realtime dispatch has
 * its own dedicated test.
 */
class OdooScenario
{
    public static function connection(array $overrides = []): OdooConnection
    {
        return OdooConnection::create(array_merge([
            'tenant_id' => current_tenant_id(),
            'name' => 'Test Odoo',
            'code' => 'test-odoo',
            'host' => 'odoo.test',
            'port' => 8069,
            'database_name' => 'odoo_test_db',
            'username' => 'admin@odoo.test',
            'password' => 'secret',
            'protocol' => 'xmlrpc',
            'use_ssl' => false,
            'timeout' => 30,
            'rate_limit_per_minute' => 0,
            'timezone' => 'UTC',
            'is_active' => true,
            'is_default' => true,
        ], $overrides));
    }

    /**
     * @param  array  $fields  Each item:
     *                         [local, odoo, direction, transform, config?, default?, required?, key?]
     */
    public static function mapping(OdooConnection $connection, array $attributes, array $fields): OdooEntityMapping
    {
        $mapping = OdooEntityMapping::create(array_merge([
            'tenant_id' => $connection->tenant_id,
            'odoo_connection_id' => $connection->id,
            'sync_direction' => 'import',
            'sync_frequency' => 'manual',
            'conflict_resolution' => 'manual',
            'batch_size' => 100,
            'priority' => 10,
            'is_active' => true,
        ], $attributes));

        $sort = 0;
        foreach ($fields as $field) {
            OdooFieldMapping::create([
                'entity_mapping_id' => $mapping->id,
                'local_field' => $field['local'],
                'odoo_field' => $field['odoo'] ?? null,
                'direction' => $field['direction'] ?? 'bidirectional',
                'transform_type' => $field['transform'] ?? 'direct',
                'transform_config' => $field['config'] ?? null,
                'default_value' => $field['default'] ?? null,
                'is_required' => $field['required'] ?? false,
                'is_key_field' => $field['key'] ?? false,
                'is_active' => true,
                'sort_order' => $field['sort'] ?? $sort,
            ]);
            $sort++;
        }

        return $mapping->fresh();
    }

    // ------------------------------------------------------------------
    // Canned entity mappings (mirroring the live tenant configuration)
    // ------------------------------------------------------------------

    public static function users(OdooConnection $c, array $overrides = []): OdooEntityMapping
    {
        return self::mapping($c, array_merge([
            'name' => 'Users',
            'local_model' => \Modules\Auth\Models\User::class,
            'local_table' => 'users',
            'odoo_model' => 'res.users',
            'sync_direction' => 'import',
            'priority' => 1,
            // Legacy merged repeater shape on purpose — getOdooDomain() must
            // normalize it to a positional [field, operator, value] leaf.
            'filter_conditions' => [
                ['0' => 'active', '1' => '=', '2' => true, 'field' => 'active', 'value' => 'true', 'operator' => '='],
            ],
        ], $overrides), [
            ['local' => 'odoo_id', 'odoo' => 'id', 'direction' => 'import', 'key' => true],
            ['local' => 'email', 'odoo' => 'login', 'direction' => 'bidirectional', 'key' => true],
            ['local' => 'is_active', 'odoo' => 'active', 'direction' => 'bidirectional', 'transform' => 'boolean'],
            ['local' => 'username', 'odoo' => 'login', 'direction' => 'bidirectional'],
            ['local' => 'first_name', 'odoo' => 'name', 'direction' => 'bidirectional', 'transform' => 'split_name', 'config' => ['part' => 'first'], 'required' => true],
            ['local' => 'last_name', 'odoo' => 'name', 'direction' => 'bidirectional', 'transform' => 'split_name', 'config' => ['part' => 'last'], 'required' => true],
            ['local' => 'password', 'odoo' => null, 'direction' => 'bidirectional', 'default' => '12345678'],
        ]);
    }

    public static function staffProfiles(OdooConnection $c, array $overrides = []): OdooEntityMapping
    {
        return self::mapping($c, array_merge([
            'name' => 'StaffProfile',
            'local_model' => \Modules\Staff\Models\StaffProfile::class,
            'local_table' => 'staff_profiles',
            'odoo_model' => 'hr.employee',
            'sync_direction' => 'import',
            'priority' => 2,
            'filter_conditions' => [['active', '=', true]],
        ], $overrides), [
            ['local' => 'odoo_id', 'odoo' => 'id', 'direction' => 'bidirectional', 'key' => true],
            ['local' => 'user_id', 'odoo' => 'user_id', 'direction' => 'bidirectional', 'transform' => 'relation', 'config' => ['model' => \Modules\Auth\Models\User::class, 'skip_on_missing' => false]],
            ['local' => 'department_id', 'odoo' => 'department_id', 'direction' => 'bidirectional', 'transform' => 'relation', 'config' => ['model' => \Modules\Core\Models\Department::class, 'skip_on_missing' => false]],
            ['local' => 'job_title', 'odoo' => 'job_title', 'direction' => 'bidirectional'],
            ['local' => 'work_email', 'odoo' => 'work_email', 'direction' => 'bidirectional'],
            ['local' => 'work_phone', 'odoo' => 'work_phone', 'direction' => 'bidirectional'],
            ['local' => 'mobile_phone', 'odoo' => 'mobile_phone', 'direction' => 'bidirectional'],
            ['local' => 'is_active', 'odoo' => 'active', 'direction' => 'bidirectional', 'transform' => 'boolean'],
            ['local' => 'time_off_approver_user_id', 'odoo' => 'leave_manager_id', 'direction' => 'import', 'transform' => 'relation', 'config' => ['model' => \Modules\Auth\Models\User::class, 'skip_on_missing' => false]],
            ['local' => 'attendance_approver_user_id', 'odoo' => 'attendance_manager_id', 'direction' => 'import', 'transform' => 'relation', 'config' => ['model' => \Modules\Auth\Models\User::class, 'skip_on_missing' => false]],
            // '_name' is not fillable on StaffProfile — it only ensures `name`
            // is fetched from Odoo so applyOdooImport can build the linked User.
            ['local' => '_name', 'odoo' => 'name', 'direction' => 'import'],
        ]);
    }

    public static function workSchedules(OdooConnection $c, array $overrides = []): OdooEntityMapping
    {
        return self::mapping($c, array_merge([
            'name' => 'WorkSchedule',
            'local_model' => \Modules\Booking\Models\WorkSchedule::class,
            'local_table' => 'work_schedules',
            'odoo_model' => 'resource.calendar',
            'sync_direction' => 'import',
            'priority' => 3,
        ], $overrides), [
            ['local' => 'odoo_id', 'odoo' => 'id', 'direction' => 'import', 'key' => true],
            ['local' => 'name', 'odoo' => 'name', 'direction' => 'import', 'required' => true],
            ['local' => 'is_active', 'odoo' => 'active', 'direction' => 'import', 'transform' => 'boolean', 'default' => true],
        ]);
    }

    public static function timeOffTypes(OdooConnection $c, array $overrides = []): OdooEntityMapping
    {
        return self::mapping($c, array_merge([
            'name' => 'TimeOffType',
            'local_model' => \Modules\Booking\Models\TimeOffType::class,
            'local_table' => 'time_off_types',
            'odoo_model' => 'hr.leave.type',
            'sync_direction' => 'import',
            'priority' => 5,
        ], $overrides), [
            ['local' => 'odoo_id', 'odoo' => 'id', 'direction' => 'bidirectional', 'key' => true],
            ['local' => 'request_unit', 'odoo' => 'request_unit', 'direction' => 'import'],
            ['local' => 'name', 'odoo' => 'name', 'direction' => 'bidirectional', 'transform' => 'translatable', 'required' => true],
            ['local' => 'code', 'odoo' => 'code', 'direction' => 'bidirectional'],
            ['local' => 'is_active', 'odoo' => 'active', 'direction' => 'bidirectional', 'transform' => 'boolean'],
            ['local' => 'requires_approval', 'odoo' => 'leave_validation_type', 'direction' => 'bidirectional', 'transform' => 'enum', 'config' => ['mapping' => ['hr' => '1', 'both' => '1', 'manager' => '1', 'no_validation' => null]]],
        ]);
    }

    public static function timeOffAllocations(OdooConnection $c, array $overrides = []): OdooEntityMapping
    {
        return self::mapping($c, array_merge([
            'name' => 'TimeOffAllocation',
            'local_model' => \Modules\Booking\Models\TimeOffAllocation::class,
            'local_table' => 'time_off_allocations',
            'odoo_model' => 'hr.leave.allocation',
            'sync_direction' => 'import',
            'priority' => 6,
            'batch_size' => 50,
            'sync_date_field' => 'date_from',
            'sync_from_date' => '2026-01-01',
        ], $overrides), [
            ['local' => 'odoo_id', 'odoo' => 'id', 'direction' => 'bidirectional', 'key' => true],
            ['local' => 'staff_profile_id', 'odoo' => 'employee_id', 'direction' => 'bidirectional', 'transform' => 'relation', 'config' => ['model' => \Modules\Staff\Models\StaffProfile::class], 'required' => true],
            ['local' => 'time_off_type_id', 'odoo' => 'holiday_status_id', 'direction' => 'bidirectional', 'transform' => 'relation', 'config' => ['model' => \Modules\Booking\Models\TimeOffType::class]],
            ['local' => 'allocated_days', 'odoo' => 'number_of_days', 'direction' => 'bidirectional'],
            // Odoo-side consumption must land locally or balances drift from
            // Odoo whenever a leave is approved in Odoo directly. Same row
            // must exist in the live tenant's mapping config.
            ['local' => 'used_days', 'odoo' => 'leaves_taken', 'direction' => 'import'],
            ['local' => 'date_from', 'odoo' => 'date_from', 'direction' => 'bidirectional', 'transform' => 'date'],
            ['local' => 'date_to', 'odoo' => 'date_to', 'direction' => 'bidirectional', 'transform' => 'date'],
        ]);
    }

    public static function timeOffs(OdooConnection $c, array $overrides = []): OdooEntityMapping
    {
        return self::mapping($c, array_merge([
            'name' => 'PractitionerTimeOff',
            'local_model' => \Modules\Booking\Models\PractitionerTimeOff::class,
            'local_table' => 'practitioner_time_offs',
            'odoo_model' => 'hr.leave',
            'sync_direction' => 'bidirectional',
            'conflict_resolution' => 'local_wins',
            'priority' => 7,
            'sync_date_field' => 'date_from',
            'sync_from_date' => '2026-01-01',
        ], $overrides), [
            ['local' => 'odoo_id', 'odoo' => 'id', 'direction' => 'import', 'key' => true],
            ['local' => 'staff_profile_id', 'odoo' => 'employee_id', 'direction' => 'bidirectional', 'transform' => 'relation', 'config' => ['model' => \Modules\Staff\Models\StaffProfile::class]],
            ['local' => 'time_off_type_id', 'odoo' => 'holiday_status_id', 'direction' => 'bidirectional', 'transform' => 'relation', 'config' => ['model' => \Modules\Booking\Models\TimeOffType::class]],
            ['local' => 'start_date', 'odoo' => 'date_from', 'direction' => 'bidirectional', 'transform' => 'datetime'],
            ['local' => 'end_date', 'odoo' => 'date_to', 'direction' => 'bidirectional', 'transform' => 'datetime'],
            ['local' => 'reason', 'odoo' => 'name', 'direction' => 'bidirectional'],
            ['local' => 'status', 'odoo' => 'state', 'direction' => 'bidirectional', 'transform' => 'enum', 'config' => ['mapping' => ['draft' => 'pending', 'confirm' => 'pending', 'validate' => 'approved', 'validate1' => 'approved', 'refuse' => 'rejected', 'cancel' => 'cancelled']]],
            ['local' => 'days_requested', 'odoo' => 'number_of_days', 'direction' => 'import'],
            ['local' => 'hours_requested', 'odoo' => 'number_of_hours_display', 'direction' => 'import'],
        ]);
    }

    public static function salaryRuleCategories(OdooConnection $c, array $overrides = []): OdooEntityMapping
    {
        return self::mapping($c, array_merge([
            'name' => 'SalaryRuleCategory',
            'local_model' => \Modules\Payroll\Models\SalaryRuleCategory::class,
            'local_table' => 'salary_rule_categories',
            'odoo_model' => 'hr.salary.rule.category',
            'sync_direction' => 'import',
            'priority' => 8,
            'batch_size' => 50,
        ], $overrides), [
            ['local' => 'odoo_id', 'odoo' => 'id', 'direction' => 'import'],
            ['local' => 'code', 'odoo' => 'code', 'direction' => 'import', 'required' => true, 'key' => true],
            ['local' => 'name', 'odoo' => 'name', 'direction' => 'import', 'required' => true],
        ]);
    }

    public static function salaryStructures(OdooConnection $c, array $overrides = []): OdooEntityMapping
    {
        return self::mapping($c, array_merge([
            'name' => 'SalaryStructure',
            'local_model' => \Modules\Payroll\Models\SalaryStructure::class,
            'local_table' => 'salary_structures',
            'odoo_model' => 'hr.payroll.structure',
            'sync_direction' => 'import',
            'priority' => 9,
            'batch_size' => 50,
        ], $overrides), [
            ['local' => 'odoo_id', 'odoo' => 'id', 'direction' => 'import'],
            ['local' => 'code', 'odoo' => 'code', 'direction' => 'import', 'key' => true],
            ['local' => 'name', 'odoo' => 'name', 'direction' => 'import', 'required' => true],
        ]);
    }

    public static function salaryRules(OdooConnection $c, array $overrides = []): OdooEntityMapping
    {
        return self::mapping($c, array_merge([
            'name' => 'SalaryRule',
            'local_model' => \Modules\Payroll\Models\SalaryRule::class,
            'local_table' => 'salary_rules',
            'odoo_model' => 'hr.salary.rule',
            'sync_direction' => 'import',
            'priority' => 10,
            'batch_size' => 50,
        ], $overrides), [
            ['local' => 'odoo_id', 'odoo' => 'id', 'direction' => 'import'],
            ['local' => 'code', 'odoo' => 'code', 'direction' => 'import', 'required' => true, 'key' => true],
            ['local' => 'name', 'odoo' => 'name', 'direction' => 'import', 'required' => true],
            ['local' => 'category_id', 'odoo' => 'category_id', 'direction' => 'import', 'transform' => 'relation', 'config' => ['model' => \Modules\Payroll\Models\SalaryRuleCategory::class]],
            ['local' => 'sequence', 'odoo' => 'sequence', 'direction' => 'import'],
            ['local' => 'amount_type', 'odoo' => 'amount_select', 'direction' => 'import'],
            ['local' => 'is_active', 'odoo' => 'active', 'direction' => 'import', 'transform' => 'boolean'],
            // Which rules the employee may see on the mobile payslip.
            ['local' => 'appears_on_payslip', 'odoo' => 'appears_on_payslip', 'direction' => 'import', 'transform' => 'boolean'],
            ['local' => 'show_in_mobile_app', 'odoo' => 'show_in_mobile_app', 'direction' => 'import', 'transform' => 'boolean'],
        ]);
    }

    public static function payslips(OdooConnection $c, array $overrides = []): OdooEntityMapping
    {
        return self::mapping($c, array_merge([
            'name' => 'Payslip',
            'local_model' => \Modules\Payroll\Models\PayrollLine::class,
            'local_table' => 'payroll_lines',
            'odoo_model' => 'hr.payslip',
            'sync_direction' => 'import',
            'priority' => 11,
            'batch_size' => 50,
            'filter_conditions' => [
                ['field' => 'date_from', 'operator' => '>=', 'value' => '2024-01-01'],
            ],
        ], $overrides), [
            ['local' => 'odoo_id', 'odoo' => 'id', 'direction' => 'import'],
            ['local' => 'staff_profile_id', 'odoo' => 'employee_id', 'direction' => 'import', 'transform' => 'relation', 'config' => ['model' => \Modules\Staff\Models\StaffProfile::class], 'required' => true, 'key' => true],
            ['local' => 'payroll_run_id', 'odoo' => '', 'direction' => 'import', 'key' => true],
            ['local' => 'status', 'odoo' => 'state', 'direction' => 'import', 'transform' => 'enum', 'config' => ['default' => null, 'mapping' => ['draft' => 'draft', 'verify' => 'pending', 'done' => 'approved', 'paid' => 'paid', 'cancel' => 'cancelled']]],
        ]);
    }

    public static function attendances(OdooConnection $c, array $overrides = []): OdooEntityMapping
    {
        return self::mapping($c, array_merge([
            'name' => 'Attendance',
            'local_model' => \Modules\Attendance\Models\Attendance::class,
            'local_table' => 'attendances',
            'odoo_model' => 'hr.attendance',
            'sync_direction' => 'export',
            'priority' => 12,
        ], $overrides), [
            ['local' => 'odoo_id', 'odoo' => 'id', 'direction' => 'bidirectional', 'key' => true, 'sort' => 1],
            ['local' => 'staff_profile_id', 'odoo' => 'employee_id', 'direction' => 'bidirectional', 'transform' => 'relation', 'config' => ['model' => \Modules\Staff\Models\StaffProfile::class], 'required' => true, 'sort' => 2],
            ['local' => 'check_in_time', 'odoo' => 'check_in', 'direction' => 'bidirectional', 'transform' => 'datetime', 'sort' => 3],
            ['local' => 'check_out_time', 'odoo' => 'check_out', 'direction' => 'bidirectional', 'transform' => 'datetime', 'sort' => 4],
            ['local' => 'working_hours', 'odoo' => 'worked_hours', 'direction' => 'bidirectional', 'sort' => 5],
        ]);
    }

    // ------------------------------------------------------------------
    // Local record helpers
    // ------------------------------------------------------------------

    public static function localUser(array $overrides = []): \Modules\Auth\Models\User
    {
        static $i = 0;
        $i++;

        return \Modules\Auth\Models\User::create(array_merge([
            'tenant_id' => current_tenant_id(),
            'first_name' => 'Local',
            'last_name' => "User{$i}",
            'email' => "local.user{$i}@xforce.test",
            'password' => bcrypt('secret-password'),
        ], $overrides));
    }

    public static function localStaff(array $overrides = []): \Modules\Staff\Models\StaffProfile
    {
        $user = $overrides['user'] ?? self::localUser();
        unset($overrides['user']);

        return \Modules\Staff\Models\StaffProfile::create(array_merge([
            'tenant_id' => current_tenant_id(),
            'user_id' => $user->id,
            'job_title' => 'Therapist',
            'is_active' => true,
        ], $overrides));
    }
}
