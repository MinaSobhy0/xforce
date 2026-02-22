<?php

namespace Modules\TreatmentPlans\Observers;

use Modules\Booking\Models\Appointment;
use Modules\TreatmentPlans\Services\TreatmentPlanService;

class AppointmentObserver
{
    protected TreatmentPlanService $treatmentPlanService;

    public function __construct(TreatmentPlanService $treatmentPlanService)
    {
        $this->treatmentPlanService = $treatmentPlanService;
    }

    /**
     * Handle the Appointment "updated" event.
     */
    public function updated(Appointment $appointment): void
    {
        // Check if status changed
        if (!$appointment->wasChanged('status')) {
            return;
        }

        $oldStatus = $appointment->getOriginal('status');
        $newStatus = $appointment->status;

        // Handle completion
        if ($newStatus === Appointment::STATUS_COMPLETED) {
            $this->treatmentPlanService->handleAppointmentCompleted($appointment);
            return;
        }

        // Handle cancellation
        if ($newStatus === Appointment::STATUS_CANCELLED) {
            $this->treatmentPlanService->handleAppointmentCancelled($appointment);
            return;
        }

        // Handle other status changes
        $this->treatmentPlanService->handleAppointmentStatusChanged($appointment);
    }

    /**
     * Handle the Appointment "deleted" event.
     */
    public function deleted(Appointment $appointment): void
    {
        // Treatment plan appointments are cascade deleted via foreign key
        // No additional action needed
    }
}
