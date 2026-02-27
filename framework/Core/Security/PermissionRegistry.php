<?php

namespace XLinic\Framework\Core\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use XLinic\Framework\Core\Module\ModuleManifest;
use XLinic\Framework\Core\Tenancy\TenantManager;
use Spatie\Permission\Models\Permission;
use Modules\Auth\Models\Role;

class PermissionRegistry
{
    protected array $permissions = [];
    protected array $roles = [];

    public function __construct(
        protected TenantManager $tenantManager
    ) {
    }

    /**
     * Register permissions and roles from a module manifest.
     */
    public function registerFromManifest(ModuleManifest $manifest): void
    {
        foreach ($manifest->permissions as $permission) {
            $this->registerPermission($permission);
        }

        foreach ($manifest->roles as $role) {
            $this->registerRole($role);
        }
    }

    /**
     * Register a permission.
     */
    public function registerPermission(array $permission): void
    {
        $this->permissions[$permission['name']] = $permission;

        // Create in database if it doesn't exist
        Permission::firstOrCreate(
            ['name' => $permission['name']],
            [
                'guard_name' => $permission['guard_name'] ?? 'web',
                'display_name' => $permission['display_name'] ?? $permission['name'],
                'description' => $permission['description'] ?? null,
                'category' => $permission['category'] ?? 'general',
                'module' => $permission['module'] ?? null,
            ]
        );
    }

    /**
     * Register a role.
     */
    public function registerRole(array $role): void
    {
        $this->roles[$role['name']] = $role;

        // Create in database if it doesn't exist
        $roleModel = Role::firstOrCreate(
            ['name' => $role['name']],
            [
                'guard_name' => $role['guard_name'] ?? 'web',
                'display_name' => $role['display_name'] ?? $role['name'],
                'description' => $role['description'] ?? null,
                'is_system' => $role['is_system'] ?? false,
                'is_active' => $role['is_active'] ?? true,
            ]
        );

        // Assign permissions to role
        if (isset($role['permissions'])) {
            $permissions = Permission::whereIn('name', $role['permissions'])->get();
            $roleModel->syncPermissions($permissions);
        }
    }

    /**
     * Get all registered permissions.
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * Get all registered roles.
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * Get permissions by category.
     */
    public function getPermissionsByCategory(): array
    {
        $grouped = [];

        foreach ($this->permissions as $permission) {
            $category = $permission['category'] ?? 'general';
            $grouped[$category][] = $permission;
        }

        return $grouped;
    }

    /**
     * Get permissions by module.
     */
    public function getPermissionsByModule(string $module): array
    {
        return array_filter(
            $this->permissions,
            fn($permission) => ($permission['module'] ?? null) === $module
        );
    }

    /**
     * Check if user has permission in tenant context.
     */
    public function userCan($user, string $permission): bool
    {
        if (!$user) {
            return false;
        }

        // System admin has all permissions
        if ($user->hasRole('super-admin')) {
            return true;
        }

        $tenantId = $this->tenantManager->getCurrentTenantId();

        // Check tenant-specific permissions
        return Cache::tags(['permissions', "tenant:{$tenantId}"])
            ->remember("user_permission:{$user->id}:{$permission}:{$tenantId}", 300, function () use ($user, $permission) {
                return $user->hasPermissionTo($permission);
            });
    }

    /**
     * Check if user has role in tenant context.
     */
    public function userHasRole($user, string $role): bool
    {
        if (!$user) {
            return false;
        }

        $tenantId = $this->tenantManager->getCurrentTenantId();

        return Cache::tags(['roles', "tenant:{$tenantId}"])
            ->remember("user_role:{$user->id}:{$role}:{$tenantId}", 300, function () use ($user, $role) {
                return $user->hasRole($role);
            });
    }

    /**
     * Get user permissions in tenant context.
     */
    public function getUserPermissions($user): Collection
    {
        if (!$user) {
            return collect();
        }

        $tenantId = $this->tenantManager->getCurrentTenantId();

        return Cache::tags(['permissions', "tenant:{$tenantId}"])
            ->remember("user_all_permissions:{$user->id}:{$tenantId}", 300, function () use ($user) {
                return $user->getAllPermissions();
            });
    }

    /**
     * Get user roles in tenant context.
     */
    public function getUserRoles($user): Collection
    {
        if (!$user) {
            return collect();
        }

        $tenantId = $this->tenantManager->getCurrentTenantId();

        return Cache::tags(['roles', "tenant:{$tenantId}"])
            ->remember("user_roles:{$user->id}:{$tenantId}", 300, function () use ($user) {
                return $user->getRoleNames();
            });
    }

    /**
     * Assign permission to user.
     */
    public function assignPermission($user, string $permission): void
    {
        $user->givePermissionTo($permission);
        $this->clearUserCache($user);

        activity()
            ->performedOn($user)
            ->withProperties(['permission' => $permission])
            ->log('Permission assigned');
    }

