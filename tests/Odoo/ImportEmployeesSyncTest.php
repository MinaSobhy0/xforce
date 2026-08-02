<?php

use Modules\Auth\Models\User;
use Modules\OdooIntegration\Enums\SyncStatus;
use Modules\Staff\Models\StaffProfile;
use Tests\Odoo\Support\OdooScenario;

/**
 * Import sync: Odoo hr.employee → local staff_profiles.
 */
test('imports employees linked to previously imported users', function () {
    $connection = OdooScenario::connection();
    $usersMapping = OdooScenario::users($connection);
    $staffMapping = OdooScenario::staffProfiles($connection);

    $odooUserId = $this->odoo->seed('res.users', ['name' => 'Sara Ahmed', 'login' => 'sara@clinic.test', 'active' => true]);
    runOdooSync($usersMapping);

    $odooEmpId = $this->odoo->seed('hr.employee', [
        'name' => 'Sara Ahmed',
        'user_id' => [$odooUserId, 'Sara Ahmed'],
        'job_title' => 'Laser Specialist',
        'work_email' => 'sara@clinic.test',
        'work_phone' => '0223456789',
        'mobile_phone' => '01001234567',
        'active' => true,
    ]);

    $log = runOdooSync($staffMapping);

    expect($log->status)->toBe(SyncStatus::COMPLETED)
        ->and($log->records_created)->toBe(1);

    $staff = StaffProfile::where('odoo_id', $odooEmpId)->first();
    $localUser = User::where('email', 'sara@clinic.test')->first();

    expect($staff)->not->toBeNull()
        ->and($staff->user_id)->toBe($localUser->id)
        ->and($staff->job_title)->toBe('Laser Specialist')
        ->and($staff->is_active)->toBeTrue()
        ->and($staff->odoo_synced_at)->not->toBeNull();

    // staff_profiles has no contact columns — the mapped work_email /
    // work_phone / mobile_phone flow into the linked User via
    // StaffProfile::applyOdooImport → syncUserContact (fill-empty-only).
    $localUser->refresh();
    expect($localUser->phone)->toBe('01001234567');
});

test('creates a placeholder local user for employees without an odoo user', function () {
    $connection = OdooScenario::connection();
    $staffMapping = OdooScenario::staffProfiles($connection);

    $odooEmpId = $this->odoo->seed('hr.employee', [
        'name' => 'Mona Fathy',
        'user_id' => false,          // no res.users behind this employee
        'work_email' => false,       // Odoo sends false for empty fields
        'job_title' => 'Receptionist',
        'active' => true,
    ]);

    $log = runOdooSync($staffMapping);

    expect($log->records_created)->toBe(1);

    $staff = StaffProfile::where('odoo_id', $odooEmpId)->first();
    expect($staff)->not->toBeNull()
        ->and($staff->user_id)->not->toBeNull();

    $placeholder = User::find($staff->user_id);
    expect($placeholder->first_name)->toBe('Mona')
        ->and($placeholder->last_name)->toBe('Fathy')
        ->and($placeholder->email)->toBe("employee-{$odooEmpId}@local.invalid");
});

test('links employee to an existing local user by work_email', function () {
    $connection = OdooScenario::connection();
    $staffMapping = OdooScenario::staffProfiles($connection);

    $existing = OdooScenario::localUser(['email' => 'front.desk@clinic.test', 'first_name' => 'Front', 'last_name' => 'Desk']);

    $odooEmpId = $this->odoo->seed('hr.employee', [
        'name' => 'Front Desk',
        'user_id' => false,
        'work_email' => 'front.desk@clinic.test',
        'active' => true,
    ]);

    runOdooSync($staffMapping);

    $staff = StaffProfile::where('odoo_id', $odooEmpId)->first();
    expect($staff->user_id)->toBe($existing->id)
        ->and(User::where('email', 'front.desk@clinic.test')->count())->toBe(1);
});

