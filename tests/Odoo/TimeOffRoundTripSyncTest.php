<?php

use Modules\Booking\Models\PractitionerTimeOff;
use Modules\Booking\Models\TimeOffType;
use Modules\OdooIntegration\Models\OdooSyncRecord;
use Modules\Staff\Models\StaffProfile;
use Tests\Odoo\Support\OdooScenario;

/**
 * Bidirectional flow for time off requests (hr.leave) — the mobile app
 * scenario: request created locally, pushed to Odoo, workflow state changes
 * flow in both directions.
 */
function roundTripFixtures($test, $connection): array
{
    $staffMapping = OdooScenario::staffProfiles($connection);
    $odooEmpId = $test->odoo->seed('hr.employee', [
        'name' => 'Sara Ahmed', 'user_id' => false, 'work_email' => 'sara@clinic.test', 'active' => true,
    ]);
    runOdooSync($staffMapping);
    $staff = StaffProfile::where('odoo_id', $odooEmpId)->firstOrFail();

    $typeMapping = OdooScenario::timeOffTypes($connection);
    $odooTypeId = $test->odoo->seed('hr.leave.type', [
        'name' => 'Annual Leave', 'code' => 'ANNUAL', 'request_unit' => 'day',
        'leave_validation_type' => 'hr', 'active' => true,
    ]);
    runOdooSync($typeMapping);
    $type = TimeOffType::where('odoo_id', $odooTypeId)->firstOrFail();

    return [$staff, $odooEmpId, $type, $odooTypeId];
}

function makeLocalLeave(int $staffId, int $typeId, array $overrides = []): PractitionerTimeOff
{
    return PractitionerTimeOff::create(array_merge([
        'tenant_id' => current_tenant_id(),
        'staff_profile_id' => $staffId,
        'time_off_type_id' => $typeId,
        'start_date' => '2026-08-10',
        'end_date' => '2026-08-12',
        'is_full_day' => true,
        'days_requested' => 3,
        'reason' => 'Requested from mobile app',
        'status' => PractitionerTimeOff::STATUS_PENDING,
    ], $overrides));
}

test('a locally created request is pushed to odoo without a state write', function () {
    $connection = OdooScenario::connection();
    [$staff, $odooEmpId, $type, $odooTypeId] = roundTripFixtures($this, $connection);
    $mapping = OdooScenario::timeOffs($connection);

    $leave = makeLocalLeave($staff->id, $type->id);

    $log = runOdooSync($mapping);

    expect($log->records_created)->toBe(1);

    $leave->refresh();
    expect($leave->odoo_id)->not->toBeNull();

    $created = $this->odoo->callsTo('create', 'hr.leave')[0]['args']['values'];

    expect($created['employee_id'])->toBe($odooEmpId)
        ->and($created['holiday_status_id'])->toBe($odooTypeId)
        ->and($created['name'])->toBe('Requested from mobile app')
        // hr.leave state transitions must go through workflow actions, so
        // create never writes `state` directly (Odoo defaults it to confirm)
        ->and($created)->not->toHaveKey('state')
        ->and($created)->not->toHaveKey('__odoo_actions')
        // custom required field on some Odoo installs, defaulted to self
        ->and($created['replacement_emp'])->toBe($odooEmpId);
});

test('hour-based requests export half-hour selection strings, not floats', function () {
    $connection = OdooScenario::connection();
    [$staff] = roundTripFixtures($this, $connection);
    $mapping = OdooScenario::timeOffs($connection);

    $odooTypeId = $this->odoo->seed('hr.leave.type', [
        'name' => 'Excuses', 'code' => 'EXCUSE', 'request_unit' => 'hour',
        'leave_validation_type' => 'hr', 'active' => true,
    ]);
    runOdooSync(\Modules\OdooIntegration\Models\OdooEntityMapping::where('odoo_model', 'hr.leave.type')->firstOrFail());
    $hourType = TimeOffType::where('odoo_id', $odooTypeId)->firstOrFail();

    // 09:00 exported as float 9.0 raised "ValueError: Wrong value for
    // hr.leave.request_hour_from: 9.0" — the field is a Selection keyed
    // by half-hour strings ('9', '10.5') in Odoo 17.
    $leave = makeLocalLeave($staff->id, $hourType->id, [
        'start_date' => '2026-08-13', 'end_date' => '2026-08-13',
        'is_full_day' => false, 'start_time' => '09:00', 'end_time' => '10:30',
        'days_requested' => 0.19, 'hours_requested' => 1.5,
    ]);

    $log = runOdooSync($mapping);

    expect($log->records_failed)->toBe(0);
    $leave->refresh();
    expect($leave->odoo_id)->not->toBeNull();

    $created = $this->odoo->callsTo('create', 'hr.leave')[0]['args']['values'];
    expect($created['request_unit_hours'])->toBeTrue()
        ->and($created['request_hour_from'])->toBeString()->toBe('9')
        ->and($created['request_hour_to'])->toBeString()->toBe('10.5');
});

