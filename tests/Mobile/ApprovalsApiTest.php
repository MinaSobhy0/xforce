<?php

use Modules\Booking\Models\PractitionerTimeOff;

/**
 * Manager-side approvals: authorization, and the allocation as the hard
 * ceiling at approval time — even if the balance shrank (e.g. an Odoo
 * re-import) after the request was submitted.
 */
function seedApprovalWorld($test): array
{
    [$approver] = $test->createStaffUser();
    [$employee, $employeeStaff] = $test->createStaffUser();
    $employeeStaff->update(['time_off_approver_user_id' => $approver->id]);

    $type = $test->syncLeaveType();
    $allocation = $test->syncAllocation($employeeStaff, $type, ['number_of_days' => 10.0]);

    $from = now()->startOfYear()->addDays(30);
    $requestId = $test->api('POST', 'time-off/requests', [
        'time_off_type_id' => $type->id,
        'start_date' => $from->toDateString(),
        'end_date' => $from->copy()->addDays(5)->toDateString(), // 6 days
    ], $employee)->assertOk()->json('data.id');

    return [$approver, $employee, $employeeStaff, $allocation, $requestId];
}

test('the assigned approver sees the pending request', function () {
    [$approver, , , , $requestId] = seedApprovalWorld($this);

    $items = $this->api('GET', 'approvals/time-off', [], $approver)->assertOk()->json('data');

    expect(collect($items)->pluck('id')->all())->toContain($requestId);
});

test('a random staff member cannot act on the request', function () {
    [, , , , $requestId] = seedApprovalWorld($this);
    [$random] = $this->createStaffUser();

    $this->api('POST', "approvals/time-off/{$requestId}/approve", [], $random)->assertStatus(403);
});

/**
 * Same world, but the employee has NO approver assigned — the state ~98% of
 * staff_profiles are actually in, since the approver columns are nullable
 * and only ever populated by hand.
 */
function seedUnassignedApprovalWorld($test): array
{
    [$employee, $employeeStaff] = $test->createStaffUser();
    $employeeStaff->update([
        'time_off_approver_user_id' => null,
        'attendance_approver_user_id' => null,
    ]);

    $type = $test->syncLeaveType();
    $allocation = $test->syncAllocation($employeeStaff, $type, ['number_of_days' => 10.0]);

    $from = now()->startOfYear()->addDays(30);
    $requestId = $test->api('POST', 'time-off/requests', [
        'time_off_type_id' => $type->id,
        'start_date' => $from->toDateString(),
        'end_date' => $from->copy()->addDays(5)->toDateString(),
    ], $employee)->assertOk()->json('data.id');

    return [$employee, $employeeStaff, $allocation, $requestId];
}

test('an unrelated employee cannot approve a request that has no assigned approver', function () {
    [, , $allocation, $requestId] = seedUnassignedApprovalWorld($this);
    [$stranger] = $this->createStaffUser();

    $this->api('POST', "approvals/time-off/{$requestId}/approve", [], $stranger)->assertStatus(403);

    expect(PractitionerTimeOff::findOrFail($requestId)->isPending())->toBeTrue()
        ->and((float) $allocation->fresh()->used_days)->toBe(0.0);
});

test('an unrelated employee cannot reject a request that has no assigned approver', function () {
    [, , , $requestId] = seedUnassignedApprovalWorld($this);
    [$stranger] = $this->createStaffUser();

    $this->api('POST', "approvals/time-off/{$requestId}/reject", [
        'notes' => 'not my call to make',
    ], $stranger)->assertStatus(403);

    expect(PractitionerTimeOff::findOrFail($requestId)->isPending())->toBeTrue();
});

test('an unrelated employee cannot read a request that has no assigned approver', function () {
    [, , , $requestId] = seedUnassignedApprovalWorld($this);
    [$stranger] = $this->createStaffUser();

    $this->api('GET', "approvals/time-off/{$requestId}", [], $stranger)->assertStatus(403);
});

test('an approve_any holder can still act when no approver is assigned', function () {
    [, , $allocation, $requestId] = seedUnassignedApprovalWorld($this);
    [$hr] = $this->createStaffUser();

    \Spatie\Permission\Models\Permission::findOrCreate('practitioner_time_off.approve_any', 'web');
    $hr->givePermissionTo('practitioner_time_off.approve_any');

    $this->api('POST', "approvals/time-off/{$requestId}/approve", [], $hr)->assertOk();

    expect(PractitionerTimeOff::findOrFail($requestId)->isApproved())->toBeTrue()
        ->and((float) $allocation->fresh()->used_days)->toBe(6.0);
});

/**
 * Attendance violations carry the same "no approver configured" fallback
 * as time-off, and waiving one cancels a colleague's payroll penalty.
 */
