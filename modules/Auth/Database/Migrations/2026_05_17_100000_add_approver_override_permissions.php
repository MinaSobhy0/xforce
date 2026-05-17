<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Auth\Models\Permission;

/**
 * Register the HR-override permissions used by the Approvers feature.
 *
 *   - `practitioner_time_off.approve_any` — lets a user approve a
 *     time-off request even when they are not the configured
 *     time_off_approver on the requester's staff profile. Intended for
 *     HR leads / admins so the system doesn't deadlock when an
 *     approver is unavailable.
 *
 *   - `attendance_violations.approve_any` — same role for attendance
 *     violations.
 *
 * Idempotent via firstOrCreate. Both belong to the `web` guard since
 * the tenant panel users (and Spatie checks) run under it — see
 * `config/auth.php`.
 *
 * Note: ported from xlinic's same-named migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => 'practitioner_time_off.approve_any', 'guard_name' => 'web'],
            ['display_name' => 'Approve Any Time-Off Request', 'module' => 'booking']
        );

        Permission::firstOrCreate(
            ['name' => 'attendance_violations.approve_any', 'guard_name' => 'web'],
            ['display_name' => 'Approve Any Attendance Violation', 'module' => 'attendance']
        );

        // Refresh Spatie's cache so the new permissions are visible to
        // the current process / next request without a manual flush.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('guard_name', 'web')
            ->whereIn('name', [
                'practitioner_time_off.approve_any',
                'attendance_violations.approve_any',
            ])
            ->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
