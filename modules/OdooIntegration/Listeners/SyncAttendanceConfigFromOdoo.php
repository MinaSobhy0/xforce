<?php

namespace Modules\OdooIntegration\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\OdooIntegration\Events\SyncCompleted;
use Modules\OdooIntegration\Services\Api\OdooApiFactory;

/**
 * After each hr.attendance sweep, mirror the custom Odoo attendance
 * configuration (xs.attendance.config — multiple check-in/out policy with
 * employee/department scoping) into the tenant's settings. Department
 * scopes are expanded to employee odoo ids at sync time so the app can
 * evaluate per-staff without a department sync.
 */
class SyncAttendanceConfigFromOdoo
{
    public function handle(SyncCompleted $event): void
    {
        $mapping = $event->syncLog->entityMapping;

        if (! $mapping || $mapping->odoo_model !== 'hr.attendance') {
            return;
        }

        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        if (! $tenant) {
            return;
        }

        try {
            $client = app(OdooApiFactory::class)->make($mapping->connection);
            $client->authenticate();

            $config = $client->searchRead('xs.attendance.config', [], [
                'allow_multiple_check_in_out', 'application_scope',
                'allowed_employee_ids', 'allowed_department_ids',
            ], 0, 1)[0] ?? null;
        } catch (\Throwable $e) {
            // Custom model absent on this Odoo — nothing to mirror.
            Log::channel('odoo')->info('xs.attendance.config not readable', ['error' => substr($e->getMessage(), 0, 120)]);

            return;
        }

        if (! $config) {
            return;
        }

        $employeeIds = array_map('intval', (array) ($config['allowed_employee_ids'] ?? []));

        // Expand department scope into employee ids.
        $departmentIds = array_map('intval', (array) ($config['allowed_department_ids'] ?? []));
        if (! empty($departmentIds)) {
            try {
                $rows = $client->searchRead('hr.employee', [
                    ['department_id', 'in', $departmentIds], ['active', '=', true],
                ], ['id'], 0, 1000);
                $employeeIds = array_values(array_unique(array_merge(
                    $employeeIds,
                    array_map(fn ($r) => (int) $r['id'], $rows)
                )));
            } catch (\Throwable) {
                // Keep the explicit employee list.
            }
        }

        $tenant->setSetting('attendance.multiple_check_in', [
            'enabled' => (bool) ($config['allow_multiple_check_in_out'] ?? false),
            'scope' => (string) ($config['application_scope'] ?? 'all'),
            'employee_odoo_ids' => $employeeIds,
            'synced_at' => now()->toIso8601String(),
        ]);
        $tenant->save();
    }
}
