<?php

use Modules\Attendance\Models\Attendance;
use Modules\Attendance\Models\AttendanceTypeSetting;

/**
 * Mobile attendance API: geofence enforcement is a backend decision — for
 * live punches and for offline-recorded punches synced later. Coordinates
 * are persisted and every out-of-geofence punch is refused.
 */

// ---------------------------------------------------------------------------
// Live check-in / check-out
// ---------------------------------------------------------------------------

test('geofence check-in inside the fence succeeds and persists the verified location', function () {
    [$user, $staff] = $this->createStaffUser();
    $this->enableGeofence();

    $this->api('POST', 'attendance/check-in', [
        'method' => 'geofence',
        'latitude' => self::GEO_LAT,
        'longitude' => self::GEO_LNG,
    ], $user)->assertOk();

    $attendance = Attendance::forStaff($staff->id)->today()->firstOrFail();
    expect((float) $attendance->check_in_latitude)->toBe(self::GEO_LAT)
        ->and((float) $attendance->check_in_longitude)->toBe(self::GEO_LNG)
        ->and($attendance->location_verified)->toBeTrue();
});

test('geofence check-in outside the fence is refused', function () {
    [$user, $staff] = $this->createStaffUser();
    $this->enableGeofence();

    $this->api('POST', 'attendance/check-in', [
        'method' => 'geofence',
        'latitude' => self::FAR_LAT,
        'longitude' => self::FAR_LNG,
    ], $user)->assertStatus(422)->assertJsonPath('error_code', 'OUT_OF_GEOFENCE');

    expect(Attendance::forStaff($staff->id)->count())->toBe(0);
});

test('manual check-in with out-of-fence coordinates is refused too', function () {
    [$user, $staff] = $this->createStaffUser();
    $this->enableGeofence();

    $this->api('POST', 'attendance/check-in', [
        'method' => 'manual',
        'latitude' => self::FAR_LAT,
        'longitude' => self::FAR_LNG,
    ], $user)->assertStatus(422)->assertJsonPath('error_code', 'OUT_OF_GEOFENCE');
});

test('double check-in on the same day is refused', function () {
    [$user, $staff] = $this->createStaffUser();

    $this->api('POST', 'attendance/check-in', ['method' => 'manual'], $user)->assertOk();
    $this->api('POST', 'attendance/check-in', ['method' => 'manual'], $user)->assertStatus(400);
});

test('check-out validates and persists its coordinates', function () {
    [$user, $staff] = $this->createStaffUser();
    $this->enableGeofence();

    $this->api('POST', 'attendance/check-in', [
        'method' => 'geofence',
        'latitude' => self::GEO_LAT,
        'longitude' => self::GEO_LNG,
    ], $user)->assertOk();

    // Outside → refused
    $this->api('POST', 'attendance/check-out', [
        'latitude' => self::FAR_LAT,
        'longitude' => self::FAR_LNG,
    ], $user)->assertStatus(422)->assertJsonPath('error_code', 'OUT_OF_GEOFENCE');

    // Inside → accepted, coordinates stored
    $this->api('POST', 'attendance/check-out', [
        'latitude' => self::GEO_LAT,
        'longitude' => self::GEO_LNG,
    ], $user)->assertOk();

    $attendance = Attendance::forStaff($staff->id)->today()->firstOrFail();
    expect((float) $attendance->check_out_latitude)->toBe(self::GEO_LAT)
        ->and($attendance->check_out_time)->not->toBeNull();
});

test('a check-in method the staff member is not allowed to use is refused', function () {
    [$user, $staff] = $this->createStaffUser(['allowed_check_in_methods' => ['qr_static']]);

    $this->api('POST', 'attendance/check-in', ['method' => 'manual'], $user)->assertStatus(403);
});