function seedUnassignedViolation($test): array
{
    [, $employeeStaff] = $test->createStaffUser();
    $employeeStaff->update(['attendance_approver_user_id' => null]);

    // attendance_violations.attendance_id is NOT NULL — a violation always
    // hangs off a real punch.
    $attendance = \Modules\Attendance\Models\Attendance::create([
        'tenant_id' => $employeeStaff->tenant_id,
        'staff_profile_id' => $employeeStaff->id,
        'attendance_date' => now()->subDay()->toDateString(),
    ]);

    $violation = \Modules\Attendance\Models\AttendanceViolation::create([
        'tenant_id' => $employeeStaff->tenant_id,
        'attendance_id' => $attendance->id,
        'staff_profile_id' => $employeeStaff->id,
        'violation_type' => \Modules\Attendance\Models\AttendanceViolation::TYPE_LATE_CHECKIN,
        'violation_date' => now()->subDay()->toDateString(),
        'violation_minutes' => 25,
        'penalty_amount_minor' => 5000,
        'status' => \Modules\Attendance\Models\AttendanceViolation::STATUS_PENDING,
    ]);

    return [$employeeStaff, $violation];
}

test('an unrelated employee cannot waive a violation that has no assigned approver', function () {
    [, $violation] = seedUnassignedViolation($this);
    [$stranger] = $this->createStaffUser();

    $this->api('POST', "approvals/violations/{$violation->id}/waive", [
        'reason' => 'clearing my colleague penalty',
    ], $stranger)->assertStatus(403);

    expect($violation->fresh()->status)
        ->toBe(\Modules\Attendance\Models\AttendanceViolation::STATUS_PENDING);
});

test('an unrelated employee cannot approve or read a violation with no assigned approver', function () {
    [, $violation] = seedUnassignedViolation($this);
    [$stranger] = $this->createStaffUser();

    $this->api('GET', "approvals/violations/{$violation->id}", [], $stranger)->assertStatus(403);
    $this->api('POST', "approvals/violations/{$violation->id}/approve", [], $stranger)->assertStatus(403);

    expect($violation->fresh()->status)
        ->toBe(\Modules\Attendance\Models\AttendanceViolation::STATUS_PENDING);
});

test('the assigned attendance approver can still waive', function () {
    [$employeeStaff, $violation] = seedUnassignedViolation($this);
    [$approver] = $this->createStaffUser();
    $employeeStaff->update(['attendance_approver_user_id' => $approver->id]);

    $this->api('POST', "approvals/violations/{$violation->id}/waive", [
        'reason' => 'traffic closure on the ring road',
    ], $approver)->assertOk();

    expect($violation->fresh()->status)
        ->toBe(\Modules\Attendance\Models\AttendanceViolation::STATUS_WAIVED);
});

test('approval deducts exactly the requested days from the allocation', function () {
    [$approver, , $employeeStaff, $allocation, $requestId] = seedApprovalWorld($this);

    $this->api('POST', "approvals/time-off/{$requestId}/approve", [], $approver)->assertOk();

    expect((float) $allocation->fresh()->used_days)->toBe(6.0)
        ->and(PractitionerTimeOff::findOrFail($requestId)->isApproved())->toBeTrue();
});

test('approval fails when the allocation no longer covers the request', function () {
    [$approver, , , $allocation, $requestId] = seedApprovalWorld($this);

    // Balance shrank after submission (e.g. Odoo re-import): 6 > 4.
    $allocation->update(['allocated_days' => 4.0]);

    $this->api('POST', "approvals/time-off/{$requestId}/approve", [], $approver)->assertStatus(422);

    $request = PractitionerTimeOff::findOrFail($requestId);
    expect($request->isPending())->toBeTrue()
        ->and((float) $allocation->fresh()->used_days)->toBe(0.0);
});

test('approval fails when the allocation row disappeared entirely', function () {
    [$approver, , , $allocation, $requestId] = seedApprovalWorld($this);

    $allocation->delete();

    $this->api('POST', "approvals/time-off/{$requestId}/approve", [], $approver)->assertStatus(422);
    expect(PractitionerTimeOff::findOrFail($requestId)->isPending())->toBeTrue();
});

test('rejection needs a reason and leaves the balance untouched', function () {
    [$approver, , , $allocation, $requestId] = seedApprovalWorld($this);

    $this->api('POST', "approvals/time-off/{$requestId}/reject", [], $approver)->assertStatus(422);

    $this->api('POST', "approvals/time-off/{$requestId}/reject", [
        'notes' => 'Coverage gap that week',
    ], $approver)->assertOk();

    expect(PractitionerTimeOff::findOrFail($requestId)->isRejected())->toBeTrue()
        ->and((float) $allocation->fresh()->used_days)->toBe(0.0);
});
