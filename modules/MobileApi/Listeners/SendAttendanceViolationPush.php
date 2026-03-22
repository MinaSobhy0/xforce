<?php

namespace Modules\MobileApi\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Attendance\Models\AttendanceViolation;
use Modules\MobileApi\Services\PushNotificationService;

class SendAttendanceViolationPush implements ShouldQueue
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
        public AttendanceViolation $violation
    ) {}

    /**
     * Handle the attendance violation created event.
     */
    public function handle(): void
    {
        $violation = $this->violation;
        // Ensure we have the staff profile and user
        $violation->loadMissing(['staffProfile.user']);

        $staffProfile = $violation->staffProfile;
        $user = $staffProfile?->user;

        if (! $user) {
            Log::warning('Cannot send push notification: user not found for violation', [
                'violation_id' => $violation->id,
                'staff_profile_id' => $violation->staff_profile_id,
            ]);

            return;
        }

        $pushService = app(PushNotificationService::class);

        $pushService->sendAttendanceViolationNotification(
            user: $user,
            violation: $violation,
            violationType: $violation->type_label,
            violationDate: $violation->violation_date->format('M d, Y')
        );
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send attendance violation push notification', [
            'violation_id' => $this->violation->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
