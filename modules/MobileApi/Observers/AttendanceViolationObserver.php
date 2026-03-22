<?php

namespace Modules\MobileApi\Observers;

use Modules\Attendance\Models\AttendanceViolation;
use Modules\MobileApi\Listeners\SendAttendanceViolationPush;

class AttendanceViolationObserver
{
    /**
     * Handle the AttendanceViolation "created" event.
     */
    public function created(AttendanceViolation $violation): void
    {
        // Dispatch notification job
        dispatch(new SendAttendanceViolationPush($violation));
    }

    /**
     * Handle the AttendanceViolation "updated" event.
     * Send notification when status changes to waived or approved.
     */
    public function updated(AttendanceViolation $violation): void
    {
        // Check if status changed
        if (! $violation->isDirty('status')) {
            return;
        }

        $newStatus = $violation->status;
        $oldStatus = $violation->getOriginal('status');

        // Only notify for specific status changes
        if (! in_array($newStatus, [
            AttendanceViolation::STATUS_APPROVED,
            AttendanceViolation::STATUS_WAIVED,
        ])) {
            return;
        }

        // Dispatch status change notification
        dispatch(new \Modules\MobileApi\Listeners\SendViolationStatusChangePush(
            $violation,
            $newStatus,
            $oldStatus
        ));
    }
}
