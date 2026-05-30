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
        if (method_exists(parent::class, 'canAccess') && ! parent::canAccess()) {
            return false;
        }

        // First check module access
        if (! static::checkModuleAccess()) {
            return false;
        }

        return static::checkViewAccess();
    }

    /**
     * Check if user has view access to this resource.
     * Simple and fast - just checks Spatie's cached permissions.
     */
    protected static function checkViewAccess(): bool
    {
        $permissionKey = static::$permissionKey ?? static::$moduleCode ?? null;

        // If no permission key defined, allow access
        if (! $permissionKey) {
            return true;
        }

        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // Super admin and tenant owner have full access
        if (static::isSuperUser($user)) {
            return true;
        }

        // Check if user has either view_any or view permission
        // Spatie caches these checks, so this is fast
        return $user->can("{$permissionKey}.view_any") || $user->can("{$permissionKey}.view");
    }

    /**
     * Check module access for current tenant.
     */
    protected static function checkModuleAccess(): bool
    {
        $moduleCode = static::$moduleCode ?? null;

        // If no module code defined, allow access (core resources)
        if (! $moduleCode) {
            return true;
        }

        // Core modules are always accessible
        if (in_array($moduleCode, ['core', 'auth'])) {
            return true;
        }

        // Only PLATFORM super-admin bypasses module checks. Tenant owners
        // also carry a 'super_admin' role inside their tenant schema (set
        // by TenantService::createOwnerUser) but that role exists in the
        // tenant.roles table, not public.roles — name collision is real.
        // Resolve by checking which Filament panel we're inside: only the
        // 'super-admin' panel skips the features gate. On tenant / owner /
        // portal panels the features list is enforced even for super_admin
        // role holders, which is the entire point of plan-based access.
        $user = auth()->user();
        if ($user && $user->hasRole(['super-admin', 'super_admin'])) {
            $panel = \Filament\Facades\Filament::getCurrentPanel();
            if ($panel && $panel->getId() === 'super-admin') {
                return true;
            }
        }

        // Get current tenant
        $tenant = static::getCurrentTenant();

        if (! $tenant) {
            return false;
        }

        return $tenant->hasFeature($moduleCode);
    }

    /**
     * Get the permission key for this resource.
     * This is used by external classes (like ExportTableAction) to check permissions.
     */
    public static function getPermissionKey(): ?string
    {
        return static::$permissionKey ?? static::$moduleCode ?? null;
    }

    /**
     * Check if user has a specific permission for this resource.
     * Uses Spatie's cached permission check - no database queries.
     */
    protected static function checkUserPermission(string $action): bool
    {
        $permissionKey = static::$permissionKey ?? static::$moduleCode ?? null;

        // If no permission key defined, allow access
        if (! $permissionKey) {
            return true;
        }

        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // Super admin and tenant owner have full access
        if (static::isSuperUser($user)) {
            return true;
        }

        // Check the specific permission using Spatie's cached check
        return $user->can("{$permissionKey}.{$action}");
    }

    /**
     * Check if user is a super admin or tenant owner (bypass permissions).
     * Note: 'admin' role is NOT included - admins should have configurable permissions.
     * Only platform super-admins and tenant owners bypass permission checks.
     */
    protected static function isSuperUser($user): bool
    {
        if (! $user || ! method_exists($user, 'hasRole')) {
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
     * Get the current tenant.
     */
    protected static function getCurrentTenant(): ?Tenant
    {
        // Try from app container first (fastest)
        if (app()->has('currentTenant')) {
            return app('currentTenant');
        }

        // Try from request attributes
        $tenant = request()->attributes->get('tenant');
        if ($tenant instanceof Tenant) {
            return $tenant;
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
     */
    public static function canViewAny(): bool
    {
        if (! static::checkModuleAccess()) {
            return false;
        }

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
        if (! static::checkModuleAccess()) {
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

    /**
     * Custom, action-level abilities this resource exposes beyond the standard
     * view/create/update/delete/export/import — e.g. ['confirm', 'view_own'].
     * Surfaced in the Role editor and checked via userCan(). Override per resource.
     *
     * @return array<int, string>
     */
    public static function customAbilities(): array
    {
        return [];
    }

    /**
     * Check whether the current user holds a given ability on this resource
     * ({permissionKey}.{ability}). Honours the super-admin / owner bypass.
     */
    public static function userCan(string $ability): bool
    {
        return static::checkUserPermission($ability);
    }
}