test('resolves time off and attendance approvers through synced users', function () {
    $connection = OdooScenario::connection();
    $usersMapping = OdooScenario::users($connection);
    $staffMapping = OdooScenario::staffProfiles($connection);

    $odooManagerId = $this->odoo->seed('res.users', ['name' => 'Hana Manager', 'login' => 'hana@clinic.test', 'active' => true]);
    runOdooSync($usersMapping);
    $localManager = User::where('email', 'hana@clinic.test')->first();

    $odooEmpId = $this->odoo->seed('hr.employee', [
        'name' => 'Worker Bee',
        'user_id' => false,
        'work_email' => 'worker@clinic.test',
        'leave_manager_id' => [$odooManagerId, 'Hana Manager'],
        'attendance_manager_id' => [$odooManagerId, 'Hana Manager'],
        'active' => true,
    ]);

    runOdooSync($staffMapping);

    $staff = StaffProfile::where('odoo_id', $odooEmpId)->first();
    expect($staff->time_off_approver_user_id)->toBe($localManager->id)
        ->and($staff->attendance_approver_user_id)->toBe($localManager->id);
});

test('leaves department unset when the odoo department has no local counterpart', function () {
    // There is no Department entity mapping configured (hr.department is not
    // synced) — employee imports must still succeed with department_id null.
    $connection = OdooScenario::connection();
    $staffMapping = OdooScenario::staffProfiles($connection);

    $odooEmpId = $this->odoo->seed('hr.employee', [
        'name' => 'Dept Person',
        'user_id' => false,
        'work_email' => 'dept.person@clinic.test',
        'department_id' => [55, 'Laser Department'],
        'active' => true,
    ]);

    $log = runOdooSync($staffMapping);

    expect($log->records_created)->toBe(1)
        ->and($log->records_skipped)->toBe(0);

    $staff = StaffProfile::where('odoo_id', $odooEmpId)->first();
    expect($staff->department_id)->toBeNull();
});

test('archived employees are excluded by the active domain filter', function () {
    $connection = OdooScenario::connection();
    $staffMapping = OdooScenario::staffProfiles($connection);

    $this->odoo->seed('hr.employee', [
        'name' => 'Gone Person',
        'user_id' => false,
        'work_email' => 'gone@clinic.test',
        'active' => false,
    ]);

    $log = runOdooSync($staffMapping);

    expect($this->odoo->lastDomain('hr.employee'))->toBe([['active', '=', true]])
        ->and($log->records_processed)->toBe(0)
        ->and(StaffProfile::count())->toBe(0);
});

test('full sync refreshes employee fields and reuses the linked user', function () {
    $connection = OdooScenario::connection();
    $staffMapping = OdooScenario::staffProfiles($connection);

    $odooEmpId = $this->odoo->seed('hr.employee', [
        'name' => 'Promo Person',
        'user_id' => false,
        'work_email' => 'promo@clinic.test',
        'job_title' => 'Junior Therapist',
        'active' => true,
    ]);

    runOdooSync($staffMapping);
    $staff = StaffProfile::where('odoo_id', $odooEmpId)->first();
    $originalUserId = $staff->user_id;

    // Promotion happens in Odoo
    $this->odoo->write('hr.employee', [$odooEmpId], ['job_title' => 'Senior Therapist']);

    $log = runOdooSync($staffMapping);

    expect($log->records_updated)->toBe(1);
    $staff->refresh();
    expect($staff->job_title)->toBe('Senior Therapist')
        ->and($staff->user_id)->toBe($originalUserId)
        ->and(StaffProfile::count())->toBe(1);
});

test('imported staff default to geofence-only check-in and the default branch', function () {
    $connection = Tests\Odoo\Support\OdooScenario::connection();
    $mapping = Tests\Odoo\Support\OdooScenario::staffProfiles($connection);

    $odooEmpId = $this->odoo->seed('hr.employee', [
        'name' => 'Default Dana', 'user_id' => false,
        'work_email' => 'dana@clinic.test', 'active' => true,
    ]);
    runOdooSync($mapping);

    $staff = Modules\Staff\Models\StaffProfile::where('odoo_id', $odooEmpId)->firstOrFail();
    expect($staff->allowed_check_in_methods)->toBe(['geofence'])
        ->and($staff->branch_id)->not->toBeNull();
});
