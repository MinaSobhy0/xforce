<?php

use Modules\Attendance\Models\Attendance;
use Modules\OdooIntegration\Enums\SyncStatus;
use Modules\OdooIntegration\Models\OdooSyncConflict;
use Modules\OdooIntegration\Models\OdooSyncRecord;
use Modules\OdooIntegration\Services\Transform\TimezoneConverter;
use Tests\Odoo\Support\OdooScenario;

/**
 * Export sync: local attendances → Odoo hr.attendance.
 * This is the flow the mobile app will drive (check-in/check-out capture).
 */
function seedExportableStaff($test, $connection): array
{
    $staffMapping = OdooScenario::staffProfiles($connection);
    $odooEmpId = $test->odoo->seed('hr.employee', [
        'name' => 'Sara Ahmed', 'user_id' => false, 'work_email' => 'sara@clinic.test', 'active' => true,
    ]);
    runOdooSync($staffMapping);

    return [\Modules\Staff\Models\StaffProfile::where('odoo_id', $odooEmpId)->firstOrFail(), $odooEmpId];
}

function makeAttendance(int $staffProfileId, array $overrides = []): Attendance
{
    return Attendance::create(array_merge([
        'tenant_id' => current_tenant_id(),
        'staff_profile_id' => $staffProfileId,
        'attendance_date' => '2026-07-30',
        'check_in_time' => '09:00:00',
        'check_out_time' => '17:30:00',
        'working_hours' => 8.5,
    ], $overrides));
}

test('exports a new attendance to odoo hr.attendance', function () {
    $connection = OdooScenario::connection();
    [$staff, $odooEmpId] = seedExportableStaff($this, $connection);
    $mapping = OdooScenario::attendances($connection);

    $attendance = makeAttendance($staff->id);

    $log = runOdooSync($mapping);

    expect($log->status)->toBe(SyncStatus::COMPLETED)
        ->and($log->records_created)->toBe(1)
        ->and($log->records_failed)->toBe(0);

    $attendance->refresh();
    expect($attendance->odoo_id)->not->toBeNull()
        ->and($attendance->odoo_synced_at)->not->toBeNull();

    $odooRecord = $this->odoo->find('hr.attendance', $attendance->odoo_id);
    expect($odooRecord)->not->toBeNull()
        ->and($odooRecord['employee_id'])->toBe($odooEmpId)
        ->and((float) $odooRecord['worked_hours'])->toBe(8.5);

    // Local time converts to UTC for Odoo (time-only column carries the time)
    $tz = app(TimezoneConverter::class);
    expect(substr($odooRecord['check_in'], 11))->toBe(substr($tz->toOdoo('09:00:00'), 11));

    // Sync bookkeeping
    $syncRecord = OdooSyncRecord::where('entity_mapping_id', $mapping->id)
        ->where('local_id', $attendance->id)
        ->first();
    expect($syncRecord)->not->toBeNull()
        ->and($syncRecord->sync_status)->toBe(OdooSyncRecord::STATUS_SYNCED)
        ->and($syncRecord->odoo_id)->toBe($attendance->odoo_id);
});

test('re-exporting a locally updated attendance writes to the same odoo record', function () {
    $connection = OdooScenario::connection();
    [$staff] = seedExportableStaff($this, $connection);
    $mapping = OdooScenario::attendances($connection);

    $attendance = makeAttendance($staff->id);
    runOdooSync($mapping);
    $attendance->refresh();
    $odooId = $attendance->odoo_id;

    // Check-out corrected locally (e.g. from the mobile app)
    $attendance->update(['check_out_time' => '18:00:00', 'working_hours' => 9.0]);

    $log = runOdooSync($mapping);

    expect($log->records_updated)->toBe(1)
        ->and($log->conflicts_detected)->toBe(0);

    $odooRecord = $this->odoo->find('hr.attendance', $odooId);
    expect((float) $odooRecord['worked_hours'])->toBe(9.0)
        ->and(Attendance::count())->toBe(1)
        ->and(count($this->odoo->records['hr.attendance']))->toBe(1);
});

