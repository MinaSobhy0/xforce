<?php

namespace Modules\Accounting\Filament\Pages\Concerns;

use Spatie\Permission\Models\Permission;

class ChecksAccountingPermissions
{
    public static function check(string $permission): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Super admin and key roles have full access
        if (method_exists($user, 'hasRole') && $user->hasRole(['super-admin', 'super_admin', 'tenant-owner', 'tenant_owner', 'owner', 'admin'])) {
            return true;
        }

        // Check the specific permission
        if ($user->can($permission)) {
            return true;
        }

        // If permission doesn't exist, allow access (fallback)
        $permissionExists = Permission::where('name', $permission)
            ->where('guard_name', 'web')
            ->exists();

        return !$permissionExists;
    }
}
