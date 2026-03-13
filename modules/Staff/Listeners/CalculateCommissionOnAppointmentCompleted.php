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

        // Skip if no practitioner assigned
        if (!$appointment->practitioner_id) {
            return;
        }

        // Get staff profile for the practitioner
        $staffProfile = StaffProfile::where('user_id', $appointment->practitioner_id)
            ->where('is_active', true)
            ->first();

        if (!$staffProfile) {
            return;
        }

        // Get the revenue from the appointment
        // This could be from invoice line or calculated price
        $revenueMinor = $this->getAppointmentRevenue($appointment);

        if ($revenueMinor <= 0) {
            return;
        }

        // Get service category ID if available
        $categoryId = $appointment->service?->category_id ?? null;

        // Check if this is a new patient
        $isNewPatient = $this->isNewPatient($appointment);

        // Get commission plan
        $commissionPlan = $staffProfile->commissionPlan;

        // Calculate commission
        $commissionAmount = 0;
        $commissionType = null;
        $commissionRate = null;

        // Check for new patient commission first
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

        // Create commission record
        StaffCommissionRecord::create([
            'tenant_id' => $appointment->tenant_id,
            'staff_profile_id' => $staffProfile->id,
            'appointment_id' => $appointment->id,
            'amount_minor' => $commissionAmount,
            'revenue_minor' => $revenueMinor,
            'commission_type' => $commissionType,
            'commission_rate' => $commissionRate,
            'status' => StaffCommissionRecord::STATUS_PENDING,
            'notes' => $isNewPatient && $commissionPlan?->hasNewPatientCommission() ? 'New patient commission' : null,
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
