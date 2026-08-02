<?php

use Modules\Booking\Models\PractitionerTimeOff;
use Modules\Booking\Models\TimeOffAllocation;

/**
 * Mobile time-off API: the Odoo allocation is the single source of truth.
 * Balances shown, types offered, and requests accepted must all derive
 * exactly from allocations imported from Odoo — never from local defaults.
 */

// ---------------------------------------------------------------------------
// Balance mirrors Odoo exactly
// ---------------------------------------------------------------------------

test('balance mirrors the Odoo allocation exactly', function () {
    [$user, $staff] = $this->createStaffUser();
    $type = $this->syncLeaveType();
    $this->syncAllocation($staff, $type, ['number_of_days' => 10.0]);

    $response = $this->api('GET', 'time-off/balance', [], $user)->assertOk();

    $balances = $response->json('data');
    expect($balances)->toHaveCount(1)
        ->and($balances[0]['allocated'])->toEqual(10)
        ->and($balances[0]['used'])->toEqual(0)
        ->and($balances[0]['remaining'])->toEqual(10)
        ->and($balances[0]['code'])->toBe('ANNUAL');
});

test('leaves taken in Odoo reduce the local balance', function () {
    [$user, $staff] = $this->createStaffUser();
    $type = $this->syncLeaveType();
    $this->syncAllocation($staff, $type, [
        'number_of_days' => 10.0,
        'leaves_taken' => 3.0,
    ]);

    $balances = $this->api('GET', 'time-off/balance', [], $user)->assertOk()->json('data');

    expect($balances[0]['used'])->toEqual(3)
        ->and($balances[0]['remaining'])->toEqual(7);
});

test('hour-based allocations convert Odoo days to hours', function () {
    [$user, $staff] = $this->createStaffUser();
    $type = $this->syncLeaveType(['name' => 'Permission', 'code' => 'PERM', 'request_unit' => 'hour']);
    $this->syncAllocation($staff, $type, ['number_of_days' => 2.0]);

    $balances = $this->api('GET', 'time-off/balance', [], $user)->assertOk()->json('data');

    // 2 Odoo days x hours_per_day (8) = 16 hours stored locally
    expect($balances[0]['allocated'])->toEqual(16)
        ->and($balances[0]['remaining'])->toEqual(16);
});

// ---------------------------------------------------------------------------
// Types offered = types allocated
// ---------------------------------------------------------------------------

test('types lists only types the employee holds an allocation for', function () {
    [$user, $staff] = $this->createStaffUser();
    $allocated = $this->syncLeaveType(['name' => 'Annual', 'code' => 'ANNUAL']);
    $this->syncLeaveType(['name' => 'Unallocated', 'code' => 'SICK']);
    $this->syncAllocation($staff, $allocated);

    $types = $this->api('GET', 'time-off/types', [], $user)->assertOk()->json('data');

    expect(collect($types)->pluck('code')->all())->toBe(['ANNUAL']);
});

test('types hides allocations with nothing left to request', function () {
    [$user, $staff] = $this->createStaffUser();
    $type = $this->syncLeaveType();
    $this->syncAllocation($staff, $type, ['number_of_days' => 2.0, 'leaves_taken' => 2.0]);

    expect($this->api('GET', 'time-off/types', [], $user)->assertOk()->json('data'))->toBeEmpty();
});

test('types remaining is net of pending requests', function () {
    [$user, $staff] = $this->createStaffUser();
    $type = $this->syncLeaveType();
    $this->syncAllocation($staff, $type, ['number_of_days' => 10.0]);

    $this->api('POST', 'time-off/requests', [
        'time_off_type_id' => $type->id,
        'start_date' => now()->startOfYear()->addDays(10)->toDateString(),
        'end_date' => now()->startOfYear()->addDays(13)->toDateString(),
    ], $user)->assertOk();

    $types = $this->api('GET', 'time-off/types', [], $user)->assertOk()->json('data');

    expect($types[0]['remaining'])->toEqual(6);
});

// ---------------------------------------------------------------------------
// Requests restricted to the allocation
// ---------------------------------------------------------------------------

function timeOffPayload($type, string $from, string $to, array $extra = []): array
{
    return array_merge([
        'time_off_type_id' => $type->id,
        'start_date' => $from,
        'end_date' => $to,
    ], $extra);
}

test('a request within the allocation is accepted as pending', function () {
    [$user, $staff] = $this->createStaffUser();
    $type = $this->syncLeaveType();
    $this->syncAllocation($staff, $type, ['number_of_days' => 10.0]);

    $from = now()->startOfYear()->addDays(30);
    $this->api('POST', 'time-off/requests', timeOffPayload($type, $from->toDateString(), $from->copy()->addDays(2)->toDateString()), $user)
        ->assertOk()
        ->assertJsonPath('data.status', PractitionerTimeOff::STATUS_PENDING);

    expect(PractitionerTimeOff::forStaffProfile($staff->id)->count())->toBe(1);
});

