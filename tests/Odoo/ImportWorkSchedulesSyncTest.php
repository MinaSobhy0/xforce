<?php

use Modules\Attendance\Models\Attendance;
use Modules\Booking\Models\PractitionerScheduleAssignment;
use Modules\Booking\Models\WorkSchedule;
use Modules\Staff\Models\StaffProfile;
use Tests\Odoo\Support\OdooScenario;

/**
 * Import sync: resource.calendar → work_schedules (weekly hours built from
 * the calendar's attendance lines; employee↔calendar links reconciled into
 * practitioner_schedule_assignments after each run) — and the hr.attendance
 * import path (Odoo datetimes split into local date + time columns).
 */
function seedCalendar($test, int $calId): void
{
    // Mon-Thu 08:30-16:30 with a 12:00-13:00 break; Friday off; Sunday single block.
    foreach ([0, 1, 2, 3] as $odooDay) { // Odoo: 0=Monday
        $test->odoo->seed('resource.calendar.attendance', [
            'calendar_id' => [$calId, 'Standard'], 'dayofweek' => (string) $odooDay,
            'hour_from' => 8.5, 'hour_to' => 12.0,
        ]);
        $test->odoo->seed('resource.calendar.attendance', [
            'calendar_id' => [$calId, 'Standard'], 'dayofweek' => (string) $odooDay,
            'hour_from' => 13.0, 'hour_to' => 16.5,
        ]);
    }
    $test->odoo->seed('resource.calendar.attendance', [
        'calendar_id' => [$calId, 'Standard'], 'dayofweek' => '6', // Odoo Sunday
        'hour_from' => 9.0, 'hour_to' => 15.0,
    ]);
}

test('imports calendars with weekly hours derived from attendance lines', function () {
    $connection = OdooScenario::connection();
    $mapping = OdooScenario::workSchedules($connection);

    $calId = $this->odoo->seed('resource.calendar', ['name' => 'Standard 40h', 'active' => true]);
    seedCalendar($this, $calId);

    runOdooSync($mapping);

    $schedule = WorkSchedule::where('odoo_id', $calId)->firstOrFail();

    // Odoo Monday(0) → Carbon Monday(1); two lines = break window
    $monday = $schedule->weekly_hours[1] ?? $schedule->weekly_hours['1'];
    expect($monday['is_working'])->toBeTrue()
        ->and($monday['start_time'])->toBe('08:30')
        ->and($monday['end_time'])->toBe('16:30')
        ->and($monday['break_start'])->toBe('12:00')
        ->and($monday['break_end'])->toBe('13:00');

    // Odoo Sunday(6) → Carbon Sunday(0); single block, no break
    $sunday = $schedule->weekly_hours[0] ?? $schedule->weekly_hours['0'];
    expect($sunday['start_time'])->toBe('09:00')
        ->and($sunday['end_time'])->toBe('15:00')
        ->and($sunday)->not->toHaveKey('break_start');

    expect($schedule->working_days)->toContain(0, 1, 2, 3, 4)
        ->and((float) $schedule->required_hours_per_week)->toBe(34.0);
});

test('reconciles employee schedule assignments after the calendar sync', function () {
    $connection = OdooScenario::connection();

    // Employee linked to the calendar in Odoo
    $staffMapping = OdooScenario::staffProfiles($connection);
    $calId = $this->odoo->seed('resource.calendar', ['name' => 'Standard', 'active' => true]);
    seedCalendar($this, $calId);
    $empId = $this->odoo->seed('hr.employee', [
        'name' => 'Sara Ahmed', 'user_id' => false, 'work_email' => 'sara@clinic.test',
        'active' => true, 'resource_calendar_id' => [$calId, 'Standard'],
    ]);
    runOdooSync($staffMapping);

    $mapping = OdooScenario::workSchedules($connection);
    runOdooSync($mapping);

    $staff = StaffProfile::where('odoo_id', $empId)->firstOrFail();
    $schedule = WorkSchedule::where('odoo_id', $calId)->firstOrFail();

    $assignment = PractitionerScheduleAssignment::where('staff_profile_id', $staff->id)
        ->where('is_active', true)->firstOrFail();
    expect($assignment->work_schedule_id)->toBe($schedule->id);

    // Calendar changes in Odoo → old assignment retired, new one active
    $cal2 = $this->odoo->seed('resource.calendar', ['name' => 'Night Shift', 'active' => true]);
    $this->odoo->seed('resource.calendar.attendance', [
        'calendar_id' => [$cal2, 'Night Shift'], 'dayofweek' => '0',
        'hour_from' => 20.0, 'hour_to' => 23.0,
    ]);
    $this->odoo->write('hr.employee', [$empId], ['resource_calendar_id' => [$cal2, 'Night Shift']]);

    runOdooSync($mapping);

    $active = PractitionerScheduleAssignment::where('staff_profile_id', $staff->id)
        ->where('is_active', true)->get();
    expect($active)->toHaveCount(1)
        ->and($active->first()->work_schedule_id)->toBe(WorkSchedule::where('odoo_id', $cal2)->firstOrFail()->id);
});

// ---------------------------------------------------------------------------
// hr.attendance import (Odoo → app, the daily sweep direction)
// ---------------------------------------------------------------------------

test('imports odoo attendances splitting datetimes into local date and times', function () {
    $connection = OdooScenario::connection();

    $staffMapping = OdooScenario::staffProfiles($connection);
    $empId = $this->odoo->seed('hr.employee', [
        'name' => 'Sara Ahmed', 'user_id' => false, 'work_email' => 'sara@clinic.test', 'active' => true,
    ]);
    runOdooSync($staffMapping);
    $staff = StaffProfile::where('odoo_id', $empId)->firstOrFail();

    $mapping = OdooScenario::attendances($connection, ['sync_direction' => 'bidirectional']);

    $odooAttId = $this->odoo->seed('hr.attendance', [
        'employee_id' => [$empId, 'Sara Ahmed'],
        'check_in' => '2026-07-28 05:30:00',   // UTC → 08:30 Cairo
        'check_out' => '2026-07-28 13:30:00',  // UTC → 16:30 Cairo
        'worked_hours' => 8.0,
    ]);

    runOdooSync($mapping);

    $att = Attendance::where('odoo_id', $odooAttId)->firstOrFail();
    expect($att->staff_profile_id)->toBe($staff->id)
        ->and($att->attendance_date->toDateString())->toBe('2026-07-28')
        ->and($att->check_in_time->format('H:i'))->toBe('08:30')
        ->and($att->check_out_time->format('H:i'))->toBe('16:30')
        ->and((float) $att->working_hours)->toBe(8.0);
});

test('imports open odoo attendances with no check_out', function () {
    $connection = OdooScenario::connection();

    $staffMapping = OdooScenario::staffProfiles($connection);
    $empId = $this->odoo->seed('hr.employee', [
        'name' => 'Sara Ahmed', 'user_id' => false, 'work_email' => 'sara@clinic.test', 'active' => true,
    ]);
    runOdooSync($staffMapping);

    $mapping = OdooScenario::attendances($connection, ['sync_direction' => 'bidirectional']);

    $odooAttId = $this->odoo->seed('hr.attendance', [
        'employee_id' => [$empId, 'Sara Ahmed'],
        'check_in' => '2026-07-29 05:00:00',
        'check_out' => false,
        'worked_hours' => 0,
    ]);

    runOdooSync($mapping);

    $att = Attendance::where('odoo_id', $odooAttId)->firstOrFail();
    expect($att->check_out_time)->toBeNull()
        ->and($att->attendance_date->toDateString())->toBe('2026-07-29');
});