test('manual check-in can be disabled tenant-wide in attendance settings', function () {
    [$user, $staff] = $this->createStaffUser();

    AttendanceTypeSetting::create([
        'tenant_id' => $this->tenant->id,
        'type' => Attendance::TYPE_MANUAL,
        'is_enabled' => false,
        'settings' => [],
    ]);

    // Live manual punch refused with the general-level error code
    $this->api('POST', 'attendance/check-in', ['method' => 'manual'], $user)
        ->assertStatus(403)
        ->assertJsonPath('error_code', 'METHOD_DISABLED');

    // Offline manual punch refused with a per-punch result
    $response = $this->api('POST', 'attendance/sync', [
        'punches' => [offlinePunch('check_in', now()->subDay()->setTime(9, 0), self::GEO_LAT, self::GEO_LNG, ['method' => 'manual'])],
    ], $user)->assertOk();
    expect($response->json('data.results.0.error_code'))->toBe('METHOD_DISABLED');

    // Settings and types both reflect the switch
    $settings = $this->api('GET', 'attendance/settings', [], $user)->assertOk()->json('data');
    expect($settings['check_in_methods'])->not->toContain('manual');

    $types = $this->api('GET', 'attendance/types', [], $user)->assertOk()->json('data.types');
    $manual = collect($types)->firstWhere('type', 'manual');
    expect($manual['enabled'])->toBeFalse();
});

test('a method the clinic never enabled is refused with METHOD_DISABLED', function () {
    [$user, $staff] = $this->createStaffUser();

    // No geofence settings row exists — the method is not offered, so the
    // backend must refuse it even with valid-looking coordinates.
    $this->api('POST', 'attendance/check-in', [
        'method' => 'geofence',
        'latitude' => self::GEO_LAT,
        'longitude' => self::GEO_LNG,
    ], $user)->assertStatus(403)->assertJsonPath('error_code', 'METHOD_DISABLED');
});

test('manual check-in without coordinates is refused when a geofence is configured', function () {
    [$user, $staff] = $this->createStaffUser();
    $this->enableGeofence();

    // Omitting the location must not bypass the fence.
    $this->api('POST', 'attendance/check-in', ['method' => 'manual'], $user)
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'LOCATION_REQUIRED');
});

test('manual check-in without coordinates stays allowed when no geofence is configured', function () {
    [$user, $staff] = $this->createStaffUser();

    $this->api('POST', 'attendance/check-in', ['method' => 'manual'], $user)->assertOk();
});

test('check-out without coordinates is refused when a geofence is configured', function () {
    [$user, $staff] = $this->createStaffUser();
    $this->enableGeofence();

    $this->api('POST', 'attendance/check-in', [
        'method' => 'geofence',
        'latitude' => self::GEO_LAT,
        'longitude' => self::GEO_LNG,
    ], $user)->assertOk();

    $this->api('POST', 'attendance/check-out', [], $user)
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'LOCATION_REQUIRED');
});

// ---------------------------------------------------------------------------
// Offline sync — backend verifies every queued punch
// ---------------------------------------------------------------------------

function offlinePunch(string $type, \Carbon\Carbon $at, float $lat, float $lng, array $extra = []): array
{
    return array_merge([
        'client_id' => $type.'-'.$at->timestamp,
        'type' => $type,
        'recorded_at' => $at->toIso8601String(),
        'latitude' => $lat,
        'longitude' => $lng,
    ], $extra);
}

test('an offline punch pair inside the fence is accepted with its original times', function () {
    [$user, $staff] = $this->createStaffUser();
    $this->enableGeofence();

    $in = now()->subDay()->setTime(9, 0);
    $out = now()->subDay()->setTime(17, 0);

    $response = $this->api('POST', 'attendance/sync', [
        'punches' => [
            // Deliberately out of order — the backend sorts chronologically.
            offlinePunch('check_out', $out, self::GEO_LAT, self::GEO_LNG),
            offlinePunch('check_in', $in, self::GEO_LAT, self::GEO_LNG),
        ],
    ], $user)->assertOk();

    expect($response->json('data.accepted'))->toBe(2)
        ->and($response->json('data.rejected'))->toBe(0);

    $attendance = Attendance::forStaff($staff->id)
        ->forDate($in->toDateString())
        ->firstOrFail();

    expect($attendance->check_in_time->format('H:i'))->toBe('09:00')
        ->and($attendance->check_out_time->format('H:i'))->toBe('17:00')
        ->and($attendance->is_offline_entry)->toBeTrue()
        ->and($attendance->location_verified)->toBeTrue()
        ->and((float) $attendance->working_hours)->toBe(8.0);
});

