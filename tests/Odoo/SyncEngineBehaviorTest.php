<?php

use Illuminate\Support\Facades\Bus;
use Modules\Auth\Models\User;
use Modules\OdooIntegration\Enums\SyncStatus;
use Modules\OdooIntegration\Exceptions\OdooRateLimitException;
use Modules\OdooIntegration\Jobs\RealtimeSyncJob;
use Modules\OdooIntegration\Services\RealtimeSyncManager;
use Modules\OdooIntegration\Services\Sync\SyncEngine;
use Tests\Odoo\Support\OdooScenario;

/**
 * SyncEngine mechanics: batching, rate limits, failure handling, priorities,
 * and realtime dispatch.
 */
test('imports large record sets in stable id-ordered batches', function () {
    $connection = OdooScenario::connection();
    $mapping = OdooScenario::users($connection, ['batch_size' => 3]);

    foreach (range(1, 5) as $i) {
        $this->odoo->seed('res.users', ['name' => "User Number{$i}", 'login' => "user{$i}@clinic.test", 'active' => true]);
    }

    $log = runOdooSync($mapping);

    expect($log->records_created)->toBe(5)
        ->and(User::whereNotNull('odoo_id')->count())->toBe(5);

    $reads = $this->odoo->callsTo('searchRead', 'res.users');
    expect($reads)->toHaveCount(2)
        ->and($reads[0]['args']['offset'])->toBe(0)
        ->and($reads[0]['args']['limit'])->toBe(3)
        ->and($reads[1]['args']['offset'])->toBe(3)
        // 'id asc' ordering keeps OFFSET pagination deterministic
        ->and($reads[0]['args']['order'])->toBe('id asc');
});

test('retries after a rate limit response and completes the sync', function () {
    $connection = OdooScenario::connection();
    $mapping = OdooScenario::users($connection);

    $this->odoo->seed('res.users', ['name' => 'Rate Limited', 'login' => 'limited@clinic.test', 'active' => true]);

    $this->odoo->failNext(
        new OdooRateLimitException('Rate limit exceeded', retryAfter: 0),
        'res.users',
        'searchCount'
    );

    $log = runOdooSync($mapping);

    expect($log->status)->toBe(SyncStatus::COMPLETED)
        ->and($log->records_created)->toBe(1)
        ->and($this->odoo->callsTo('searchCount', 'res.users'))->toHaveCount(2);
});

test('marks the sync log failed when authentication fails', function () {
    $connection = OdooScenario::connection();
    $mapping = OdooScenario::users($connection);

    $this->odoo->failNext(
        new \Modules\OdooIntegration\Exceptions\OdooAuthException('Invalid credentials'),
        '*',
        'authenticate'
    );

    expect(fn () => runOdooSync($mapping))
        ->toThrow(\Modules\OdooIntegration\Exceptions\OdooAuthException::class);

    $log = \Modules\OdooIntegration\Models\OdooSyncLog::latest('id')->first();
    expect($log->status)->toBe(SyncStatus::FAILED)
        ->and($log->errors)->not->toBeEmpty();
});

test('syncAll runs active mappings in priority order and stamps the connection', function () {
    $connection = OdooScenario::connection();
    OdooScenario::staffProfiles($connection);                       // priority 2
    OdooScenario::users($connection);                               // priority 1
    OdooScenario::timeOffTypes($connection, ['is_active' => false]); // inactive — skipped

    expect($connection->last_sync_at)->toBeNull();

    $results = app(SyncEngine::class)->syncAll($connection->fresh());

    expect($results)->toHaveCount(2);

    // Users (priority 1) must be queried before employees (priority 2)
    $models = collect($this->odoo->calls)
        ->whereIn('method', ['searchCount'])
        ->pluck('model')
        ->values();
    expect($models->all())->toBe(['res.users', 'hr.employee']);

    expect($this->odoo->lastDomain('hr.leave.type'))->toBeNull()
        ->and($connection->fresh()->last_sync_at)->not->toBeNull();
});

