<?php

namespace Modules\TreatmentPlans\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\TreatmentPlans\Models\TreatmentPlan;
use Modules\TreatmentPlans\Models\TreatmentPlanItem;
use Modules\TreatmentPlans\Models\TreatmentPlanAppointment;
use Modules\Booking\Models\Appointment;
use Modules\Packages\Models\Package;
use Modules\Packages\Models\PackageSubscription;
use Modules\Patients\Models\Patient;

class TreatmentPlanService
{
    /**
     * Create a treatment plan with items from a package template.
     */
    public function createFromPackage(
        Patient $patient,
        Package $package,
        string $branchId,
        array $additionalData = []
    ): TreatmentPlan {
        return DB::transaction(function () use ($patient, $package, $branchId, $additionalData) {
            // Create the treatment plan
            $plan = TreatmentPlan::create([
                'tenant_id' => $patient->tenant_id,
                'patient_id' => $patient->id,
                'branch_id' => $branchId,
                'name' => $package->name,
                'description' => $package->description,
                'source' => $additionalData['source'] ?? TreatmentPlan::SOURCE_MANUAL,
                'recommended_package_id' => $package->id,
                'created_by_user_id' => auth()->id(),
                ...$additionalData,
            ]);

            // Create items from package items
            foreach ($package->items as $index => $packageItem) {
                TreatmentPlanItem::create([
                    'tenant_id' => $patient->tenant_id,
                    'treatment_plan_id' => $plan->id,
                    'service_id' => $packageItem->service_id,
                    'recommended_sessions' => $packageItem->quantity,
                    'session_interval_days' => config('treatment_plans.default_session_interval_days', 7),
                    'sort_order' => $index,
                ]);
            }

            return $plan->load('items');
        });
    }

    /**
     * Link a package subscription to a treatment plan.
     */
    public function linkPackageSubscription(
        TreatmentPlan $plan,
        PackageSubscription $subscription
    ): bool {
        // Validate subscription belongs to same patient
        if ($subscription->patient_id !== $plan->patient_id) {
            throw new \InvalidArgumentException('Package subscription belongs to a different patient.');
        }

        // Validate package matches recommended package (if set)
        if ($plan->recommended_package_id && $subscription->package_id !== $plan->recommended_package_id) {
            throw new \InvalidArgumentException('Package subscription does not match recommended package.');
        }

        return $plan->linkPackageSubscription($subscription);
    }

    /**
     * Book an appointment for a treatment plan item.
     */
    public function bookAppointment(
        TreatmentPlanItem $item,
        array $appointmentData
    ): Appointment {
        return DB::transaction(function () use ($item, $appointmentData) {
            // Validate item can be booked
            if (!$item->canBook()) {
                throw new \Exception('Cannot book appointment for this treatment plan item.');
            }

            // Create the appointment
            $appointment = Appointment::create([
                'tenant_id' => $item->tenant_id,
                'patient_id' => $item->treatmentPlan->patient_id,
                'service_id' => $item->service_id,
                ...$appointmentData,
            ]);

            // Create the link to treatment plan
            TreatmentPlanAppointment::create([
                'tenant_id' => $item->tenant_id,
                'treatment_plan_item_id' => $item->id,
                'appointment_id' => $appointment->id,
                'session_number' => $item->next_session_number,
                'status' => $appointment->status,
            ]);

            return $appointment;
        });
    }

    /**
     * Handle appointment completion - update treatment plan progress.
     */
    public function handleAppointmentCompleted(Appointment $appointment): void
    {
        // Find the treatment plan appointment link
        $planAppointment = TreatmentPlanAppointment::where('appointment_id', $appointment->id)->first();

        if (!$planAppointment) {
            return;
        }

        DB::transaction(function () use ($appointment, $planAppointment) {
            // Update the plan appointment status
            $planAppointment->status = $appointment->status;
            $planAppointment->save();

            // Increment completed sessions on the item
            $item = $planAppointment->item;
            $item->incrementCompletedSessions();

            // Check if plan should auto-complete
            $item->treatmentPlan->checkAndMarkComplete();
        });
    }

    /**
     * Handle appointment cancellation.
     */
    public function handleAppointmentCancelled(Appointment $appointment): void
    {
        $planAppointment = TreatmentPlanAppointment::where('appointment_id', $appointment->id)->first();

        if (!$planAppointment) {
            return;
        }

        // Update the plan appointment status
        $planAppointment->status = $appointment->status;
        $planAppointment->save();
    }

    /**
     * Handle appointment status change.
     */
    public function handleAppointmentStatusChanged(Appointment $appointment): void
    {
        $planAppointment = TreatmentPlanAppointment::where('appointment_id', $appointment->id)->first();

        if (!$planAppointment) {
            return;
        }

        $planAppointment->syncStatusFromAppointment();
    }

