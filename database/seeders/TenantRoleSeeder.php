<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\Permission;
use Modules\Core\Database\Seeders\Concerns\ResolveTenantId;

class TenantRoleSeeder extends Seeder
{
    use ResolveTenantId;

    public function run(): void
    {
        // Ensure tenant context is set
        $this->resolveTenantId();

        // Tenant-level roles with comprehensive permissions
        $tenantRoles = [
            // ============================================
            // OWNER - Full access to everything
            // ============================================
            'owner' => [
                'display_name' => 'Clinic Owner',
                'permissions' => [
                    // Administration
                    'users.view', 'users.create', 'users.edit', 'users.delete',
                    'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
                    'branches.view', 'branches.create', 'branches.edit', 'branches.delete',
                    'settings.view', 'settings.edit',
                    // Operations
                    'patients.view', 'patients.create', 'patients.edit', 'patients.delete',
                    'appointments.view', 'appointments.create', 'appointments.edit', 'appointments.delete',
                    'visits.view', 'visits.create', 'visits.edit', 'visits.delete',
                    'waitlist.view', 'waitlist.create', 'waitlist.edit', 'waitlist.delete',
                    // Medical
                    'treatment_plans.view', 'treatment_plans.create', 'treatment_plans.edit', 'treatment_plans.delete',
                    'prescriptions.view', 'prescriptions.create', 'prescriptions.edit', 'prescriptions.delete',
                    // Services
                    'services.view', 'services.create', 'services.edit', 'services.delete',
                    'service_categories.view', 'service_categories.create', 'service_categories.edit', 'service_categories.delete',
                    'packages.view', 'packages.create', 'packages.edit', 'packages.delete',
                    // Finance
                    'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete',
                    'payments.view', 'payments.create', 'payments.edit', 'payments.delete',
                    'gift_cards.view', 'gift_cards.create', 'gift_cards.edit', 'gift_cards.delete',
                    'memberships.view', 'memberships.create', 'memberships.edit', 'memberships.delete',
                    // Inventory
                    'products.view', 'products.create', 'products.edit', 'products.delete',
                    'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
                    'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.edit', 'purchase_orders.delete',
                    'stock_movements.view', 'stock_movements.create',
                    // Equipment
                    'equipment.view', 'equipment.create', 'equipment.edit', 'equipment.delete',
                    'rooms.view', 'rooms.create', 'rooms.edit', 'rooms.delete',
                    // HR
                    'staff.view', 'staff.create', 'staff.edit', 'staff.delete',
                    'attendance.view', 'attendance.create', 'attendance.edit',
                    'payroll.view', 'payroll.create', 'payroll.edit',
                    // Marketing
                    'campaigns.view', 'campaigns.create', 'campaigns.edit', 'campaigns.delete',
                    'loyalty.view', 'loyalty.create', 'loyalty.edit',
                    // Reports
                    'reports.view', 'reports.export',
                    // Accounting
                    'accounting.view', 'accounting.create', 'accounting.edit',
                ],
            ],

            // ============================================
            // ADMIN - Full operational access
            // ============================================
            'admin' => [
                'display_name' => 'Administrator',
                'permissions' => [
                    // Administration
                    'users.view', 'users.create', 'users.edit', 'users.delete',
                    'roles.view', 'roles.create', 'roles.edit',
                    'branches.view', 'branches.edit',
                    'settings.view', 'settings.edit',
                    // Operations
                    'patients.view', 'patients.create', 'patients.edit', 'patients.delete',
                    'appointments.view', 'appointments.create', 'appointments.edit', 'appointments.delete',
                    'visits.view', 'visits.create', 'visits.edit', 'visits.delete',
                    'waitlist.view', 'waitlist.create', 'waitlist.edit', 'waitlist.delete',
                    // Medical
                    'treatment_plans.view', 'treatment_plans.create', 'treatment_plans.edit', 'treatment_plans.delete',
                    'prescriptions.view', 'prescriptions.create', 'prescriptions.edit', 'prescriptions.delete',
                    // Services
                    'services.view', 'services.create', 'services.edit', 'services.delete',
                    'service_categories.view', 'service_categories.create', 'service_categories.edit', 'service_categories.delete',
                    'packages.view', 'packages.create', 'packages.edit', 'packages.delete',
                    // Finance
                    'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete',
                    'payments.view', 'payments.create', 'payments.edit', 'payments.delete',
                    'gift_cards.view', 'gift_cards.create', 'gift_cards.edit', 'gift_cards.delete',
                    'memberships.view', 'memberships.create', 'memberships.edit', 'memberships.delete',
                    // Inventory
                    'products.view', 'products.create', 'products.edit', 'products.delete',
                    'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
                    'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.edit', 'purchase_orders.delete',
                    'stock_movements.view', 'stock_movements.create',
                    // Equipment
                    'equipment.view', 'equipment.create', 'equipment.edit', 'equipment.delete',
                    'rooms.view', 'rooms.create', 'rooms.edit', 'rooms.delete',
                    // HR
                    'staff.view', 'staff.create', 'staff.edit', 'staff.delete',
                    'attendance.view', 'attendance.create', 'attendance.edit',
                    'payroll.view', 'payroll.create', 'payroll.edit',
                    // Marketing
                    'campaigns.view', 'campaigns.create', 'campaigns.edit', 'campaigns.delete',
                    'loyalty.view', 'loyalty.create', 'loyalty.edit',
                    // Reports
                    'reports.view', 'reports.export',
                ],
            ],

            // ============================================
            // MANAGER - Operational management
            // ============================================
            'manager' => [
                'display_name' => 'Manager',
                'permissions' => [
                    // Limited admin
                    'users.view', 'users.create', 'users.edit',
                    'roles.view',
                    'branches.view',
                    // Operations
                    'patients.view', 'patients.create', 'patients.edit',
                    'appointments.view', 'appointments.create', 'appointments.edit', 'appointments.delete',
                    'visits.view', 'visits.create', 'visits.edit',
                    'waitlist.view', 'waitlist.create', 'waitlist.edit', 'waitlist.delete',
                    // Services
                    'services.view', 'services.create', 'services.edit',
                    'service_categories.view',
                    'packages.view', 'packages.create', 'packages.edit',
                    // Finance
                    'invoices.view', 'invoices.create', 'invoices.edit',
                    'payments.view', 'payments.create', 'payments.edit',
                    'gift_cards.view', 'gift_cards.create', 'gift_cards.edit',
                    'memberships.view', 'memberships.create', 'memberships.edit',
                    // Inventory
                    'products.view', 'products.create', 'products.edit',
                    'suppliers.view', 'suppliers.create', 'suppliers.edit',
                    'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.edit',
                    'stock_movements.view', 'stock_movements.create',
                    // Equipment
                    'equipment.view', 'equipment.edit',
                    'rooms.view', 'rooms.edit',
                    // HR
                    'staff.view', 'staff.create', 'staff.edit',
                    'attendance.view', 'attendance.create', 'attendance.edit',
                    // Marketing
                    'campaigns.view', 'campaigns.create', 'campaigns.edit',
                    'loyalty.view', 'loyalty.create',
                    // Reports
                    'reports.view', 'reports.export',
                ],
            ],

            // ============================================
            // DOCTOR - Medical professional
            // ============================================
            'doctor' => [
                'display_name' => 'Doctor',
                'permissions' => [
                    // View staff
                    'users.view',
                    // Full patient medical access
                    'patients.view', 'patients.create', 'patients.edit',
                    'appointments.view', 'appointments.create', 'appointments.edit',
                    'visits.view', 'visits.create', 'visits.edit',
                    'waitlist.view',
                    // Medical
                    'treatment_plans.view', 'treatment_plans.create', 'treatment_plans.edit',
                    'prescriptions.view', 'prescriptions.create', 'prescriptions.edit',
                    // Services (view for treatment planning)
                    'services.view',
                    'packages.view',
                    // Equipment (view for procedures)
                    'equipment.view',
                    'rooms.view',
                    // Products (consumables used in treatment)
                    'products.view',
                    // View own schedule
                    'staff.view',
                ],
            ],

            // ============================================
            // NURSE - Medical support
            // ============================================
            'nurse' => [
                'display_name' => 'Nurse',
                'permissions' => [
                    'users.view',
                    // Patient care
                    'patients.view', 'patients.edit',
                    'appointments.view', 'appointments.edit',
                    'visits.view', 'visits.create', 'visits.edit',
                    'waitlist.view', 'waitlist.create', 'waitlist.edit',
                    // Medical support
                    'treatment_plans.view',
                    'prescriptions.view',
                    // Services
                    'services.view',
                    // Equipment
                    'equipment.view',
                    'rooms.view',
                    // Products
                    'products.view',
                    'stock_movements.view',
                    // Staff schedule
                    'staff.view',
                ],
            ],

            // ============================================
            // TECHNICIAN - Equipment & procedures
            // ============================================
            'technician' => [
                'display_name' => 'Technician',
                'permissions' => [
                    'users.view',
                    // Patient interaction
                    'patients.view',
                    'appointments.view',
                    'visits.view', 'visits.edit',
                    // Services
                    'services.view',
                    // Equipment focused
                    'equipment.view', 'equipment.edit',
                    'rooms.view',
                    // Products & consumables
                    'products.view',
                    'stock_movements.view', 'stock_movements.create',
                    // Staff schedule
                    'staff.view',
                ],
            ],

            // ============================================
            // RECEPTIONIST - Front desk operations
            // ============================================
            'receptionist' => [
                'display_name' => 'Receptionist',
                'permissions' => [
                    'users.view',
                    // Full scheduling access
                    'patients.view', 'patients.create', 'patients.edit',
                    'appointments.view', 'appointments.create', 'appointments.edit', 'appointments.delete',
                    'visits.view', 'visits.create',
                    'waitlist.view', 'waitlist.create', 'waitlist.edit', 'waitlist.delete',
                    // Services info
                    'services.view',
                    'packages.view',
                    // Payment collection
                    'invoices.view', 'invoices.create',
                    'payments.view', 'payments.create',
                    'gift_cards.view',
                    'memberships.view',
                    // Room availability
                    'rooms.view',
                    // Staff schedules
                    'staff.view',
                ],
            ],

            // ============================================
            // SALES - Sales & revenue focused
            // ============================================
            'sales' => [
                'display_name' => 'Sales',
                'permissions' => [
                    'users.view',
                    // Customer management
                    'patients.view', 'patients.create', 'patients.edit',
                    'appointments.view', 'appointments.create', 'appointments.edit',
                    // Services & packages
                    'services.view',
                    'packages.view', 'packages.create', 'packages.edit',
                    // Sales transactions
                    'invoices.view', 'invoices.create', 'invoices.edit',
                    'payments.view', 'payments.create',
                    'gift_cards.view', 'gift_cards.create', 'gift_cards.edit',
                    'memberships.view', 'memberships.create', 'memberships.edit',
                    // Marketing
                    'campaigns.view',
                    'loyalty.view', 'loyalty.create',
                    // Products (retail)
                    'products.view',
                    // Reports
                    'reports.view',
                ],
            ],

            // ============================================
            // STAFF - Basic access
            // ============================================
            'staff' => [
                'display_name' => 'Staff',
                'permissions' => [
                    'users.view',
                    // Basic view access
                    'patients.view',
                    'appointments.view',
                    'services.view',
                    'rooms.view',
                    'staff.view',
                ],
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
                [
                    'display_name' => $roleData['display_name'],
                    'is_active' => true,
                    'is_system' => in_array($roleName, ['owner', 'admin']),
                ]
            );

            // Always sync permissions (update existing roles too)
            $permissions = Permission::whereIn('name', $roleData['permissions'])->get();
            $role->syncPermissions($permissions);
        }
    }
}
