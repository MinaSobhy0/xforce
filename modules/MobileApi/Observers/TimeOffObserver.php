<?php

namespace Modules\MobileApi\Observers;

use Modules\Booking\Models\PractitionerTimeOff;
use Modules\MobileApi\Listeners\SendTimeOffRequestedToApproverPush;
use Modules\MobileApi\Listeners\SendTimeOffStatusPush;

class TimeOffObserver
{
    /**
     * Handle the PractitionerTimeOff "created" event.
     *
     * Push a "needs review" notification to the configured time-off approver
     * (or any override holder) so they don't miss the new request.
     */
    public function created(PractitionerTimeOff $timeOff): void
    {
        if ($timeOff->status !== PractitionerTimeOff::STATUS_PENDING) {
            return;
        }

        dispatch(new SendTimeOffRequestedToApproverPush($timeOff));
    }

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
