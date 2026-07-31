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