test('syncRecord pushes a single record on demand', function () {
    $connection = OdooScenario::connection();
    $staffMapping = OdooScenario::staffProfiles($connection);
    $odooEmpId = $this->odoo->seed('hr.employee', [
        'name' => 'Solo Export', 'user_id' => false, 'work_email' => 'solo@clinic.test', 'active' => true,
    ]);
    runOdooSync($staffMapping);
    $staff = \Modules\Staff\Models\StaffProfile::where('odoo_id', $odooEmpId)->firstOrFail();

    $mapping = OdooScenario::attendances($connection);

    $attendance = \Modules\Attendance\Models\Attendance::create([
        'tenant_id' => current_tenant_id(),
        'staff_profile_id' => $staff->id,
        'attendance_date' => '2026-07-30',
        'check_in_time' => '08:00:00',
        'check_out_time' => '16:00:00',
        'working_hours' => 8,
    ]);

    $result = app(SyncEngine::class)->syncRecord($mapping, localId: $attendance->id, direction: 'export');

    expect($result['action'])->toBe('created')
        ->and($attendance->fresh()->odoo_id)->toBe($result['odoo_id']);
});

test('saving a record under a realtime export mapping dispatches a realtime job', function () {
    Bus::fake([RealtimeSyncJob::class]);

    $connection = OdooScenario::connection();
    $staffMapping = OdooScenario::staffProfiles($connection);
    $odooEmpId = $this->odoo->seed('hr.employee', [
        'name' => 'Realtime Person', 'user_id' => false, 'work_email' => 'rt@clinic.test', 'active' => true,
    ]);
    runOdooSync($staffMapping);
    $staff = \Modules\Staff\Models\StaffProfile::where('odoo_id', $odooEmpId)->firstOrFail();

    $mapping = OdooScenario::attendances($connection, ['sync_frequency' => 'realtime']);
    RealtimeSyncManager::flush();

    $attendance = \Modules\Attendance\Models\Attendance::create([
        'tenant_id' => current_tenant_id(),
        'staff_profile_id' => $staff->id,
        'attendance_date' => '2026-07-30',
        'check_in_time' => '08:00:00',
        'working_hours' => 0,
    ]);

    Bus::assertDispatched(RealtimeSyncJob::class, function (RealtimeSyncJob $job) use ($mapping, $attendance) {
        return $job->entityMappingId === $mapping->id && $job->localId === $attendance->id;
    });

    // Writes made during an import are suppressed — no echo loops
    Bus::fake([RealtimeSyncJob::class]);
    RealtimeSyncManager::suppress(function () use ($staff) {
        \Modules\Attendance\Models\Attendance::create([
            'tenant_id' => current_tenant_id(),
            'staff_profile_id' => $staff->id,
            'attendance_date' => '2026-07-29',
            'check_in_time' => '08:00:00',
            'working_hours' => 0,
        ]);
    });
    Bus::assertNotDispatched(RealtimeSyncJob::class);
});

test('the realtime job exports the saved record to odoo', function () {
    $connection = OdooScenario::connection();
    $staffMapping = OdooScenario::staffProfiles($connection);
    $odooEmpId = $this->odoo->seed('hr.employee', [
        'name' => 'Realtime Person', 'user_id' => false, 'work_email' => 'rt@clinic.test', 'active' => true,
    ]);
    runOdooSync($staffMapping);
    $staff = \Modules\Staff\Models\StaffProfile::where('odoo_id', $odooEmpId)->firstOrFail();

    $mapping = OdooScenario::attendances($connection, ['sync_frequency' => 'realtime']);
    RealtimeSyncManager::flush();

    $attendance = RealtimeSyncManager::suppress(fn () => \Modules\Attendance\Models\Attendance::create([
        'tenant_id' => current_tenant_id(),
        'staff_profile_id' => $staff->id,
        'attendance_date' => '2026-07-30',
        'check_in_time' => '09:00:00',
        'check_out_time' => '17:00:00',
        'working_hours' => 8,
    ]));

    (new RealtimeSyncJob($mapping->id, $attendance->id))->handle(app(SyncEngine::class));

    $attendance->refresh();
    expect($attendance->odoo_id)->not->toBeNull()
        ->and($this->odoo->find('hr.attendance', $attendance->odoo_id)['employee_id'])->toBe($odooEmpId);
});

