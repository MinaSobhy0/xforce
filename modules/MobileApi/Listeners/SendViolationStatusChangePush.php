<?php

namespace Modules\MobileApi\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Modules\Attendance\Models\AttendanceViolation;
use Modules\MobileApi\Models\PushNotification;
use Modules\MobileApi\Services\PushNotificationService;

class SendViolationStatusChangePush implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The queue name for this job.
     */
    public string $queue = 'notifications';

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    public function __construct(
        public AttendanceViolation $violation,
        public string $newStatus,
        public string $oldStatus
    ) {}

    /**
     * Handle the violation status change event.
     */
    public function handle(): void
    {
        // Ensure we have the staff profile and user
        $this->violation->loadMissing(['staffProfile.user']);

        $staffProfile = $this->violation->staffProfile;
        $user = $staffProfile?->user;

        if (! $user) {
            Log::warning('Cannot send push notification: user not found for violation', [
                'violation_id' => $this->violation->id,
                'staff_profile_id' => $this->violation->staff_profile_id,
            ]);

            return;
        }

        $pushService = app(PushNotificationService::class);

        $statusLabel = match ($this->newStatus) {
            AttendanceViolation::STATUS_APPROVED => __('mobile_api::notifications.violation_status.approved'),
            AttendanceViolation::STATUS_WAIVED => __('mobile_api::notifications.violation_status.waived'),
            default => $this->newStatus,
        };

        $body = match ($this->newStatus) {
            AttendanceViolation::STATUS_WAIVED => __('mobile_api::notifications.violation_status.waived_body', [
                'type' => $this->violation->type_label,
                'date' => $this->violation->violation_date->format('M d'),
            ]),
            default => __('mobile_api::notifications.violation_status.body', [
                'type' => $this->violation->type_label,
                'status' => $statusLabel,
            ]),
        };

        $pushService->sendToUser(
            user: $user,
            type: PushNotification::TYPE_VIOLATION_STATUS_CHANGED,
            title: __('mobile_api::notifications.violation_status.title'),
            body: $body,
            data: [
                'violation_id' => $this->violation->id,
                'new_status' => $this->newStatus,
                'old_status' => $this->oldStatus,
            ],
            reference: $this->violation
        );
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send violation status change push notification', [
            'violation_id' => $this->violation->id,
            'new_status' => $this->newStatus,
            'error' => $exception->getMessage(),
        ]);
    }
}
