<?php

namespace Modules\MobileApi\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Booking\Models\PractitionerTimeOff;
use Modules\MobileApi\Services\PushNotificationService;

class SendTimeOffStatusPush implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    /**
     * The queue name for this job.
     */
    public string $queue = 'notifications';

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    public function __construct(
        public PractitionerTimeOff $timeOff,
        public string $newStatus,
        public ?string $reason = null
    ) {}

    /**
     * Handle the time off status changed event.
     */
    public function handle(): void
    {
        // Only send notifications for approved or rejected
        if (! in_array($this->newStatus, ['approved', 'rejected'])) {
            return;
        }

        // Ensure we have the user (via staff profile)
        $this->timeOff->loadMissing(['staffProfile.user']);
        $user = $this->timeOff->staffProfile?->user;

        if (! $user) {
            Log::warning('Cannot send push notification: user not found for time off request', [
                'time_off_id' => $this->timeOff->id,
                'staff_profile_id' => $this->timeOff->staff_profile_id,
            ]);

            return;
        }

        $pushService = app(PushNotificationService::class);

        $pushService->sendTimeOffStatusNotification(
            user: $user,
            timeOffRequest: $this->timeOff,
            status: $this->newStatus,
            reason: $this->reason
        );
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send time off status push notification', [
            'time_off_id' => $this->timeOff->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
