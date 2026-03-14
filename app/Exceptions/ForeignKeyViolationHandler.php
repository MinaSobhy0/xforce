<?php

namespace App\Exceptions;

use Filament\Notifications\Notification;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ForeignKeyViolationHandler
{
    /**
     * Map of table names to translation keys.
     */
    protected static array $tableTranslationMap = [
        // Booking module
        'appointments' => 'booking::booking.labels.appointments',
        'practitioner_schedules' => 'booking::booking.schedules',
        'practitioner_time_off' => 'booking::booking.time_off',
        'visits' => 'booking::booking.visits',

        // Billing module
        'invoices' => 'billing::billing.invoices',
        'invoice_lines' => 'billing::billing.line_types.service',
        'payments' => 'billing::billing.payments',

        // Treatment Plans module
        'treatment_plans' => 'treatment_plans::treatment_plans.treatment_plans',
        'treatment_plan_items' => 'treatment_plans::treatment_plans.items',

        // Packages module
        'packages' => 'packages::packages.packages',
        'package_items' => 'packages::packages.items',
        'package_subscriptions' => 'packages::packages.subscriptions',

        // Inventory module
        'stock_movements' => 'inventory::inventory.stock_movements',
        'purchase_orders' => 'inventory::inventory.purchase_orders',
        'purchase_order_lines' => 'inventory::inventory.purchase_order_lines',

        // Payroll module
        'payslips' => 'payroll::payroll.payslips',
        'payroll_runs' => 'payroll::payroll.payroll_runs',

        // Staff module
        'staff_profiles' => 'staff::staff.staff_profiles',
        'commissions' => 'staff::staff.commissions',

        // Services module
        'services' => 'services::services.services',
        'service_categories' => 'services::services.categories',

        // Patients module
        'patients' => 'patients::patients.patients',
        'patient_documents' => 'patients::patients.documents',
        'medical_records' => 'patients::patients.medical_records',

        // Products (inventory)
        'products' => 'inventory::inventory.products',

        // Core module
        'branches' => 'core::core.branches',
        'rooms' => 'core::core.rooms',
        'users' => 'core::core.users',

        // Gift cards
        'gift_cards' => 'giftcards::giftcards.gift_cards',

        // Memberships
        'memberships' => 'memberships::memberships.memberships',
        'member_subscriptions' => 'memberships::memberships.subscriptions',
    ];

    /**
     * Handle a foreign key violation exception.
     */
    public function handle(QueryException $e, Request $request): JsonResponse|RedirectResponse|Response|null
    {
        // Check for PostgreSQL foreign key violation (error code 23503)
        if ($e->getCode() !== '23503') {
            return null;
        }

        $message = $e->getMessage();
        $referencingTable = $this->extractReferencingTable($message);
        $displayName = $this->getTableDisplayName($referencingTable);

        $errorMessage = __('core::core.deletion_blocked.message', ['relation' => $displayName]);

        // Send Filament notification (stores in session)
        Notification::make()
            ->title(__('core::core.deletion_blocked.title'))
            ->body($errorMessage)
            ->danger()
            ->persistent()
            ->send();

        // For Livewire requests - skip handling here, handle at Filament level
        if ($this->isLivewireRequest($request)) {
            return null;
        }

        // For regular AJAX/JSON requests
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'message' => $errorMessage,
            ], 422);
        }

        // For regular web requests - redirect back
        return back();
    }

    /**
     * Check if this is a Livewire request.
     */
    protected function isLivewireRequest(Request $request): bool
    {
        // Check for Livewire-specific indicators
        return $request->hasHeader('X-Livewire')
            || str_contains($request->header('Content-Type', ''), 'livewire')
            || $request->has('components')
            || str_contains($request->path(), 'livewire');
    }

    /**
     * Extract the referencing table name from the PostgreSQL error message.
     *
     * Example error message:
     * 'SQLSTATE[23503]: Foreign key violation: 7 ERROR: update or delete on table "patients"
     *  violates foreign key constraint "appointments_patient_id_foreign" on table "appointments"'
     */
    protected function extractReferencingTable(string $message): ?string
    {
        // Match the last occurrence of 'on table "tablename"'
        if (preg_match('/on table "([^"]+)"[^"]*$/', $message, $matches)) {
            return $matches[1];
        }

        // Fallback: try to extract from constraint name (e.g., "appointments_patient_id_foreign")
        if (preg_match('/constraint "([^"]+)_[^"]+_foreign"/', $message, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get the display name for a table, using translations when available.
     */
    protected function getTableDisplayName(?string $table): string
    {
        if ($table === null) {
            return __('core::core.deletion_blocked.related_records');
        }

        // Check if we have a translation mapping for this table
        if (isset(self::$tableTranslationMap[$table])) {
            $translated = __(self::$tableTranslationMap[$table]);

            // If translation exists and is different from the key, use it
            if ($translated !== self::$tableTranslationMap[$table]) {
                return $translated;
            }
        }

        // Fallback: humanize the table name
        return str_replace('_', ' ', ucfirst($table));
    }

    /**
     * Check if the exception is a foreign key violation.
     */
    public static function isForeignKeyViolation(QueryException $e): bool
    {
        return $e->getCode() === '23503';
    }
}
