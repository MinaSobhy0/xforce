<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class TenantRoleSeeder extends Seeder
{
    public function run(): void
    {
        // Tenant-level roles (for clinic users)
        $tenantRoles = [
            'owner' => [
                'display_name' => 'Clinic Owner',
                'permissions' => [
                    'users.view', 'users.create', 'users.edit', 'users.delete',
                    'roles.view', 'roles.manage', 'permissions.view',
                ],
            ],
            'admin' => [
                'display_name' => 'Administrator',
                'permissions' => [
                    'users.view', 'users.create', 'users.edit', 'users.delete',
                    'roles.view', 'roles.manage',
                ],
            ],
            'manager' => [
                'display_name' => 'Manager',
                'permissions' => [
                    'users.view', 'users.create', 'users.edit', 'roles.view',
                ],
            ],
            'doctor' => [
                'display_name' => 'Doctor',
                'permissions' => ['users.view'],
            ],
            'nurse' => [
                'display_name' => 'Nurse',
                'permissions' => ['users.view'],
            ],
            'technician' => [
                'display_name' => 'Technician',
                'permissions' => ['users.view'],
            ],
            'receptionist' => [
                'display_name' => 'Receptionist',
                'permissions' => ['users.view'],
            ],
            'staff' => [
                'display_name' => 'Staff',
                'permissions' => ['users.view'],
            ],
        ];

        // Create permissions if they don't exist
        $allPermissions = collect($tenantRoles)
            ->pluck('permissions')
            ->flatten()
            ->unique();

        foreach ($allPermissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions
        foreach ($tenantRoles as $roleName => $roleData) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                ['display_name' => $roleData['display_name']]
            );

            $permissions = Permission::whereIn('name', $roleData['permissions'])->get();
            $role->syncPermissions($permissions);
        }
    }
}
