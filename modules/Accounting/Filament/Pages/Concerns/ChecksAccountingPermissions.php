<?php

namespace Modules\Accounting\Filament\Pages\Concerns;

use Modules\Auth\Models\Permission;

class ChecksAccountingPermissions
{
    /**
     * Check if user has permission to access accounting features.
     * Note: 'admin' role is NOT included - admins should have configurable permissions.
     */
    public static function check(string $permission): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Only platform super-admins and tenant owners bypass permission checks
        if (method_exists($user, 'hasRole') && $user->hasRole(['super-admin', 'super_admin', 'tenant-owner', 'tenant_owner', 'owner'])) {
            return true;
        }

        // Check the specific permission
        if ($user->can($permission)) {
            return true;
        }

        // Also check view_any variant if checking for view
        if (str_ends_with($permission, '.view')) {
            $viewAnyPermission = str_replace('.view', '.view_any', $permission);
            if ($user->can($viewAnyPermission)) {
                return true;
            }
        }

        // If permission doesn't exist, allow access (fallback for new resources)
        $permissionExists = Permission::where('name', $permission)
            ->where('guard_name', 'web')
            ->exists();

        // Also check if view_any exists
        if (!$permissionExists && str_ends_with($permission, '.view')) {
            $viewAnyPermission = str_replace('.view', '.view_any', $permission);
            $permissionExists = Permission::where('name', $viewAnyPermission)
                ->where('guard_name', 'web')
                ->exists();
        }

        return !$permissionExists;
    }
}