test('flags a manual conflict when both sides changed', function () {
    $connection = OdooScenario::connection();
    [$staff] = seedExportableStaff($this, $connection);
    $mapping = OdooScenario::attendances($connection);   // conflict_resolution = manual

    $attendance = makeAttendance($staff->id);
    runOdooSync($mapping);
    $attendance->refresh();

    // Odoo-side edit...
    $this->odoo->write('hr.attendance', [$attendance->odoo_id], ['worked_hours' => 7.0]);
    // ...and a diverging local edit
    $attendance->update(['working_hours' => 9.25]);

    $log = runOdooSync($mapping);

    expect($log->conflicts_detected)->toBe(1);

    $conflict = OdooSyncConflict::first();
    expect($conflict)->not->toBeNull()
        ->and($conflict->conflict_type)->toBe(OdooSyncConflict::TYPE_BOTH_MODIFIED)
        ->and($conflict->status)->toBe(\Modules\OdooIntegration\Enums\ConflictStatus::PENDING);

    $syncRecord = OdooSyncRecord::where('local_id', $attendance->id)->first();
    expect($syncRecord->sync_status)->toBe(OdooSyncRecord::STATUS_CONFLICT);

    // Odoo keeps its own value until the conflict is resolved
    expect((float) $this->odoo->find('hr.attendance', $attendance->odoo_id)['worked_hours'])->toBe(7.0);
});

test('local wins resolution pushes local changes over odoo edits', function () {
    $connection = OdooScenario::connection();
    [$staff] = seedExportableStaff($this, $connection);
    $mapping = OdooScenario::attendances($connection, ['conflict_resolution' => 'local_wins']);

    $attendance = makeAttendance($staff->id);
    runOdooSync($mapping);
    $attendance->refresh();

    $this->odoo->write('hr.attendance', [$attendance->odoo_id], ['worked_hours' => 7.0]);
    $attendance->update(['working_hours' => 9.25]);

    $log = runOdooSync($mapping);

    expect($log->records_updated)->toBe(1)
        ->and($log->conflicts_detected)->toBe(0)
        ->and((float) $this->odoo->find('hr.attendance', $attendance->odoo_id)['worked_hours'])->toBe(9.25);
});

test('recreates the odoo record when it was deleted remotely under local wins', function () {
    $connection = OdooScenario::connection();
    [$staff, $odooEmpId] = seedExportableStaff($this, $connection);
    $mapping = OdooScenario::attendances($connection, ['conflict_resolution' => 'local_wins']);

    $attendance = makeAttendance($staff->id);
    runOdooSync($mapping);
    $attendance->refresh();
    $firstOdooId = $attendance->odoo_id;

    $this->odoo->unlink('hr.attendance', [$firstOdooId]);

    runOdooSync($mapping);
    $attendance->refresh();

    expect($attendance->odoo_id)->not->toBe($firstOdooId)
        ->and($this->odoo->find('hr.attendance', $attendance->odoo_id)['employee_id'])->toBe($odooEmpId);

    $syncRecord = OdooSyncRecord::where('local_id', $attendance->id)->first();
    expect($syncRecord->odoo_id)->toBe($attendance->odoo_id)
        ->and($syncRecord->sync_status)->toBe(OdooSyncRecord::STATUS_SYNCED);
});

test('files a deleted-remotely conflict under manual resolution', function () {
    $connection = OdooScenario::connection();
    [$staff] = seedExportableStaff($this, $connection);
    $mapping = OdooScenario::attendances($connection);   // manual

    $attendance = makeAttendance($staff->id);
    runOdooSync($mapping);
    $attendance->refresh();

    $this->odoo->unlink('hr.attendance', [$attendance->odoo_id]);
    $attendance->update(['working_hours' => 9.0]);

    $log = runOdooSync($mapping);

    expect($log->conflicts_detected)->toBe(1);
    expect(OdooSyncConflict::first()->conflict_type)->toBe(OdooSyncConflict::TYPE_DELETED_REMOTELY);
});

test('delta export only pushes records modified after the watermark', function () {
    $connection = OdooScenario::connection();
    [$staff] = seedExportableStaff($this, $connection);
    $mapping = OdooScenario::attendances($connection);

    $old = makeAttendance($staff->id, ['attendance_date' => '2026-07-28']);
    runOdooSync($mapping);

    // Watermark now = completion of first export; only newer changes go out
    app(\Modules\OdooIntegration\Services\Sync\WatermarkService::class)
        ->setWatermark($mapping->fresh(), 'export', now()->format('Y-m-d H:i:s'));

    $this->travel(1)->minutes();

    $new = makeAttendance($staff->id, ['attendance_date' => '2026-07-29']);

    $log = runOdooSync($mapping, 'delta');

    expect($log->records_processed)->toBe(1)
        ->and($log->records_created)->toBe(1);

    expect($new->fresh()->odoo_id)->not->toBeNull();
});
