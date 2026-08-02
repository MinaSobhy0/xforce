<?php

namespace Modules\OdooIntegration\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Booking\Models\PractitionerScheduleAssignment;
use Modules\Booking\Models\WorkSchedule;
use Modules\OdooIntegration\Events\SyncCompleted;
use Modules\OdooIntegration\Services\Api\OdooApiFactory;
use Modules\Staff\Models\StaffProfile;

/**
 * After a resource.calendar sync, mirror Odoo's employee↔calendar links
 * (hr.employee.resource_calendar_id) into practitioner_schedule_assignments.
 * Runs post-sync so every referenced schedule already exists locally.
 */
class ReconcileWorkScheduleAssignments
{
    public function handle(SyncCompleted $event): void
    {
        $mapping = $event->syncLog->entityMapping;

        if (! $mapping || $mapping->odoo_model !== 'resource.calendar') {
            return;
        }

        try {
            $client = app(OdooApiFactory::class)->make($mapping->connection);
            $client->authenticate();

            $pairs = $client->searchRead(
                'hr.employee',
                [['active', '=', true]],
                ['id', 'resource_calendar_id'],
                0,
                1000
            );
        } catch (\Throwable $e) {
            Log::warning('Work schedule assignment reconcile failed', ['error' => $e->getMessage()]);

            return;
        }

        $staffByOdoo = StaffProfile::whereNotNull('odoo_id')->pluck('id', 'odoo_id');
        $scheduleByOdoo = WorkSchedule::whereNotNull('odoo_id')->pluck('id', 'odoo_id');

        $updated = 0;
        foreach ($pairs as $emp) {
            $staffId = $staffByOdoo[(int) $emp['id']] ?? null;
            $calId = is_array($emp['resource_calendar_id'] ?? null) ? (int) $emp['resource_calendar_id'][0] : null;
            $scheduleId = $calId ? ($scheduleByOdoo[$calId] ?? null) : null;

            if (! $staffId || ! $scheduleId) {
                continue;
            }

            $current = PractitionerScheduleAssignment::where('staff_profile_id', $staffId)
                ->where('is_active', true)
                ->first();

            if ($current && $current->work_schedule_id === $scheduleId) {
                continue;
            }

            // Calendar changed in Odoo: retire the old assignment, add the new.
            if ($current) {
                $current->update(['is_active' => false, 'effective_until' => now()]);
            }

            PractitionerScheduleAssignment::create([
                'tenant_id' => $mapping->tenant_id,
                'staff_profile_id' => $staffId,
                'work_schedule_id' => $scheduleId,
                'is_active' => true,
                'is_primary' => true,
                'effective_from' => now(),
            ]);
            $updated++;
        }

        if ($updated > 0) {
            Log::channel('odoo')->info('Work schedule assignments reconciled', ['updated' => $updated]);
        }
    }
}
