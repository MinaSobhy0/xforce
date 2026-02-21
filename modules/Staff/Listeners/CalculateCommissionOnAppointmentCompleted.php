<?php

namespace Modules\Staff\Listeners;

use Modules\Booking\Events\AppointmentCompleted;
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

        // Calculate commission
        $commissionAmount = $staffProfile->calculateCommission(
            $revenueMinor,
            $appointment->service_id
        );

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
            'commission_type' => $staffProfile->commission_type,
            'commission_rate' => $staffProfile->commission_percentage,
            'status' => StaffCommissionRecord::STATUS_PENDING,
        ]);
    }

    /**
     * Get revenue from the appointment.
     */
    protected function getAppointmentRevenue($appointment): int
    {
        // Try to get from related invoice line
        if ($appointment->invoiceLine) {
            return $appointment->invoiceLine->total_minor ?? 0;
        }

        // Fall back to service price
        if ($appointment->service) {
            return $appointment->service->price_minor ?? 0;
        }

        // Use final price if set on appointment
        if (isset($appointment->final_price_minor)) {
            return $appointment->final_price_minor;
        }

        return 0;
    }
}