    /**
     * Get active treatment plans for a patient.
     */
    public function getActivePlansForPatient(string $patientId): \Illuminate\Database\Eloquent\Collection
    {
        return TreatmentPlan::forPatient($patientId)
            ->active()
            ->with(['items.service', 'branch'])
            ->withProgress()
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get items that need scheduling for a treatment plan.
     */
    public function getItemsNeedingScheduling(TreatmentPlan $plan): \Illuminate\Database\Eloquent\Collection
    {
        return $plan->items()
            ->needsScheduling()
            ->with(['service', 'preferredPractitioner'])
            ->ordered()
            ->get();
    }

    /**
     * Calculate progress statistics for a treatment plan.
     */
    public function calculateProgress(TreatmentPlan $plan): array
    {
        $items = $plan->items;

        $totalRecommended = $items->sum('recommended_sessions');
        $totalCompleted = $items->sum('completed_sessions');
        $totalScheduled = $items->sum(function ($item) {
            return $item->planAppointments()
                ->scheduled()
                ->count();
        });

        return [
            'total_recommended' => $totalRecommended,
            'total_completed' => $totalCompleted,
            'total_scheduled' => $totalScheduled,
            'total_remaining' => max(0, $totalRecommended - $totalCompleted),
            'percentage' => $totalRecommended > 0
                ? round(($totalCompleted / $totalRecommended) * 100, 1)
                : 0,
            'items_count' => $items->count(),
            'items_completed' => $items->where('status', TreatmentPlanItem::STATUS_COMPLETED)->count(),
            'items_in_progress' => $items->where('status', TreatmentPlanItem::STATUS_IN_PROGRESS)->count(),
            'items_pending' => $items->where('status', TreatmentPlanItem::STATUS_PENDING)->count(),
        ];
    }

    /**
     * Get suggested next appointment date for an item.
     */
    public function getSuggestedNextDate(TreatmentPlanItem $item): Carbon
    {
        $lastCompleted = $item->last_completed_appointment;

        if (!$lastCompleted || !$item->session_interval_days) {
            return today();
        }

        $suggestedDate = $lastCompleted->date->addDays($item->session_interval_days);

        // If suggested date is in the past, return today
        return $suggestedDate->isFuture() ? $suggestedDate : today();
    }

    /**
     * Duplicate a treatment plan for another patient or as a template.
     */
    public function duplicatePlan(
        TreatmentPlan $sourcePlan,
        ?Patient $targetPatient = null,
        array $overrides = []
    ): TreatmentPlan {
        return DB::transaction(function () use ($sourcePlan, $targetPatient, $overrides) {
            $patient = $targetPatient ?? $sourcePlan->patient;

            // Create new plan
            $newPlan = TreatmentPlan::create([
                'tenant_id' => $patient->tenant_id,
                'patient_id' => $patient->id,
                'branch_id' => $overrides['branch_id'] ?? $sourcePlan->branch_id,
                'name' => $sourcePlan->name,
                'description' => $sourcePlan->description,
                'source' => TreatmentPlan::SOURCE_MANUAL,
                'recommended_package_id' => $sourcePlan->recommended_package_id,
                'created_by_user_id' => auth()->id(),
                'start_date' => $overrides['start_date'] ?? null,
                'target_end_date' => $overrides['target_end_date'] ?? null,
                'notes' => $overrides['notes'] ?? $sourcePlan->notes,
                ...$overrides,
            ]);

            // Duplicate items
            foreach ($sourcePlan->items as $item) {
                TreatmentPlanItem::create([
                    'tenant_id' => $patient->tenant_id,
                    'treatment_plan_id' => $newPlan->id,
                    'service_id' => $item->service_id,
                    'recommended_sessions' => $item->recommended_sessions,
                    'session_interval_days' => $item->session_interval_days,
                    'preferred_practitioner_id' => $item->preferred_practitioner_id,
                    'preferred_day_of_week' => $item->preferred_day_of_week,
                    'preferred_time_slot' => $item->preferred_time_slot,
                    'sort_order' => $item->sort_order,
                    'notes' => $item->notes,
                ]);
            }

            return $newPlan->load('items');
        });
    }

    /**
     * Get overdue treatment plans (active plans past target end date).
     */
    public function getOverduePlans(?string $tenantId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = TreatmentPlan::active()
            ->whereNotNull('target_end_date')
            ->where('target_end_date', '<', today());

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->with(['patient', 'branch'])
            ->orderBy('target_end_date')
            ->get();
    }

    /**
     * Check for plans that should be auto-completed.
     */
    public function checkAndCompleteAllSessions(): int
    {
        $completed = 0;

        TreatmentPlan::active()
            ->with('items')
            ->chunk(100, function ($plans) use (&$completed) {
                foreach ($plans as $plan) {
                    if ($plan->checkAndMarkComplete()) {
                        $completed++;
                    }
                }
            });

        return $completed;
    }
}
