<?php

use Modules\Core\Models\Department;
use Modules\OdooIntegration\Enums\SyncStatus;
use Modules\Staff\Models\StaffProfile;
use Tests\Odoo\Support\OdooScenario;

/**
 * Import sync: Odoo hr.department → local departments, and department
 * resolution on employee imports.
 */
test('imports departments with codes and active flags', function () {
    $connection = OdooScenario::connection();
    $mapping = OdooScenario::departments($connection);

    $odooDeptId = $this->odoo->seed('hr.department', [
        'name' => 'Laser Department',
        'code' => 'LASER',
        'parent_id' => false,
        'manager_id' => false,
        'active' => true,
    ]);

    $log = runOdooSync($mapping);

    expect($log->status)->toBe(SyncStatus::COMPLETED)
        ->and($log->records_created)->toBe(1)
        ->and($log->records_failed)->toBe(0)
        ->and($log->records_skipped)->toBe(0);

    $dept = Department::where('odoo_id', $odooDeptId)->first();
    expect($dept)->not->toBeNull()
        ->and($dept->name)->toBe('Laser Department')
        ->and($dept->code)->toBe('LASER')
        ->and($dept->is_active)->toBeTrue()
        ->and($dept->odoo_synced_at)->not->toBeNull();
});

test('resolves parent department hierarchy in id order', function () {
    $connection = OdooScenario::connection();
    $mapping = OdooScenario::departments($connection);

    $odooParentId = $this->odoo->seed('hr.department', [
        'name' => 'Clinic Operations', 'code' => 'OPS', 'parent_id' => false, 'manager_id' => false, 'active' => true,
    ]);
    $odooChildId = $this->odoo->seed('hr.department', [
        'name' => 'Front Desk', 'code' => 'FRONT', 'parent_id' => [$odooParentId, 'Clinic Operations'], 'manager_id' => false, 'active' => true,
    ]);

    $log = runOdooSync($mapping);

    expect($log->records_created)->toBe(2);

    $parent = Department::where('odoo_id', $odooParentId)->first();
    $child = Department::where('odoo_id', $odooChildId)->first();
    expect($child->parent_id)->toBe($parent->id);
});

test('a department with an unsynced manager still imports and backfills later', function () {
    $connection = OdooScenario::connection();
    $deptMapping = OdooScenario::departments($connection);

    $odooEmpId = $this->odoo->seed('hr.employee', [
        'name' => 'Mona Manager', 'user_id' => false, 'work_email' => 'mona@clinic.test', 'active' => true,
    ]);
    $odooDeptId = $this->odoo->seed('hr.department', [
        'name' => 'Nursing', 'code' => 'NURS', 'parent_id' => false,
        'manager_id' => [$odooEmpId, 'Mona Manager'], 'active' => true,
    ]);

    // First sync: employee not synced yet → department imports without manager
    $log = runOdooSync($deptMapping);
    expect($log->records_created)->toBe(1)
        ->and($log->records_skipped)->toBe(0);

    $dept = Department::where('odoo_id', $odooDeptId)->first();
    expect($dept->manager_id)->toBeNull();

    // Employees sync afterwards (as in the priority order dept → staff)
    $staffMapping = OdooScenario::staffProfiles($connection);
    runOdooSync($staffMapping);
    $manager = StaffProfile::where('odoo_id', $odooEmpId)->firstOrFail();

    // Next full department sync backfills the manager
    runOdooSync($deptMapping);
    expect($dept->fresh()->manager_id)->toBe($manager->id);
});

test('employees imported after departments get their department resolved', function () {
    $connection = OdooScenario::connection();
    $deptMapping = OdooScenario::departments($connection);
    $staffMapping = OdooScenario::staffProfiles($connection);

    $odooDeptId = $this->odoo->seed('hr.department', [
        'name' => 'Laser Department', 'code' => 'LASER', 'parent_id' => false, 'manager_id' => false, 'active' => true,
    ]);
    $odooEmpId = $this->odoo->seed('hr.employee', [
        'name' => 'Sara Ahmed',
        'user_id' => false,
        'work_email' => 'sara@clinic.test',
        'department_id' => [$odooDeptId, 'Laser Department'],
        'active' => true,
    ]);

    // Same order syncAll uses: departments (priority 2) before employees
    runOdooSync($deptMapping);
    runOdooSync($staffMapping);

    $dept = Department::where('odoo_id', $odooDeptId)->firstOrFail();
    $staff = StaffProfile::where('odoo_id', $odooEmpId)->firstOrFail();

    expect($staff->department_id)->toBe($dept->id);
});

test('full sync refreshes renamed departments without duplicating', function () {
    $connection = OdooScenario::connection();
    $mapping = OdooScenario::departments($connection);

    $odooDeptId = $this->odoo->seed('hr.department', [
        'name' => 'Reception', 'code' => 'RCPT', 'parent_id' => false, 'manager_id' => false, 'active' => true,
    ]);
    runOdooSync($mapping);

    $this->odoo->write('hr.department', [$odooDeptId], ['name' => 'Front Office']);
    $log = runOdooSync($mapping);

    expect($log->records_updated)->toBe(1)
        ->and(Department::count())->toBe(1)
        ->and(Department::where('odoo_id', $odooDeptId)->first()->name)->toBe('Front Office');
});
