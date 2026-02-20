<?php

namespace Modules\Loyalty\Listeners;

use Modules\Loyalty\Services\LoyaltyService;
use Illuminate\Contracts\Queue\ShouldQueue;

class AwardPointsOnVisit implements ShouldQueue
{
    protected LoyaltyService $loyaltyService;

    public function __construct(LoyaltyService $loyaltyService)
    {
        $this->loyaltyService = $loyaltyService;
    }

    public function handle($event): void
    {
        $appointment = $event->appointment ?? $event;

        // Get the patient
        $patient = $appointment->patient;
        if (!$patient) {
            return;
        }

        // Award visit points
        $this->loyaltyService->awardVisitPoints($patient);
    }
}
