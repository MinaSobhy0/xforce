<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Permission renames from legacy to standardized names.
     */
    protected array $renames = [
        // Auth module
        'users.edit' => 'users.update',
        'roles.edit' => 'roles.update',
        'permissions.edit' => 'permissions.update',

        // Patients module
        'patients.edit' => 'patients.update',
        'patients.edit_medical_history' => 'patients.update_medical_history',

        // Services module
        'services.edit' => 'services.update',
        'service_categories.edit' => 'service_categories.update',
        'consent_templates.edit' => 'consent_templates.update',

        // Booking module
        'appointments.edit' => 'appointments.update',
        'work_schedules.edit' => 'work_schedules.update',
        'booking_rules.edit' => 'booking_rules.update',

        // Billing module
        'invoices.edit' => 'invoices.update',
        'payments.edit' => 'payments.update',
        'tax_rates.edit' => 'tax_rates.update',

        // Inventory module
        'products.edit' => 'products.update',
        'product_categories.edit' => 'product_categories.update',
        'suppliers.edit' => 'suppliers.update',
        'purchase_orders.edit' => 'purchase_orders.update',
        'vendor_bills.edit' => 'vendor_bills.update',
        'stock_locations.edit' => 'stock_locations.update',
        'uoms.edit' => 'uoms.update',
        'uom_categories.edit' => 'uom_categories.update',

        // Accounting module
        'journals.edit' => 'journals.update',
        'journal_entries.edit' => 'journal_entries.update',
        'chart_of_accounts.edit' => 'chart_of_accounts.update',
        'fiscal_periods.edit' => 'fiscal_periods.update',

        // Staff module
        'staff.edit' => 'staff.update',
        'staff_profiles.edit' => 'staff_profiles.update',
        'commission_plans.edit' => 'commission_plans.update',

        // Payroll module
        'payroll_runs.edit' => 'payroll_runs.update',
        'payslips.edit' => 'payslips.update',
        'salary_structures.edit' => 'salary_structures.update',

        // Equipment module
        'equipment.edit' => 'equipment.update',

        // Assets module
        'assets.edit' => 'assets.update',
        'asset_types.edit' => 'asset_types.update',

        // Packages module
        'packages.edit' => 'packages.update',
        'package_subscriptions.edit' => 'package_subscriptions.update',

        // Memberships module
        'memberships.edit' => 'memberships.update',

        // GiftCards module
        'gift_cards.edit' => 'gift_cards.update',
        'gift_card_templates.edit' => 'gift_card_templates.update',

        // Marketing module
        'campaigns.edit' => 'campaigns.update',
        'message_templates.edit' => 'message_templates.update',

        // Loyalty module
        'loyalty_rules.edit' => 'loyalty_rules.update',
        'referral_programs.edit' => 'referral_programs.update',

        // TreatmentPlans module
        'treatment_plans.edit' => 'treatment_plans.update',

        // Prescriptions module
        'prescriptions.edit' => 'prescriptions.update',
        'medicine_catalog.edit' => 'medicine_catalog.update',

        // Attendance module
        'attendance.edit' => 'attendance.update',
        'attendance_rules.edit' => 'attendance_rules.update',

        // Core module
        'branches.edit' => 'branches.update',
        'rooms.edit' => 'rooms.update',

        // Expand .manage to individual permissions (for roles that had .manage)
        // These will be handled separately
    ];

    /**
     * Permissions to add (view_any for list views).
     */
    protected array $newPermissions = [
        // Auth
        'users.view_any',
        'roles.view_any',
        'permissions.view_any',

        // Patients
        'patients.view_any',

        // Services
        'services.view_any',
        'service_categories.view_any',
        'consent_templates.view_any',

        // Booking
        'appointments.view_any',
        'work_schedules.view_any',
        'booking_rules.view_any',

        // Billing
        'invoices.view_any',
        'payments.view_any',
        'tax_rates.view_any',

        // Inventory
        'products.view_any',
        'product_categories.view_any',
        'suppliers.view_any',
        'purchase_orders.view_any',
        'vendor_bills.view_any',
        'stock_locations.view_any',
        'inventory_adjustments.view_any',
        'stock_transfers.view_any',
        'uoms.view_any',
        'uom_categories.view_any',

        // Accounting
        'journals.view_any',
        'journal_entries.view_any',
        'chart_of_accounts.view_any',
        'fiscal_periods.view_any',

        // Staff
        'staff_profiles.view_any',
        'commission_plans.view_any',

        // Payroll
        'payroll_runs.view_any',
        'payslips.view_any',
        'salary_structures.view_any',

        // Equipment
        'equipment.view_any',

        // Assets
        'assets.view_any',
        'asset_types.view_any',

        // Packages
        'packages.view_any',
        'package_subscriptions.view_any',

        // Memberships
        'memberships.view_any',

        // GiftCards
        'gift_cards.view_any',
        'gift_card_templates.view_any',

        // Marketing
        'campaigns.view_any',
        'message_templates.view_any',
        'notification_logs.view_any',
        'automation_rules.view_any',

        // Loyalty
        'loyalty_rules.view_any',
        'loyalty_transactions.view_any',
        'referral_programs.view_any',

        // TreatmentPlans
        'treatment_plans.view_any',

        // Prescriptions
        'prescriptions.view_any',
        'medicine_catalog.view_any',

        // Attendance
        'attendance.view_any',
        'attendance_rules.view_any',

        // Core
        'branches.view_any',
        'rooms.view_any',

        // Reporting
        'reports.view_any',
        'reports.view',
        'reports.export',
    ];

    public function up(): void
    {
        // Rename existing permissions
        foreach ($this->renames as $old => $new) {
            DB::table('permissions')
                ->where('name', $old)
                ->where('guard_name', 'web')
                ->update(['name' => $new]);
        }

        // Add new permissions if they don't exist
        foreach ($this->newPermissions as $permission) {
            $exists = DB::table('permissions')
                ->where('name', $permission)
                ->where('guard_name', 'web')
                ->exists();

            if (!$exists) {
                DB::table('permissions')->insert([
                    'name' => $permission,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Clear permission cache
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Reverse renames
        foreach ($this->renames as $old => $new) {
            DB::table('permissions')
                ->where('name', $new)
                ->where('guard_name', 'web')
                ->update(['name' => $old]);
        }

        // Remove new permissions
        DB::table('permissions')
            ->whereIn('name', $this->newPermissions)
            ->where('guard_name', 'web')
            ->delete();

        // Clear permission cache
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