test('a request exceeding the remaining balance is refused', function () {
    [$user, $staff] = $this->createStaffUser();
    $type = $this->syncLeaveType();
    $this->syncAllocation($staff, $type, ['number_of_days' => 10.0]);

    $from = now()->startOfYear()->addDays(30);
    $this->api('POST', 'time-off/requests', timeOffPayload($type, $from->toDateString(), $from->copy()->addDays(14)->toDateString()), $user)
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'INSUFFICIENT_BALANCE');

    expect(PractitionerTimeOff::forStaffProfile($staff->id)->count())->toBe(0);
});

test('a request equal to the exact remaining balance is accepted', function () {
    [$user, $staff] = $this->createStaffUser();
    $type = $this->syncLeaveType();
    $this->syncAllocation($staff, $type, ['number_of_days' => 5.0]);

    $from = now()->startOfYear()->addDays(30);
    $this->api('POST', 'time-off/requests', timeOffPayload($type, $from->toDateString(), $from->copy()->addDays(4)->toDateString()), $user)
        ->assertOk();
});

test('pending requests count against the balance for later requests', function () {
    [$user, $staff] = $this->createStaffUser();
    $type = $this->syncLeaveType();
    $this->syncAllocation($staff, $type, ['number_of_days' => 10.0]);

    $first = now()->startOfYear()->addDays(30);
    $this->api('POST', 'time-off/requests', timeOffPayload($type, $first->toDateString(), $first->copy()->addDays(5)->toDateString()), $user)
        ->assertOk(); // 6 days pending

    $second = now()->startOfYear()->addDays(60);
    $this->api('POST', 'time-off/requests', timeOffPayload($type, $second->toDateString(), $second->copy()->addDays(4)->toDateString()), $user)
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'INSUFFICIENT_BALANCE'); // 6 + 5 > 10

    $third = now()->startOfYear()->addDays(90);
    $this->api('POST', 'time-off/requests', timeOffPayload($type, $third->toDateString(), $third->copy()->addDays(3)->toDateString()), $user)
        ->assertOk(); // 6 + 4 = 10 fits exactly
});

test('a request outside any allocation period is refused and no allocation is minted', function () {
    [$user, $staff] = $this->createStaffUser();
    $type = $this->syncLeaveType();
    $this->syncAllocation($staff, $type); // covers current year only

    $before = TimeOffAllocation::count();

    $nextYear = now()->addYear()->startOfYear()->addDays(10);
    $this->api('POST', 'time-off/requests', timeOffPayload($type, $nextYear->toDateString(), $nextYear->copy()->addDays(2)->toDateString()), $user)
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'NO_ALLOCATION');

    // The backend must NOT have created a default allocation row.
    expect(TimeOffAllocation::count())->toBe($before);
});

test('a type with no allocation at all cannot be requested', function () {
    [$user, $staff] = $this->createStaffUser();
    $type = $this->syncLeaveType();

    $from = now()->startOfYear()->addDays(30);
    $this->api('POST', 'time-off/requests', timeOffPayload($type, $from->toDateString(), $from->toDateString()), $user)
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'NO_ALLOCATION');
});

// ---------------------------------------------------------------------------
// Request-shape rules
// ---------------------------------------------------------------------------

test('overlapping requests are refused', function () {
    [$user, $staff] = $this->createStaffUser();
    $type = $this->syncLeaveType();
    $this->syncAllocation($staff, $type);

    $from = now()->startOfYear()->addDays(30);
    $this->api('POST', 'time-off/requests', timeOffPayload($type, $from->toDateString(), $from->copy()->addDays(2)->toDateString()), $user)->assertOk();

    $this->api('POST', 'time-off/requests', timeOffPayload($type, $from->copy()->addDay()->toDateString(), $from->copy()->addDays(4)->toDateString()), $user)
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'DATES_OVERLAP');
});

test('hour-based requests require start and end times', function () {
    [$user, $staff] = $this->createStaffUser();
    $type = $this->syncLeaveType(['name' => 'Permission', 'code' => 'PERM', 'request_unit' => 'hour']);
    $this->syncAllocation($staff, $type, ['number_of_days' => 2.0]);

    $day = now()->startOfYear()->addDays(30)->toDateString();
    $this->api('POST', 'time-off/requests', timeOffPayload($type, $day, $day), $user)
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'TIMES_REQUIRED');
});

test('hour-based requests with a reversed time range are refused', function () {
    [$user, $staff] = $this->createStaffUser();
    $type = $this->syncLeaveType(['name' => 'Permission', 'code' => 'PERM', 'request_unit' => 'hour']);
    $this->syncAllocation($staff, $type, ['number_of_days' => 2.0]);

    $day = now()->startOfYear()->addDays(30)->toDateString();
    $this->api('POST', 'time-off/requests', timeOffPayload($type, $day, $day, [
        'start_time' => '17:00',
        'end_time' => '09:00',
    ]), $user)
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'INVALID_DURATION');
});

