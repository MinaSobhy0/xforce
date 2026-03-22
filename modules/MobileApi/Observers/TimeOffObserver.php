<?php

namespace Modules\MobileApi\Observers;

use Modules\Booking\Models\PractitionerTimeOff;
use Modules\MobileApi\Listeners\SendTimeOffStatusPush;

class TimeOffObserver
{
    /**
     * Handle the PractitionerTimeOff "updated" event.
     */
    public function updated(PractitionerTimeOff $timeOff): void
    {
        // Check if status changed
        if (! $timeOff->isDirty('status')) {
            return;
        }

        $newStatus = $timeOff->status;

        // Only send notifications for approved or rejected
        if (! in_array($newStatus, [
            PractitionerTimeOff::STATUS_APPROVED,
            PractitionerTimeOff::STATUS_REJECTED,
        ])) {
            return;
        }

        // Get the rejection reason from notes if rejected
        $reason = $newStatus === PractitionerTimeOff::STATUS_REJECTED
            ? $timeOff->notes
            : null;

        // Dispatch notification job
        dispatch(new SendTimeOffStatusPush($timeOff, $newStatus, $reason));
    }
}
