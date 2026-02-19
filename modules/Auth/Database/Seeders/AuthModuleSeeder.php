<?php

namespace Modules\Auth\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\Models\User;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\Permission;
use Illuminate\Support\Facades\Hash;

class AuthModuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding Auth module data...');

        $this->seedPermissions();
        $this->seedRoles();
        $this->seedUsers();

        $this->command->info('Auth module seeded successfully.');
    }

    private function seedPermissions(): void
    {
        $permissions = [
            // System Management
            ['name' => 'system.view', 'display_name' => 'View System', 'module' => 'core'],
            ['name' => 'system.manage', 'display_name' => 'Manage System', 'module' => 'core'],
            ['name' => 'system.settings', 'display_name' => 'System Settings', 'module' => 'core'],

            // Tenant Management
            ['name' => 'tenants.view', 'display_name' => 'View Tenants', 'module' => 'core'],
            ['name' => 'tenants.create', 'display_name' => 'Create Tenants', 'module' => 'core'],
            ['name' => 'tenants.edit', 'display_name' => 'Edit Tenants', 'module' => 'core'],
            ['name' => 'tenants.delete', 'display_name' => 'Delete Tenants', 'module' => 'core'],

            // Module Management
            ['name' => 'modules.view', 'display_name' => 'View Modules', 'module' => 'core'],
            ['name' => 'modules.manage', 'display_name' => 'Manage Modules', 'module' => 'core'],

            // User Management
            ['name' => 'users.view', 'display_name' => 'View Users', 'module' => 'auth'],
            ['name' => 'users.create', 'display_name' => 'Create Users', 'module' => 'auth'],
            ['name' => 'users.edit', 'display_name' => 'Edit Users', 'module' => 'auth'],
            ['name' => 'users.delete', 'display_name' => 'Delete Users', 'module' => 'auth'],
            ['name' => 'users.impersonate', 'display_name' => 'Impersonate Users', 'module' => 'auth'],

            // Role Management
            ['name' => 'roles.view', 'display_name' => 'View Roles', 'module' => 'auth'],
            ['name' => 'roles.create', 'display_name' => 'Create Roles', 'module' => 'auth'],
            ['name' => 'roles.edit', 'display_name' => 'Edit Roles', 'module' => 'auth'],
            ['name' => 'roles.delete', 'display_name' => 'Delete Roles', 'module' => 'auth'],

            // Profile Management
            ['name' => 'profile.view', 'display_name' => 'View Profile', 'module' => 'auth'],
            ['name' => 'profile.edit', 'display_name' => 'Edit Profile', 'module' => 'auth'],

            // Patient Management (for future modules)
            ['name' => 'patients.view', 'display_name' => 'View Patients', 'module' => 'patients'],
            ['name' => 'patients.create', 'display_name' => 'Create Patients', 'module' => 'patients'],
            ['name' => 'patients.edit', 'display_name' => 'Edit Patients', 'module' => 'patients'],
            ['name' => 'patients.delete', 'display_name' => 'Delete Patients', 'module' => 'patients'],

            // Appointment Management
            ['name' => 'appointments.view', 'display_name' => 'View Appointments', 'module' => 'appointments'],
            ['name' => 'appointments.create', 'display_name' => 'Create Appointments', 'module' => 'appointments'],
            ['name' => 'appointments.edit', 'display_name' => 'Edit Appointments', 'module' => 'appointments'],
            ['name' => 'appointments.delete', 'display_name' => 'Delete Appointments', 'module' => 'appointments'],

            // Treatment Management
            ['name' => 'treatments.view', 'display_name' => 'View Treatments', 'module' => 'treatments'],
            ['name' => 'treatments.create', 'display_name' => 'Create Treatments', 'module' => 'treatments'],
            ['name' => 'treatments.edit', 'display_name' => 'Edit Treatments', 'module' => 'treatments'],
            ['name' => 'treatments.delete', 'display_name' => 'Delete Treatments', 'module' => 'treatments'],

            // Billing Management
            ['name' => 'billing.view', 'display_name' => 'View Billing', 'module' => 'billing'],
            ['name' => 'billing.create', 'display_name' => 'Create Invoices', 'module' => 'billing'],
            ['name' => 'billing.edit', 'display_name' => 'Edit Billing', 'module' => 'billing'],
            ['name' => 'billing.delete', 'display_name' => 'Delete Billing', 'module' => 'billing'],

            // Reports
            ['name' => 'reports.view', 'display_name' => 'View Reports', 'module' => 'reports'],
            ['name' => 'reports.export', 'display_name' => 'Export Reports', 'module' => 'reports'],
        ];

        foreach ($permissions as $permission) {
            $permission['guard_name'] = 'web';
            Permission::firstOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'web'],
                $permission
            );
        }

        $this->command->info('✓ Permissions created: ' . count($permissions));
    }

    private function seedRoles(): void
    {
        $roles = [
            [
                'name' => 'super_admin',
                'display_name' => 'Super Administrator',
                'description' => 'Full system access with all permissions',
                'level' => 100,
                'is_system' => true,
                'is_active' => true,
                'permissions' => Permission::all()->pluck('name')->toArray(),
            ],
            [
                'name' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'System administrator with most permissions',
                'level' => 90,
                'is_system' => true,
                'is_active' => true,
                'permissions' => [
                    'users.view', 'users.create', 'users.edit',
                    'roles.view', 'roles.create', 'roles.edit',
                    'patients.view', 'patients.create', 'patients.edit', 'patients.delete',
                    'appointments.view', 'appointments.create', 'appointments.edit', 'appointments.delete',
                    'treatments.view', 'treatments.create', 'treatments.edit', 'treatments.delete',
                    'billing.view', 'billing.create', 'billing.edit',
                    'reports.view', 'reports.export',
                    'profile.view', 'profile.edit',
                ],
            ],
            [
                'name' => 'manager',
                'display_name' => 'Clinic Manager',
                'description' => 'Manage clinic operations and staff',
                'level' => 70,
                'is_system' => false,
                'is_active' => true,
                'permissions' => [
                    'users.view', 'users.create', 'users.edit',
                    'patients.view', 'patients.create', 'patients.edit', 'patients.delete',
                    'appointments.view', 'appointments.create', 'appointments.edit', 'appointments.delete',
                    'treatments.view', 'treatments.create', 'treatments.edit',
                    'billing.view', 'billing.create', 'billing.edit',
                    'reports.view',
                    'profile.view', 'profile.edit',
                ],
            ],
            [
                'name' => 'doctor',
                'display_name' => 'Doctor',
                'description' => 'Medical practitioner with patient and treatment access',
                'level' => 50,
                'is_system' => false,
                'is_active' => true,
                'permissions' => [
                    'patients.view', 'patients.create', 'patients.edit',
                    'appointments.view', 'appointments.create', 'appointments.edit',
                    'treatments.view', 'treatments.create', 'treatments.edit',
                    'billing.view',
                    'profile.view', 'profile.edit',
                ],
            ],
            [
                'name' => 'nurse',
                'display_name' => 'Nurse',
                'description' => 'Nursing staff with patient care access',
                'level' => 40,
                'is_system' => false,
                'is_active' => true,
                'permissions' => [
                    'patients.view', 'patients.edit',
                    'appointments.view', 'appointments.edit',
                    'treatments.view',
                    'profile.view', 'profile.edit',
                ],
            ],
            [
                'name' => 'receptionist',
                'display_name' => 'Receptionist',
                'description' => 'Front desk staff with appointment and basic patient access',
                'level' => 30,
                'is_system' => false,
                'is_active' => true,
                'permissions' => [
                    'patients.view', 'patients.create', 'patients.edit',
                    'appointments.view', 'appointments.create', 'appointments.edit',
                    'billing.view',
                    'profile.view', 'profile.edit',
                ],
            ],
            [
                'name' => 'user',
                'display_name' => 'User',
                'description' => 'Basic user with limited access',
                'level' => 10,
                'is_system' => false,
                'is_active' => true,
                'permissions' => [
                    'profile.view', 'profile.edit',
                ],
            ],
        ];

        foreach ($roles as $roleData) {
            $permissions = $roleData['permissions'];
            unset($roleData['permissions']);

            $roleData['guard_name'] = 'web';
            $role = Role::firstOrCreate(
                ['name' => $roleData['name'], 'guard_name' => 'web'],
                $roleData
            );

            // Sync permissions
            $permissionIds = Permission::whereIn('name', $permissions)->pluck('id');
            $role->permissions()->sync($permissionIds);

            $this->command->info("✓ Role created: {$role->display_name} with " . count($permissions) . " permissions");
        }
    }

    private function seedUsers(): void
    {
        // Get or create the system tenant
        $tenant = \Modules\Core\Models\Tenant::where('slug', 'system')->first();
        if (!$tenant) {
            $this->command->warn('System tenant not found. Run CoreModuleSeeder first.');
            return;
        }

        $users = [
            [
                'first_name' => 'Super',
                'last_name' => 'Administrator',
                'email' => 'superadmin@xlinic.com',
                'username' => 'superadmin',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'status' => 'active',
                'roles' => ['super_admin'],
                'job_title' => 'System Administrator',
                'department' => 'IT',
                'timezone' => 'Africa/Cairo',
                'language' => 'en',
            ],
            [
                'first_name' => 'System',
                'last_name' => 'Admin',
                'email' => 'admin@xlinic.com',
                'username' => 'admin',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'status' => 'active',
                'roles' => ['admin'],
                'job_title' => 'Administrator',
                'department' => 'Administration',
                'timezone' => 'Africa/Cairo',
                'language' => 'en',
            ],
            [
                'first_name' => 'Ahmed',
                'last_name' => 'Mohamed',
                'email' => 'ahmed@example.com',
                'username' => 'ahmed',
                'password' => Hash::make('password123'),
                'phone' => '+201234567890',
                'email_verified_at' => now(),
                'status' => 'active',
                'roles' => ['doctor'],
                'job_title' => 'Senior Doctor',
                'department' => 'Medical',
                'gender' => 'male',
                'timezone' => 'Africa/Cairo',
                'language' => 'en',
            ],
            [
                'first_name' => 'Sara',
                'last_name' => 'Ali',
                'email' => 'sara@example.com',
                'username' => 'sara',
                'password' => Hash::make('password123'),
                'phone' => '+201987654321',
                'email_verified_at' => now(),
                'status' => 'active',
                'roles' => ['manager'],
                'job_title' => 'Clinic Manager',
                'department' => 'Management',
                'gender' => 'female',
                'timezone' => 'Africa/Cairo',
                'language' => 'en',
            ],
        ];

        foreach ($users as $userData) {
            $roles = $userData['roles'];
            unset($userData['roles']);

            $userData['tenant_id'] = $tenant->id;
            $userData['last_login_at'] = now()->subDays(rand(0, 30));

            $user = User::firstOrCreate(
                ['email' => $userData['email'], 'tenant_id' => $tenant->id],
                $userData
            );

            // Assign roles using Spatie
            $user->syncRoles($roles);

            $this->command->info("✓ User created: {$user->first_name} {$user->last_name} ({$user->email}) with roles: " . implode(', ', $roles));
        }
    }
}