test('an hour-based request deducts hours, not days', function () {
    [$user, $staff] = $this->createStaffUser();
    $type = $this->syncLeaveType(['name' => 'Permission', 'code' => 'PERM', 'request_unit' => 'hour']);
    $this->syncAllocation($staff, $type, ['number_of_days' => 2.0]); // 16h

    $day = now()->startOfYear()->addDays(30)->toDateString();
    $this->api('POST', 'time-off/requests', timeOffPayload($type, $day, $day, [
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]), $user)->assertOk();

    $request = PractitionerTimeOff::forStaffProfile($staff->id)->firstOrFail();
    expect((float) $request->hours_requested)->toEqual(3);
});

// ---------------------------------------------------------------------------
// Cancel
// ---------------------------------------------------------------------------

test('a pending request can be cancelled and frees the balance', function () {
    [$user, $staff] = $this->createStaffUser();
    $type = $this->syncLeaveType();
    $this->syncAllocation($staff, $type, ['number_of_days' => 10.0]);

    $from = now()->startOfYear()->addDays(30);
    $id = $this->api('POST', 'time-off/requests', timeOffPayload($type, $from->toDateString(), $from->copy()->addDays(5)->toDateString()), $user)
        ->assertOk()->json('data.id');

    $this->api('POST', "time-off/requests/{$id}/cancel", [], $user)->assertOk();

    // Balance is fully available again.
    $types = $this->api('GET', 'time-off/types', [], $user)->assertOk()->json('data');
    expect($types[0]['remaining'])->toEqual(10);
});

test('an approved request cannot be cancelled from the app', function () {
    [$user, $staff] = $this->createStaffUser();
    $type = $this->syncLeaveType();
    $this->syncAllocation($staff, $type);

    $from = now()->startOfYear()->addDays(30);
    $id = $this->api('POST', 'time-off/requests', timeOffPayload($type, $from->toDateString(), $from->toDateString()), $user)
        ->assertOk()->json('data.id');

    PractitionerTimeOff::findOrFail($id)->approve((string) $user->id);

    $this->api('POST', "time-off/requests/{$id}/cancel", [], $user)->assertStatus(400);
});

// ---------------------------------------------------------------------------
// Scoping
// ---------------------------------------------------------------------------

test('an employee cannot see another employee\'s request', function () {
    [$alice, $aliceStaff] = $this->createStaffUser();
    [$bob] = $this->createStaffUser();
    $type = $this->syncLeaveType();
    $this->syncAllocation($aliceStaff, $type);

    $from = now()->startOfYear()->addDays(30);
    $id = $this->api('POST', 'time-off/requests', timeOffPayload($type, $from->toDateString(), $from->toDateString()), $alice)
        ->assertOk()->json('data.id');

    $this->api('GET', "time-off/requests/{$id}", [], $bob)->assertStatus(404);
});

// ---------------------------------------------------------------------------
// Work-schedule-aware day counting (like Odoo)
// ---------------------------------------------------------------------------

test('requested days count only working days per the schedule', function () {
    [$user, $staff] = $this->createStaffUser();
    $this->assignSchedule($staff); // Sun-Thu working, Fri+Sat off
    $type = $this->syncLeaveType();
    $this->syncAllocation($staff, $type, ['number_of_days' => 10.0]);

    // Thursday → Sunday: 4 calendar days, only Thu + Sun are working days.
    $thursday = now()->addWeeks(2)->next(\Carbon\Carbon::THURSDAY);
    $this->api('POST', 'time-off/requests', timeOffPayload($type, $thursday->toDateString(), $thursday->copy()->addDays(3)->toDateString()), $user)
        ->assertOk();

    $request = PractitionerTimeOff::forStaffProfile($staff->id)->firstOrFail();
    expect((float) $request->days_requested)->toBe(2.0);

    // Balance reflects the working-day deduction, not calendar days.
    $types = $this->api('GET', 'time-off/types', [], $user)->assertOk()->json('data');
    expect($types[0]['remaining'])->toEqual(8);
});

test('a request spanning only days off is refused', function () {
    [$user, $staff] = $this->createStaffUser();
    $this->assignSchedule($staff); // Fri+Sat off
    $type = $this->syncLeaveType();
    $this->syncAllocation($staff, $type, ['number_of_days' => 10.0]);

    $friday = now()->addWeeks(2)->next(\Carbon\Carbon::FRIDAY);
    $this->api('POST', 'time-off/requests', timeOffPayload($type, $friday->toDateString(), $friday->copy()->addDay()->toDateString()), $user)
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'NON_WORKING_PERIOD');
});

test('staff without a schedule keep plain calendar-day counting', function () {
    [$user, $staff] = $this->createStaffUser();
    $type = $this->syncLeaveType();
    $this->syncAllocation($staff, $type, ['number_of_days' => 10.0]);

    $from = now()->startOfYear()->addDays(30);
    $this->api('POST', 'time-off/requests', timeOffPayload($type, $from->toDateString(), $from->copy()->addDays(3)->toDateString()), $user)
        ->assertOk();

    expect((float) PractitionerTimeOff::forStaffProfile($staff->id)->firstOrFail()->days_requested)->toBe(4.0);
});
