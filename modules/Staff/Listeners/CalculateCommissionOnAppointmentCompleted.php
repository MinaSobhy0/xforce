<?php

namespace Modules\Staff\Listeners;

use Modules\Booking\Events\AppointmentCompleted;
use Modules\Booking\Models\Appointment;
use Modules\Staff\Models\StaffProfile;
use Modules\Staff\Models\StaffCommissionRecord;

class CalculateCommissionOnAppointmentCompleted
{
    /**
     * Handle the event.
     */
    public function handle(AppointmentCompleted $event): void
    {
        $appointment = $event->appointment;

        // Skip if commission already recorded for this appointment (prevent duplicates on reschedule)
        if ($this->hasExistingCommission($appointment)) {
            return;
        }

        // Get the revenue from the appointment
        $revenueMinor = $this->getAppointmentRevenue($appointment);

        if ($revenueMinor <= 0) {
            return;
        }

        // Determine who gets the commission:
        // - Upsell during treatment session → Practitioner
        // - Regular booking → Sales person who created the booking/patient
        if ($appointment->is_upsell) {
            $this->createPractitionerCommission($appointment, $revenueMinor);
        } else {
            $this->createSalesCommission($appointment, $revenueMinor);
        }
    }

    /**
     * Check if commission was already recorded for this appointment.
     */
    protected function hasExistingCommission(Appointment $appointment): bool
    {
        return StaffCommissionRecord::where('appointment_id', $appointment->id)
            ->whereNotIn('status', [StaffCommissionRecord::STATUS_CANCELLED])
            ->exists();
    }

    /**
     * Create commission for the practitioner (upsell scenario).
     */
    protected function createPractitionerCommission(Appointment $appointment, int $revenueMinor): void
    {
        if (!$appointment->practitioner_id) {
            return;
        }

        $staffProfile = StaffProfile::where('user_id', $appointment->practitioner_id)
            ->where('is_active', true)
            ->first();

        if (!$staffProfile || !$staffProfile->commission_plan_id) {
            return;
        }

        $this->calculateAndCreateCommission($staffProfile, $appointment, $revenueMinor, 'Upsell commission');
    }

    /**
     * Create commission for the sales person (regular booking scenario).
     */
    protected function createSalesCommission(Appointment $appointment, int $revenueMinor): void
    {
        // Priority:
        // 1. Appointment creator (who booked)
        // 2. Patient creator (who registered the patient)
        $salesUserId = $appointment->created_by_user_id
            ?? $appointment->patient?->created_by_user_id;

        if (!$salesUserId) {
            return;
        }

        $staffProfile = StaffProfile::where('user_id', $salesUserId)
            ->where('is_active', true)
            ->first();

        if (!$staffProfile || !$staffProfile->commission_plan_id) {
            return;
        }

        // Check if this is a new patient
        $isNewPatient = $this->isNewPatient($appointment);
        $notes = $isNewPatient ? 'New patient commission' : 'Sales commission';

        $this->calculateAndCreateCommission($staffProfile, $appointment, $revenueMinor, $notes, $isNewPatient);
    }

    /**
     * Calculate and create commission record.
     */
    protected function calculateAndCreateCommission(
        StaffProfile $staffProfile,
        Appointment $appointment,
        int $revenueMinor,
        ?string $notes = null,
        bool $isNewPatient = false
    ): void {
        $categoryId = $appointment->service?->category_id ?? null;
        $commissionPlan = $staffProfile->commissionPlan;

        $commissionAmount = 0;
        $commissionType = null;
        $commissionRate = null;

        // Check for new patient commission first (only for sales, not upsells)
        if ($isNewPatient && $commissionPlan && $commissionPlan->hasNewPatientCommission()) {
            $commissionAmount = $commissionPlan->calculateNewPatientCommission($revenueMinor);
            $commissionType = 'new_patient_' . ($commissionPlan->new_patient_commission_type ?? 'percentage');
            $commissionRate = $commissionPlan->new_patient_percentage;
        }

        // If no new patient commission or amount is 0, use regular commission
        if ($commissionAmount <= 0) {
            $commissionAmount = $staffProfile->calculateCommission(
                $revenueMinor,
                $appointment->service_id,
                $categoryId
            );
            $commissionType = $commissionPlan?->commission_type ?? $staffProfile->commission_type;
            $commissionRate = $commissionPlan?->default_percentage ?? $staffProfile->commission_percentage;
        }

        if ($commissionAmount <= 0) {
            return;
        }

        StaffCommissionRecord::create([
            'tenant_id' => $appointment->tenant_id,
            'staff_profile_id' => $staffProfile->id,
            'appointment_id' => $appointment->id,
            'amount_minor' => $commissionAmount,
            'revenue_minor' => $revenueMinor,
            'commission_type' => $commissionType,
            'commission_rate' => $commissionRate,
            'status' => StaffCommissionRecord::STATUS_PENDING,
            'notes' => $notes,
        ]);
    }

    /**
     * Check if this is the patient's first completed appointment.
     */
    protected function isNewPatient(Appointment $appointment): bool
    {
        if (!$appointment->patient_id) {
            return false;
        }

        // Check if patient has any other completed appointments before this one
        $previousCompletedCount = Appointment::where('patient_id', $appointment->patient_id)
            ->where('id', '!=', $appointment->id)
            ->where('status', Appointment::STATUS_COMPLETED)
            ->count();

        return $previousCompletedCount === 0;
    }

    /**
     * Get revenue from the appointment.
     */
    protected function getAppointmentRevenue($appointment): int
    {
        // Try to get from related invoice line first
        if ($appointment->invoiceLine) {
            return $appointment->invoiceLine->total_minor ?? 0;
        }

        // Use appointment's own price_minor (set during booking)
        if (!empty($appointment->price_minor)) {
            return $appointment->price_minor;
        }

        // Use final price if set on appointment
        if (!empty($appointment->final_price_minor)) {
            return $appointment->final_price_minor;
        }

        // Fall back to service base price
        if ($appointment->service) {
            return $appointment->service->base_price_minor ?? $appointment->service->price_minor ?? 0;
        }

        return 0;
    }
}
