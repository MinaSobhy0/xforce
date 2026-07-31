<?php

use Modules\Booking\Models\PractitionerTimeOff;
use Modules\Booking\Models\TimeOffAllocation;
use Modules\Booking\Models\TimeOffType;
use Modules\OdooIntegration\Enums\SyncStatus;
use Modules\Staff\Models\StaffProfile;
use Tests\Odoo\Support\OdooScenario;

/**
 * Import sync: hr.leave.type → time_off_types,
 * hr.leave.allocation → time_off_allocations, hr.leave → practitioner_time_offs.
 */
function seedSyncedStaff($test, $connection): array
{
    $staffMapping = OdooScenario::staffProfiles($connection);

    $odooEmpId = $test->odoo->seed('hr.employee', [
        'name' => 'Sara Ahmed',
        'user_id' => false,
        'work_email' => 'sara@clinic.test',
        'active' => true,
    ]);
    runOdooSync($staffMapping);

    return [StaffProfile::where('odoo_id', $odooEmpId)->firstOrFail(), $odooEmpId];
}

function seedSyncedLeaveType($test, $connection, array $overrides = []): array
{
    $typeMapping = OdooScenario::timeOffTypes($connection);

    $odooTypeId = $test->odoo->seed('hr.leave.type', array_merge([
        'name' => 'Annual Leave',
        'code' => 'ANNUAL',
        'request_unit' => 'day',
        'leave_validation_type' => 'hr',
        'active' => true,
    ], $overrides));
    runOdooSync($typeMapping);

    return [TimeOffType::where('odoo_id', $odooTypeId)->firstOrFail(), $odooTypeId];
}

// ---------------------------------------------------------------------------
// hr.leave.type
// ---------------------------------------------------------------------------

test('imports time off types with translatable names and units', function () {
    $connection = OdooScenario::connection();

    [$type] = seedSyncedLeaveType($this, $connection);

    expect($type->getTranslation('name', 'en'))->toBe('Annual Leave')
        ->and($type->code)->toBe('ANNUAL')
        ->and($type->request_unit)->toBe('day')
        ->and($type->is_active)->toBeTrue()
        ->and($type->requires_approval)->toBeTrue();
});

test('imports hour-based time off types', function () {
    $connection = OdooScenario::connection();

    [$type] = seedSyncedLeaveType($this, $connection, [
        'name' => 'Hourly Permission',
        'code' => 'PERM',
        'request_unit' => 'hour',
    ]);

    expect($type->request_unit)->toBe('hour')
        ->and($type->isHourBased())->toBeTrue();
});

// ---------------------------------------------------------------------------
// hr.leave.allocation
// ---------------------------------------------------------------------------

test('imports allocations linked to staff and leave type', function () {
    $connection = OdooScenario::connection();
    [$staff, $odooEmpId] = seedSyncedStaff($this, $connection);
    [$type, $odooTypeId] = seedSyncedLeaveType($this, $connection);

    $allocMapping = OdooScenario::timeOffAllocations($connection);

    $odooAllocId = $this->odoo->seed('hr.leave.allocation', [
        'employee_id' => [$odooEmpId, 'Sara Ahmed'],
        'holiday_status_id' => [$odooTypeId, 'Annual Leave'],
        'number_of_days' => 21.0,
        'date_from' => '2026-01-01',
        'date_to' => '2026-12-31',
    ]);

    $log = runOdooSync($allocMapping);

    expect($log->status)->toBe(SyncStatus::COMPLETED)
        ->and($log->records_created)->toBe(1);

    $alloc = TimeOffAllocation::where('odoo_id', $odooAllocId)->first();
    expect($alloc)->not->toBeNull()
        ->and($alloc->staff_profile_id)->toBe($staff->id)
        ->and($alloc->time_off_type_id)->toBe($type->id)
        ->and((float) $alloc->allocated_days)->toBe(21.0)
        ->and($alloc->date_from->toDateString())->toBe('2026-01-01')
        ->and($alloc->date_to->toDateString())->toBe('2026-12-31');
});

test('applies the configured date window to the allocation domain', function () {
    $connection = OdooScenario::connection();
    [, $odooEmpId] = seedSyncedStaff($this, $connection);
    [, $odooTypeId] = seedSyncedLeaveType($this, $connection);

    $allocMapping = OdooScenario::timeOffAllocations($connection);

    // Predates the sync_from_date (2026-01-01) — must not be fetched
    $this->odoo->seed('hr.leave.allocation', [
        'employee_id' => [$odooEmpId, 'Sara Ahmed'],
        'holiday_status_id' => [$odooTypeId, 'Annual Leave'],
        'number_of_days' => 15.0,
        'date_from' => '2025-01-01',
        'date_to' => '2025-12-31',
    ]);

    $log = runOdooSync($allocMapping);

    expect($this->odoo->lastDomain('hr.leave.allocation'))->toBe([['date_from', '>=', '2026-01-01']])
        ->and($log->records_processed)->toBe(0)
        ->and(TimeOffAllocation::count())->toBe(0);
});

