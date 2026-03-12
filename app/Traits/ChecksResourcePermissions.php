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
        $resourceClass = static::class;
        \Log::warning("PERM CHECK canAccess() called for: {$resourceClass}");

        // Check parent canAccess if exists
        if (method_exists(parent::class, 'canAccess') && !parent::canAccess()) {
            \Log::warning("PERM CHECK: Parent canAccess returned false for {$resourceClass}");
            return false;
        }

        // First check module access
        if (!static::checkModuleAccess()) {
            \Log::warning("PERM CHECK: Module access denied for {$resourceClass}");
            return false;
        }

        // Use the smarter permission check that handles view_any/view fallback properly
        return static::checkViewAccess();
    }

    /**
     * Check if user has view access to this resource.
     * This method properly handles the view_any/view fallback without legacy issues.
     */
    protected static function checkViewAccess(): bool
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

        $viewAnyPerm = "{$permissionKey}.view_any";
        $viewPerm = "{$permissionKey}.view";

        // Check if user has either permission
        if ($user->can($viewAnyPerm)) {
            \Log::warning("PERM CHECK: User {$user->email} HAS {$viewAnyPerm}");
            return true;
        }

        if ($user->can($viewPerm)) {
            \Log::warning("PERM CHECK: User {$user->email} HAS {$viewPerm}");
            return true;
        }

        // User doesn't have either permission - check if ANY view permission exists
        $viewAnyExists = static::permissionExists($viewAnyPerm);
        $viewExists = static::permissionExists($viewPerm);

        \Log::warning("PERM CHECK: {$viewAnyPerm} exists={$viewAnyExists}, {$viewPerm} exists={$viewExists}");

        // Only allow access via legacy fallback if NEITHER permission exists in DB
        // This means the resource hasn't been set up with permissions yet
        if (!$viewAnyExists && !$viewExists) {
            \Log::warning("PERM CHECK: No view permissions exist for {$permissionKey}, allowing access (legacy fallback)");
            return true;
        }

        // At least one view permission exists, and user doesn't have it - deny access
        \Log::warning("PERM CHECK: DENIED - User {$user->email} lacks view access to {$permissionKey}");
        return false;
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
            \Log::debug("PERM CHECK: No permission key defined, allowing access");
            return true;
        }

        $user = auth()->user();

        if (!$user) {
            \Log::debug("PERM CHECK: No user, denying access");
            return false;
        }

        // Super admin and tenant owner have full access
        if (static::isSuperUser($user)) {
            \Log::warning("PERM CHECK: User {$user->email} is super user, bypassing checks");
            return true;
        }

        // Check the specific permission (e.g., 'patients.view')
        $permissionName = "{$permissionKey}.{$action}";

        // If user has the permission, allow access
        $hasPermission = $user->can($permissionName);
        \Log::warning("PERM CHECK: User {$user->email} checking {$permissionName} = " . ($hasPermission ? 'YES' : 'NO'));

        if ($hasPermission) {
            return true;
        }

        // If the permission doesn't exist yet (not assigned to any role),
        // fall back to checking if user has any role (legacy behavior)
        // This prevents blocking access when permissions haven't been set up yet
        $permExists = static::permissionExists($permissionName);
        \Log::warning("PERM CHECK: Permission {$permissionName} exists in DB = " . ($permExists ? 'YES' : 'NO'));

        if (!$permExists) {
            \Log::warning("PERM CHECK: Permission doesn't exist, allowing access (legacy fallback)");
            return true;
        }

        \Log::warning("PERM CHECK: DENIED - User {$user->email} does not have {$permissionName}");
        return false;
    }

    /**
     * Check if user is a super admin or tenant owner (bypass permissions).
     * Note: 'admin' role is NOT included - admins should have configurable permissions.
     * Only platform super-admins and tenant owners bypass permission checks.
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
        ]);
    }

    /**
     * Check if a permission exists in the system.
     * Uses the tenant-aware Permission model for multi-tenancy.
     */
    protected static function permissionExists(string $permissionName): bool
    {
        return \Modules\Auth\Models\Permission::where('name', $permissionName)
            ->where('guard_name', 'web')
            ->exists();
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

        // Try from session (only if numeric ID - INT primary keys)
        $tenantId = session('tenant_id');
        if ($tenantId && is_numeric($tenantId)) {
            return Tenant::find((int) $tenantId);
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
     * Determine if the user can view records (list view).
     * Checks for 'view_any' first, falls back to 'view' for backwards compatibility.
     */
    public static function canViewAny(): bool
    {
        if (!static::checkModuleAccess()) {
            return false;
        }

        // Use the same smart view access check as canAccess()
        return static::checkViewAccess();
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
     * Checks for both 'update' (new standard) and 'edit' (legacy) permissions.
     */
    public static function canEdit($record): bool
    {
        if (!static::checkModuleAccess()) {
            return false;
        }

        // Check new standard 'update' permission first
        if (static::checkUserPermission('update')) {
            return true;
        }

        // Fall back to legacy 'edit' permission for backwards compatibility
        return static::checkUserPermission('edit');
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
