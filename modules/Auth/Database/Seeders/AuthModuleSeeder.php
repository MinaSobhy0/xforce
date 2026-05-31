<?php

namespace Modules\Auth\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Models\Permission;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;

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
            ['name' => 'tenants.view_any', 'display_name' => 'View Tenants List', 'module' => 'core'],
            ['name' => 'tenants.view', 'display_name' => 'View Tenant', 'module' => 'core'],
            ['name' => 'tenants.create', 'display_name' => 'Create Tenants', 'module' => 'core'],
            ['name' => 'tenants.update', 'display_name' => 'Update Tenants', 'module' => 'core'],
            ['name' => 'tenants.delete', 'display_name' => 'Delete Tenants', 'module' => 'core'],

            // Module Management
            ['name' => 'modules.view', 'display_name' => 'View Modules', 'module' => 'core'],
            ['name' => 'modules.manage', 'display_name' => 'Manage Modules', 'module' => 'core'],

            // User Management
            ['name' => 'users.view_any', 'display_name' => 'View Users List', 'module' => 'auth'],
            ['name' => 'users.view', 'display_name' => 'View User', 'module' => 'auth'],
            ['name' => 'users.create', 'display_name' => 'Create Users', 'module' => 'auth'],
            ['name' => 'users.update', 'display_name' => 'Update Users', 'module' => 'auth'],
            ['name' => 'users.delete', 'display_name' => 'Delete Users', 'module' => 'auth'],
            ['name' => 'users.impersonate', 'display_name' => 'Impersonate Users', 'module' => 'auth'],

            // Role Management
            ['name' => 'roles.view_any', 'display_name' => 'View Roles List', 'module' => 'auth'],
            ['name' => 'roles.view', 'display_name' => 'View Role', 'module' => 'auth'],
            ['name' => 'roles.create', 'display_name' => 'Create Roles', 'module' => 'auth'],
            ['name' => 'roles.update', 'display_name' => 'Update Roles', 'module' => 'auth'],
            ['name' => 'roles.delete', 'display_name' => 'Delete Roles', 'module' => 'auth'],

            // Profile Management
            ['name' => 'profile.view', 'display_name' => 'View Profile', 'module' => 'auth'],
            ['name' => 'profile.update', 'display_name' => 'Update Profile', 'module' => 'auth'],

            // Patient Management
            ['name' => 'patients.view_any', 'display_name' => 'View Patients List', 'module' => 'patients'],
            ['name' => 'patients.view', 'display_name' => 'View Patient', 'module' => 'patients'],
            ['name' => 'patients.create', 'display_name' => 'Create Patients', 'module' => 'patients'],
            ['name' => 'patients.update', 'display_name' => 'Update Patients', 'module' => 'patients'],
            ['name' => 'patients.delete', 'display_name' => 'Delete Patients', 'module' => 'patients'],

            // Appointment Management
            ['name' => 'appointments.view_any', 'display_name' => 'View Appointments List', 'module' => 'booking'],
            ['name' => 'appointments.view', 'display_name' => 'View Appointment', 'module' => 'booking'],
            ['name' => 'appointments.create', 'display_name' => 'Create Appointments', 'module' => 'booking'],
            ['name' => 'appointments.update', 'display_name' => 'Update Appointments', 'module' => 'booking'],
            ['name' => 'appointments.delete', 'display_name' => 'Delete Appointments', 'module' => 'booking'],

            // Treatment Management
            ['name' => 'treatment_plans.view_any', 'display_name' => 'View Treatment Plans List', 'module' => 'treatment_plans'],
            ['name' => 'treatment_plans.view', 'display_name' => 'View Treatment Plan', 'module' => 'treatment_plans'],
            ['name' => 'treatment_plans.create', 'display_name' => 'Create Treatment Plans', 'module' => 'treatment_plans'],
            ['name' => 'treatment_plans.update', 'display_name' => 'Update Treatment Plans', 'module' => 'treatment_plans'],
            ['name' => 'treatment_plans.delete', 'display_name' => 'Delete Treatment Plans', 'module' => 'treatment_plans'],

            // Billing Management
            ['name' => 'invoices.view_any', 'display_name' => 'View Invoices List', 'module' => 'billing'],
            ['name' => 'invoices.view', 'display_name' => 'View Invoice', 'module' => 'billing'],
            ['name' => 'invoices.create', 'display_name' => 'Create Invoices', 'module' => 'billing'],
            ['name' => 'invoices.update', 'display_name' => 'Update Invoices', 'module' => 'billing'],
            ['name' => 'invoices.delete', 'display_name' => 'Delete Invoices', 'module' => 'billing'],

            // Reports
            ['name' => 'reports.view_any', 'display_name' => 'View Reports List', 'module' => 'reporting'],
            ['name' => 'reports.view', 'display_name' => 'View Report', 'module' => 'reporting'],
            ['name' => 'reports.export', 'display_name' => 'Export Reports', 'module' => 'reporting'],
        ];

        foreach ($permissions as $permission) {
            $permission['guard_name'] = 'web';
            Permission::firstOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'web'],
                $permission
            );
        }

        // Custom (action-level) abilities declared by resources — e.g.
        // payslips.view_own. Derived from the single source of truth so new
        // abilities are seeded automatically.
        $customCount = 0;
        foreach (\Modules\Auth\Filament\Resources\RoleResource::getCustomAbilitiesMap() as $resource => $abilities) {
            foreach ($abilities as $ability) {
                Permission::firstOrCreate(
                    ['name' => "{$resource}.{$ability}", 'guard_name' => 'web'],
                    [
                        'name' => "{$resource}.{$ability}",
                        'display_name' => \Illuminate\Support\Str::headline($resource).' — '.\Illuminate\Support\Str::headline($ability),
                        'module' => $resource,
                        'guard_name' => 'web',
                    ]
                );
                $customCount++;
            }
        }

        $this->command->info('✓ Permissions created: '.(count($permissions) + $customCount));
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
                    'users.view_any', 'users.view', 'users.create', 'users.update',
                    'roles.view_any', 'roles.view', 'roles.create', 'roles.update',
                    'patients.view_any', 'patients.view', 'patients.create', 'patients.update', 'patients.delete',
                    'appointments.view_any', 'appointments.view', 'appointments.create', 'appointments.update', 'appointments.delete',
                    'treatment_plans.view_any', 'treatment_plans.view', 'treatment_plans.create', 'treatment_plans.update', 'treatment_plans.delete',
                    'invoices.view_any', 'invoices.view', 'invoices.create', 'invoices.update',
                    'reports.view_any', 'reports.view', 'reports.export',
                    'profile.view', 'profile.update',
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
                    'users.view_any', 'users.view', 'users.create', 'users.update',
                    'patients.view_any', 'patients.view', 'patients.create', 'patients.update', 'patients.delete',
                    'appointments.view_any', 'appointments.view', 'appointments.create', 'appointments.update', 'appointments.delete',
                    'treatment_plans.view_any', 'treatment_plans.view', 'treatment_plans.create', 'treatment_plans.update',
                    'invoices.view_any', 'invoices.view', 'invoices.create', 'invoices.update',
                    'reports.view_any', 'reports.view',
                    'profile.view', 'profile.update',
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
                    'patients.view_any', 'patients.view', 'patients.create', 'patients.update',
                    'appointments.view_any', 'appointments.view', 'appointments.create', 'appointments.update',
                    'treatment_plans.view_any', 'treatment_plans.view', 'treatment_plans.create', 'treatment_plans.update',
                    'invoices.view_any', 'invoices.view',
                    'profile.view', 'profile.update',
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
                    'patients.view_any', 'patients.view', 'patients.update',
                    'appointments.view_any', 'appointments.view', 'appointments.update',
                    'treatment_plans.view_any', 'treatment_plans.view',
                    'profile.view', 'profile.update',
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
                    'patients.view_any', 'patients.view', 'patients.create', 'patients.update',
                    'appointments.view_any', 'appointments.view', 'appointments.create', 'appointments.update',
                    'invoices.view_any', 'invoices.view',
                    'profile.view', 'profile.update',
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
                    'profile.view', 'profile.update',
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

            $this->command->info("✓ Role created: {$role->display_name} with ".count($permissions).' permissions');
        }
    }

    private function seedUsers(): void
    {
        // Get or create the system tenant
        $tenant = \Modules\Core\Models\Tenant::where('slug', 'system')->first();
        if (! $tenant) {
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

            $this->command->info("✓ User created: {$user->first_name} {$user->last_name} ({$user->email}) with roles: ".implode(', ', $roles));
        }
    }
}