test('skips allocations whose employee has not been synced', function () {
    $connection = OdooScenario::connection();
    [, $odooTypeId] = seedSyncedLeaveType($this, $connection);

    $allocMapping = OdooScenario::timeOffAllocations($connection);

    $this->odoo->seed('hr.leave.allocation', [
        'employee_id' => [999999, 'Unknown Employee'],
        'holiday_status_id' => [$odooTypeId, 'Annual Leave'],
        'number_of_days' => 10.0,
        'date_from' => '2026-01-01',
        'date_to' => false,
    ]);

    $log = runOdooSync($allocMapping);

    expect($log->status)->toBe(SyncStatus::COMPLETED)
        ->and($log->records_skipped)->toBe(1)
        ->and($log->records_failed)->toBe(0)
        ->and(TimeOffAllocation::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// hr.leave (time off requests)
// ---------------------------------------------------------------------------

test('imports approved leaves with timezone-converted dates and mapped status', function () {
    $connection = OdooScenario::connection();
    [$staff, $odooEmpId] = seedSyncedStaff($this, $connection);
    [$type, $odooTypeId] = seedSyncedLeaveType($this, $connection);

    $timeOffMapping = OdooScenario::timeOffs($connection);

    $odooLeaveId = $this->odoo->seed('hr.leave', [
        'employee_id' => [$odooEmpId, 'Sara Ahmed'],
        'holiday_status_id' => [$odooTypeId, 'Annual Leave'],
        'date_from' => '2026-08-03 06:00:00',   // UTC in Odoo
        'date_to' => '2026-08-05 14:00:00',
        'name' => 'Family vacation',
        'state' => 'validate',
        'number_of_days' => 3.0,
        'number_of_hours_display' => 24.0,
    ]);

    $log = runOdooSync($timeOffMapping);

    expect($log->records_created)->toBe(1);

    $leave = PractitionerTimeOff::where('odoo_id', $odooLeaveId)->first();

    // UTC → Africa/Cairo conversion, then applyOdooImport splits date/time
    $tz = app(\Modules\OdooIntegration\Services\Transform\TimezoneConverter::class);
    $expectedStart = $tz->fromOdoo('2026-08-03 06:00:00');

    expect($leave)->not->toBeNull()
        ->and($leave->staff_profile_id)->toBe($staff->id)
        ->and($leave->time_off_type_id)->toBe($type->id)
        ->and($leave->status)->toBe(PractitionerTimeOff::STATUS_APPROVED)
        ->and($leave->reason)->toBe('Family vacation')
        ->and($leave->start_date->toDateString())->toBe(substr($expectedStart, 0, 10))
        ->and(substr($leave->start_time, 0, 5))->toBe(substr($expectedStart, 11, 5))
        ->and((float) $leave->days_requested)->toBe(3.0);
});

test('maps every odoo leave state to the local status vocabulary', function (string $odooState, string $localStatus) {
    $connection = OdooScenario::connection();
    [, $odooEmpId] = seedSyncedStaff($this, $connection);
    [, $odooTypeId] = seedSyncedLeaveType($this, $connection);

    $timeOffMapping = OdooScenario::timeOffs($connection);

    $odooLeaveId = $this->odoo->seed('hr.leave', [
        'employee_id' => [$odooEmpId, 'Sara Ahmed'],
        'holiday_status_id' => [$odooTypeId, 'Annual Leave'],
        'date_from' => '2026-09-01 06:00:00',
        'date_to' => '2026-09-01 14:00:00',
        'name' => 'State check',
        'state' => $odooState,
        'number_of_days' => 1.0,
        'number_of_hours_display' => 8.0,
    ]);

    runOdooSync($timeOffMapping);

    $leave = PractitionerTimeOff::where('odoo_id', $odooLeaveId)->first();
    expect($leave->status)->toBe($localStatus);
})->with([
    ['draft', PractitionerTimeOff::STATUS_PENDING],
    ['confirm', PractitionerTimeOff::STATUS_PENDING],
    ['validate', PractitionerTimeOff::STATUS_APPROVED],
    ['validate1', PractitionerTimeOff::STATUS_APPROVED],
    ['refuse', PractitionerTimeOff::STATUS_REJECTED],
    ['cancel', PractitionerTimeOff::STATUS_CANCELLED],
]);

test('delta sync leaves locally-known leaves untouched', function () {
    $connection = OdooScenario::connection();
    [, $odooEmpId] = seedSyncedStaff($this, $connection);
    [, $odooTypeId] = seedSyncedLeaveType($this, $connection);

    $timeOffMapping = OdooScenario::timeOffs($connection);

    $odooLeaveId = $this->odoo->seed('hr.leave', [
        'employee_id' => [$odooEmpId, 'Sara Ahmed'],
        'holiday_status_id' => [$odooTypeId, 'Annual Leave'],
        'date_from' => '2026-08-03 06:00:00',
        'date_to' => '2026-08-05 14:00:00',
        'name' => 'Original reason',
        'state' => 'confirm',
        'number_of_days' => 3.0,
        'number_of_hours_display' => 24.0,
    ]);

    runOdooSync($timeOffMapping);

    // Odoo changes, but delta sync must not clobber the local copy
    $this->odoo->write('hr.leave', [$odooLeaveId], ['name' => 'Rewritten in Odoo']);
    $log = runOdooSync($timeOffMapping, 'delta');

    expect($log->records_skipped)->toBe(1);
    expect(PractitionerTimeOff::where('odoo_id', $odooLeaveId)->first()->reason)->toBe('Original reason');
});
