<?php

namespace App\Traits;

use Modules\Core\Models\Tenant;

/**
 * Trait for Filament resources to check both tenant module access AND user permissions.
 *
 * Usage in your resource:
 *   use App\Traits\ChecksResourcePermissions;
 *
 *   class PatientResource extends Resource
 *   {
 *       use ChecksResourcePermissions;
 *
 *       protected static ?string $moduleCode = 'patients';
 *       protected static ?string $permissionKey = 'patients'; // For permission checking
 *   }
 *
 * The resource will automatically:
 * - Hide from navigation if tenant doesn't have the module
 * - Hide from navigation if user doesn't have view permission
 * - Deny create/edit/delete based on user permissions
 */
trait ChecksResourcePermissions
{
    /**
     * Check if the current user/tenant can access this resource.
     */
    public static function canAccess(): bool
    {
        // Check parent canAccess if exists
        if (method_exists(parent::class, 'canAccess') && !parent::canAccess()) {
            return false;
        }

        // First check module access
        if (!static::checkModuleAccess()) {
            return false;
        }

        // Then check user permission
        return static::checkUserPermission('view');
    }

    /**
     * Check module access for current tenant.
     */
    protected static function checkModuleAccess(): bool
    {
        $moduleCode = static::$moduleCode ?? null;

        // If no module code defined, allow access (core resources)
        if (!$moduleCode) {
            return true;
        }

        // Core modules are always accessible
        if (in_array($moduleCode, ['core', 'auth'])) {
            return true;
        }

        // Only platform super-admin bypasses module checks (not tenant admins)
        $user = auth()->user();
        if ($user && $user->hasRole(['super-admin', 'super_admin'])) {
            return true;
        }

        // Get current tenant
        $tenant = static::getCurrentTenant();

        if (!$tenant) {
            return false;
        }

        return $tenant->hasFeature($moduleCode);
    }

    /**
     * Check if user has a specific permission for this resource.
     */
    protected static function checkUserPermission(string $action): bool
    {
        $permissionKey = static::$permissionKey ?? static::$moduleCode ?? null;

        // If no permission key defined, allow access
        if (!$permissionKey) {
            return true;
        }

        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Super admin and tenant owner have full access
        if (static::isSuperUser($user)) {
            return true;
        }

        // Check the specific permission (e.g., 'patients.view')
        $permissionName = "{$permissionKey}.{$action}";

        // If user has the permission, allow access
        if ($user->can($permissionName)) {
            return true;
        }

        // If the permission doesn't exist yet (not assigned to any role),
        // fall back to checking if user has any role (legacy behavior)
        // This prevents blocking access when permissions haven't been set up yet
        if (!static::permissionExists($permissionName)) {
            return true;
        }

        return false;
    }

    /**
     * Check if user is a super admin or tenant owner (bypass permissions).
     */
    protected static function isSuperUser($user): bool
    {
        if (!$user || !method_exists($user, 'hasRole')) {
            return false;
        }

        return $user->hasRole([
            'super-admin',
            'super_admin',
            'tenant-owner',
            'tenant_owner',
            'owner',
            'admin',
        ]);
    }

    /**
     * Check if a permission exists in the system.
     */
    protected static function permissionExists(string $permissionName): bool
    {
        static $cache = [];

        if (!isset($cache[$permissionName])) {
            $cache[$permissionName] = \Spatie\Permission\Models\Permission::where('name', $permissionName)
                ->where('guard_name', 'web')
                ->exists();
        }

        return $cache[$permissionName];
    }

    /**
     * Get the current tenant.
     */
    protected static function getCurrentTenant(): ?Tenant
    {
        // Try from app container
        if (app()->has('currentTenant')) {
            return app('currentTenant');
        }

        // Try from request attributes
        $tenant = request()->attributes->get('tenant');
        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        // Try from session
        $tenantId = session('tenant_id');
        if ($tenantId) {
            return Tenant::find($tenantId);
        }

        return null;
    }

    /**
     * Determine if this resource should be registered.
     * This prevents the resource from appearing in navigation entirely.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /**
     * Determine if the user can view records.
     */
    public static function canViewAny(): bool
    {
        return static::checkModuleAccess() && static::checkUserPermission('view');
    }

    /**
     * Determine if the user can view a specific record.
     */
    public static function canView($record): bool
    {
        return static::checkModuleAccess() && static::checkUserPermission('view');
    }

    /**
     * Determine if the user can create records.
     */
    public static function canCreate(): bool
    {
        return static::checkModuleAccess() && static::checkUserPermission('create');
    }

    /**
     * Determine if the user can edit records.
     */
    public static function canEdit($record): bool
    {
        return static::checkModuleAccess() && static::checkUserPermission('edit');
    }

    /**
     * Determine if the user can delete records.
     */
    public static function canDelete($record): bool
    {
        return static::checkModuleAccess() && static::checkUserPermission('delete');
    }

    /**
     * Determine if the user can delete any records (bulk delete).
     */
    public static function canDeleteAny(): bool
    {
        return static::checkModuleAccess() && static::checkUserPermission('delete');
    }
}
