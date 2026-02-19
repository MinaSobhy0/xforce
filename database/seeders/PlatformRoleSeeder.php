<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PlatformRoleSeeder extends Seeder
{
    public function run(): void
    {
        // Platform-level roles (no guard specified, uses default)
        $platformRoles = [
            'platform_support_lead' => [
                'view_tenants',
                'manage_tenants',
                'login_as_tenant',
                'view_tickets',
                'manage_tickets',
                'view_announcements',
                'manage_announcements',
            ],
            'platform_support' => [
                'view_tenants',
                'view_tickets',
                'manage_tickets',
            ],
            'platform_billing' => [
                'view_invoices',
                'manage_invoices',
                'view_subscriptions',
                'manage_subscriptions',
                'view_revenue',
                'view_promo_codes',
                'manage_promo_codes',
            ],
            'platform_readonly' => [
                'view_dashboard',
                'view_analytics',
                'view_tenants',
            ],
        ];

        // Create permissions if they don't exist
        $allPermissions = collect($platformRoles)->flatten()->unique();

        foreach ($allPermissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions
        foreach ($platformRoles as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);
        }

        // Ensure super_admin has all permissions
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::where('guard_name', 'web')->get());
    }
}