test('a mapped custom field missing from odoo does not break the import', function () {
    $connection = Tests\Odoo\Support\OdooScenario::connection();
    $mapping = Tests\Odoo\Support\OdooScenario::salaryRules($connection);

    // This Odoo install has NO show_in_mobile_app (custom module absent).
    $this->odoo->defineFields('hr.salary.rule', [
        'id', 'name', 'code', 'category_id', 'sequence', 'amount_select',
        'active', 'appears_on_payslip', 'write_date', 'create_date',
    ]);

    $catId = $this->odoo->seed('hr.salary.rule.category', ['name' => 'Basic', 'code' => 'BASIC']);
    runOdooSync(Tests\Odoo\Support\OdooScenario::salaryRuleCategories($connection));

    $this->odoo->seed('hr.salary.rule', [
        'name' => 'Basic Salary', 'code' => 'BASIC', 'sequence' => 1,
        'category_id' => [$catId, 'Basic'], 'amount_select' => 'fix', 'active' => true,
        'appears_on_payslip' => true,
    ]);

    $log = runOdooSync($mapping);

    expect($log->records_failed)->toBe(0)
        ->and($log->records_created)->toBe(1);

    $rule = Modules\Payroll\Models\SalaryRule::where('code', 'BASIC')->firstOrFail();
    // Missing custom field degrades to the local default, nothing breaks.
    expect($rule->show_in_mobile_app)->toBeFalse()
        ->and($rule->appears_on_payslip)->toBeTrue();
});

test('export payload drops fields the odoo install does not have', function () {
    $connection = Tests\Odoo\Support\OdooScenario::connection();

    $empId = $this->odoo->seed('hr.employee', ['name' => 'Sara Ahmed', 'user_id' => false, 'work_email' => 'sara@clinic.test', 'active' => true]);
    runOdooSync(Tests\Odoo\Support\OdooScenario::staffProfiles($connection));
    $typeOdooId = $this->odoo->seed('hr.leave.type', ['name' => 'Annual Leave', 'code' => 'ANNUAL', 'request_unit' => 'day', 'leave_validation_type' => 'hr', 'active' => true]);
    runOdooSync(Tests\Odoo\Support\OdooScenario::timeOffTypes($connection));
    $staff = Modules\Staff\Models\StaffProfile::where('odoo_id', $empId)->firstOrFail();
    $type = Modules\Booking\Models\TimeOffType::where('odoo_id', $typeOdooId)->firstOrFail();
    $mapping = Tests\Odoo\Support\OdooScenario::timeOffs($connection);

    // No custom replacement_emp field on this install.
    $this->odoo->defineFields('hr.leave', [
        'id', 'employee_id', 'holiday_status_id', 'date_from', 'date_to',
        'request_date_from', 'request_date_to', 'name', 'state',
        'number_of_days', 'write_date', 'create_date',
    ]);

    $leave = Modules\Booking\Models\PractitionerTimeOff::create([
        'tenant_id' => current_tenant_id(),
        'staff_profile_id' => $staff->id,
        'time_off_type_id' => $type->id,
        'start_date' => '2026-09-10',
        'end_date' => '2026-09-11',
        'is_full_day' => true,
        'days_requested' => 2,
        'reason' => 'Requested from mobile app',
        'status' => 'pending',
    ]);
    $log = runOdooSync($mapping);

    expect($log->records_failed)->toBe(0);
    $leave->refresh();
    expect($leave->odoo_id)->not->toBeNull();

    $odooRecord = $this->odoo->find('hr.leave', $leave->odoo_id);
    expect($odooRecord)->not->toHaveKey('replacement_emp')
        ->and($odooRecord['request_date_from'])->not->toBeEmpty();
});