test('an offline punch outside the fence is refused and nothing is stored', function () {
    [$user, $staff] = $this->createStaffUser();
    $this->enableGeofence();

    $in = now()->subDay()->setTime(9, 0);

    $response = $this->api('POST', 'attendance/sync', [
        'punches' => [offlinePunch('check_in', $in, self::FAR_LAT, self::FAR_LNG)],
    ], $user)->assertOk();

    expect($response->json('data.results.0.accepted'))->toBeFalse()
        ->and($response->json('data.results.0.error_code'))->toBe('OUT_OF_GEOFENCE')
        ->and(Attendance::forStaff($staff->id)->count())->toBe(0);
});

test('an offline check-out without a check-in is refused', function () {
    [$user, $staff] = $this->createStaffUser();
    $this->enableGeofence();

    $response = $this->api('POST', 'attendance/sync', [
        'punches' => [offlinePunch('check_out', now()->subDay()->setTime(17, 0), self::GEO_LAT, self::GEO_LNG)],
    ], $user)->assertOk();

    expect($response->json('data.results.0.error_code'))->toBe('NO_CHECK_IN');
});

test('future and stale offline punches are refused', function () {
    [$user, $staff] = $this->createStaffUser();
    $this->enableGeofence();

    $response = $this->api('POST', 'attendance/sync', [
        'punches' => [
            offlinePunch('check_in', now()->addHours(2), self::GEO_LAT, self::GEO_LNG),
            offlinePunch('check_in', now()->subDays(10), self::GEO_LAT, self::GEO_LNG),
        ],
    ], $user)->assertOk();

    $codes = collect($response->json('data.results'))->pluck('error_code')->all();
    expect($codes)->toContain('FUTURE_PUNCH', 'PUNCH_TOO_OLD')
        ->and($response->json('data.accepted'))->toBe(0);
});

test('an offline check-in for a day that already has attendance is refused', function () {
    [$user, $staff] = $this->createStaffUser();
    $this->enableGeofence();

    $this->api('POST', 'attendance/check-in', [
        'method' => 'geofence',
        'latitude' => self::GEO_LAT,
        'longitude' => self::GEO_LNG,
    ], $user)->assertOk();

    // Start-of-today keeps the punch on the same calendar day as the live
    // check-in above regardless of what time the suite runs.
    $response = $this->api('POST', 'attendance/sync', [
        'punches' => [offlinePunch('check_in', now()->startOfDay()->addSecond(), self::GEO_LAT, self::GEO_LNG)],
    ], $user)->assertOk();

    expect($response->json('data.results.0.error_code'))->toBe('ALREADY_CHECKED_IN');
});

test('an offline check-out before its check-in time is refused', function () {
    [$user, $staff] = $this->createStaffUser();
    $this->enableGeofence();

    $in = now()->subDay()->setTime(9, 0);

    $this->api('POST', 'attendance/sync', [
        'punches' => [offlinePunch('check_in', $in, self::GEO_LAT, self::GEO_LNG)],
    ], $user)->assertOk();

    $response = $this->api('POST', 'attendance/sync', [
        'punches' => [offlinePunch('check_out', $in->copy()->subHour(), self::GEO_LAT, self::GEO_LNG)],
    ], $user)->assertOk();

    expect($response->json('data.results.0.error_code'))->toBe('INVALID_SEQUENCE');
});

test('offline punches missing coordinates fail validation', function () {
    [$user, $staff] = $this->createStaffUser();

    $this->api('POST', 'attendance/sync', [
        'punches' => [[
            'client_id' => 'x',
            'type' => 'check_in',
            'recorded_at' => now()->subDay()->toIso8601String(),
        ]],
    ], $user)->assertStatus(422);
});

test('attendance settings advertise the offline sync contract', function () {
    [$user] = $this->createStaffUser();

    $data = $this->api('GET', 'attendance/settings', [], $user)->assertOk()->json('data');

    expect($data['offline_sync']['enabled'])->toBeTrue()
        ->and($data['offline_sync']['max_age_days'])->toBe(7)
        ->and($data['offline_sync']['offline_message'])->not->toBeEmpty()
        ->and($data)->toHaveKey('location_required');
});