    /**
     * Remove permission from user.
     */
    public function removePermission($user, string $permission): void
    {
        $user->revokePermissionTo($permission);
        $this->clearUserCache($user);

        activity()
            ->performedOn($user)
            ->withProperties(['permission' => $permission])
            ->log('Permission removed');
    }

    /**
     * Assign role to user.
     */
    public function assignRole($user, string $role): void
    {
        $user->assignRole($role);
        $this->clearUserCache($user);

        activity()
            ->performedOn($user)
            ->withProperties(['role' => $role])
            ->log('Role assigned');
    }

    /**
     * Remove role from user.
     */
    public function removeRole($user, string $role): void
    {
        $user->removeRole($role);
        $this->clearUserCache($user);

        activity()
            ->performedOn($user)
            ->withProperties(['role' => $role])
            ->log('Role removed');
    }

    /**
     * Create a custom permission.
     */
    public function createPermission(array $data): Permission
    {
        $permission = Permission::create(array_merge([
            'guard_name' => 'web',
            'display_name' => $data['name'],
            'category' => 'custom',
        ], $data));

        $this->permissions[$permission->name] = $data;

        activity()
            ->withProperties($data)
            ->log('Custom permission created');

        return $permission;
    }

    /**
     * Create a custom role.
     */
    public function createRole(array $data): Role
    {
        $role = Role::create(array_merge([
            'guard_name' => 'web',
            'display_name' => $data['name'],
            'is_system' => false,
            'is_active' => true,
        ], $data));

        if (isset($data['permissions'])) {
            $permissions = Permission::whereIn('name', $data['permissions'])->get();
            $role->syncPermissions($permissions);
        }

        $this->roles[$role->name] = $data;

        activity()
            ->withProperties($data)
            ->log('Custom role created');

        return $role;
    }

    /**
     * Get permission hierarchy (for UI display).
     */
    public function getPermissionHierarchy(): array
    {
        $hierarchy = [];

        foreach ($this->permissions as $permission) {
            $parts = explode('.', $permission['name']);
            $current = &$hierarchy;

            foreach ($parts as $part) {
                if (!isset($current[$part])) {
                    $current[$part] = [
                        'permissions' => [],
                        'children' => [],
                    ];
                }
                $current = &$current[$part]['children'];
            }

            // Add the full permission to the leaf
            $current['_permission'] = $permission;
        }

        return $hierarchy;
    }

    /**
     * Check if permission is system-protected.
     */
    public function isSystemPermission(string $permission): bool
    {
        $systemPermissions = [
            'super-admin',
            'system.manage',
            'tenant.create',
            'tenant.delete',
            'module.manage',
        ];

        return in_array($permission, $systemPermissions);
    }

    /**
     * Check if role is system-protected.
     */
    public function isSystemRole(string $role): bool
    {
        $roleData = $this->roles[$role] ?? null;
        return $roleData['is_system'] ?? false;
    }

    /**
     * Clear permission cache for user.
     */
    public function clearUserCache($user): void
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();

        Cache::tags(['permissions', 'roles', "tenant:{$tenantId}"])
            ->flush();

        // Also clear Spatie's cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Clear all permission cache.
     */
    public function clearCache(): void
    {
        Cache::tags(['permissions', 'roles'])->flush();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Get permission statistics.
     */
    public function getStats(): array
    {
        return [
            'total_permissions' => count($this->permissions),
            'total_roles' => count($this->roles),
            'permissions_by_category' => array_map('count', $this->getPermissionsByCategory()),
            'system_permissions' => count(array_filter(
                array_keys($this->permissions),
                fn($perm) => $this->isSystemPermission($perm)
            )),
            'system_roles' => count(array_filter(
                array_keys($this->roles),
                fn($role) => $this->isSystemRole($role)
            )),
        ];
    }

    /**
     * Validate permission structure.
     */
    public function validatePermission(array $permission): array
    {
        $errors = [];

        if (empty($permission['name'])) {
            $errors[] = 'Permission name is required';
        }

        if (isset($permission['name']) && !preg_match('/^[a-z0-9._-]+$/', $permission['name'])) {
            $errors[] = 'Permission name must contain only lowercase letters, numbers, dots, underscores, and hyphens';
        }

        return $errors;
    }

    /**
     * Validate role structure.
     */
    public function validateRole(array $role): array
    {
        $errors = [];

        if (empty($role['name'])) {
            $errors[] = 'Role name is required';
        }

        if (isset($role['name']) && !preg_match('/^[a-z0-9._-]+$/', $role['name'])) {
            $errors[] = 'Role name must contain only lowercase letters, numbers, dots, underscores, and hyphens';
        }

        if (isset($role['permissions']) && !is_array($role['permissions'])) {
            $errors[] = 'Role permissions must be an array';
        }

        return $errors;
    }
}