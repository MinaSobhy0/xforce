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