test('approving locally triggers the action_approve workflow in odoo', function () {
    $connection = OdooScenario::connection();
    [$staff, , $type] = roundTripFixtures($this, $connection);
    $mapping = OdooScenario::timeOffs($connection);

    $leave = makeLocalLeave($staff->id, $type->id);
    runOdooSync($mapping);
    $leave->refresh();

    $leave->update(['status' => PractitionerTimeOff::STATUS_APPROVED]);

    // Delta sync: import skips known records, export pushes local changes.
    // (A full sync intentionally overrides local state from Odoo.)
    $log = runOdooSync($mapping, 'delta');

    expect($log->records_failed)->toBe(0);

    $actions = collect($this->odoo->actions)->where('model', 'hr.leave');
    expect($actions->pluck('method'))->toContain('action_approve')
        ->and($this->odoo->find('hr.leave', $leave->odoo_id)['state'])->toBe('validate');
});

test('rejecting locally triggers action_refuse even when odoo faults with marshal none', function () {
    $connection = OdooScenario::connection();
    [$staff, , $type] = roundTripFixtures($this, $connection);
    $mapping = OdooScenario::timeOffs($connection);

    $leave = makeLocalLeave($staff->id, $type->id);
    runOdooSync($mapping);
    $leave->refresh();

    // Odoo's own RPC layer fails to marshal the None returned by action_*
    // methods even though the transition applied — the sync must treat that
    // specific fault as success.
    $this->odoo->marshalNoneOnActions = true;

    $leave->update(['status' => PractitionerTimeOff::STATUS_REJECTED]);

    $log = runOdooSync($mapping, 'delta');

    expect($log->records_failed)->toBe(0)
        ->and($this->odoo->find('hr.leave', $leave->odoo_id)['state'])->toBe('refuse');

    $syncRecord = OdooSyncRecord::where('local_id', $leave->id)->first();
    expect($syncRecord->sync_status)->toBe(OdooSyncRecord::STATUS_SYNCED);
});

test('an approval done in odoo flows back into the local request', function () {
    $connection = OdooScenario::connection();
    [$staff, , $type] = roundTripFixtures($this, $connection);
    $mapping = OdooScenario::timeOffs($connection);

    $leave = makeLocalLeave($staff->id, $type->id);
    runOdooSync($mapping);
    $leave->refresh();

    // HR approves inside Odoo
    $this->odoo->execute('hr.leave', 'action_approve', [[$leave->odoo_id]]);

    runOdooSync($mapping);

    expect($leave->fresh()->status)->toBe(PractitionerTimeOff::STATUS_APPROVED);
});

test('under local wins the local decision beats a conflicting odoo edit', function () {
    $connection = OdooScenario::connection();
    [$staff, , $type] = roundTripFixtures($this, $connection);
    $mapping = OdooScenario::timeOffs($connection);   // conflict_resolution = local_wins

    $leave = makeLocalLeave($staff->id, $type->id);
    runOdooSync($mapping);
    $leave->refresh();

    // Diverge: Odoo reviewer edits the description while the local side approves
    $this->odoo->write('hr.leave', [$leave->odoo_id], ['name' => 'Edited in Odoo']);
    $leave->update(['status' => PractitionerTimeOff::STATUS_APPROVED]);

    $log = runOdooSync($mapping, 'delta');

    expect($log->conflicts_detected)->toBe(0)
        ->and($log->records_failed)->toBe(0);

    $odooRecord = $this->odoo->find('hr.leave', $leave->odoo_id);
    expect($odooRecord['state'])->toBe('validate')
        ->and($odooRecord['name'])->toBe('Requested from mobile app');
});

test('odoo-originated requests and local requests coexist in one bidirectional sync', function () {
    $connection = OdooScenario::connection();
    [$staff, $odooEmpId, $type, $odooTypeId] = roundTripFixtures($this, $connection);
    $mapping = OdooScenario::timeOffs($connection);

    // One request created in Odoo…
    $odooLeaveId = $this->odoo->seed('hr.leave', [
        'employee_id' => [$odooEmpId, 'Sara Ahmed'],
        'holiday_status_id' => [$odooTypeId, 'Annual Leave'],
        'date_from' => '2026-09-01 06:00:00',
        'date_to' => '2026-09-02 14:00:00',
        'name' => 'Created in Odoo',
        'state' => 'confirm',
        'number_of_days' => 2.0,
        'number_of_hours_display' => 16.0,
    ]);

    // …and one created locally
    $local = makeLocalLeave($staff->id, $type->id);

    $log = runOdooSync($mapping);

    expect($log->records_failed)->toBe(0);

    // Odoo one imported
    $imported = PractitionerTimeOff::where('odoo_id', $odooLeaveId)->first();
    expect($imported)->not->toBeNull()
        ->and($imported->reason)->toBe('Created in Odoo');

    // Local one exported
    expect($local->fresh()->odoo_id)->not->toBeNull()
        ->and(PractitionerTimeOff::count())->toBe(2)
        ->and(count($this->odoo->records['hr.leave']))->toBe(2);
});
