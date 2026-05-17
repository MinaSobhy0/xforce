<?php

namespace Modules\MobileApi\Support;

use Illuminate\Support\Collection;
use Modules\Auth\Models\User;
use Modules\Staff\Models\StaffProfile;

/**
 * Resolves the User(s) that should receive an approval-needed notification
 * for a given staff member, with a fallback to any holder of the relevant
 * `*.approve_any` override permission.
 *
 * The two override permissions are defined in:
 *   modules/Auth/Database/Migrations/2026_05_17_100000_add_approver_override_permissions.php
 *
 * Caller picks which field on staff_profiles to read (time_off_approver_user_id
 * or attendance_approver_user_id) and the matching override permission name.
 */
class ApproverResolver
{
    public const PERM_TIME_OFF_APPROVE_ANY = 'practitioner_time_off.approve_any';

    public const PERM_VIOLATIONS_APPROVE_ANY = 'attendance_violations.approve_any';

    /**
     * Per-request cache of override-permission holders so a single sync sweep
     * doesn't re-query for every record.
     *
     * @var array<string, Collection<int, User>>
     */
    protected static array $overrideCache = [];

    /**
     * Return the User configured as the staff's approver, or null when none
     * is set / the configured id no longer points to an active user.
     */
    public static function configuredApprover(?StaffProfile $staffProfile, string $field): ?User
    {
        if (! $staffProfile) {
            return null;
        }

        $approverId = $staffProfile->{$field} ?? null;
        if (! $approverId) {
            return null;
        }

        return User::query()->whereKey($approverId)->first();
    }

    /**
     * Return every User holding the given permission, cached per request.
     */
    public static function overrideApprovers(string $permission): Collection
    {
        if (isset(self::$overrideCache[$permission])) {
            return self::$overrideCache[$permission];
        }

        $users = User::query()
            ->whereHas('roles.permissions', fn ($q) => $q->where('name', $permission))
            ->orWhereHas('permissions', fn ($q) => $q->where('name', $permission))
            ->get();

        return self::$overrideCache[$permission] = $users;
    }

    /**
     * Recipients for an approval-needed push: the configured approver if any,
     * plus every override holder, de-duplicated. Returns an empty collection
     * when nobody can act on this record.
     *
     * @return Collection<int, User>
     */
    public static function recipientsFor(
        ?StaffProfile $staffProfile,
        string $approverField,
        string $overridePermission,
    ): Collection {
        $users = self::overrideApprovers($overridePermission)->all();

        $configured = self::configuredApprover($staffProfile, $approverField);
        if ($configured) {
            $users[] = $configured;
        }

        return collect($users)->unique('id')->values();
    }
}
