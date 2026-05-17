<?php

namespace Modules\MobileApi\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Booking\Models\PractitionerTimeOff;
use Modules\MobileApi\Services\PushNotificationService;
use Modules\MobileApi\Support\ApproverResolver;

/**
 * When a staff member submits a new leave request, push a "needs review"
 * notification to the configured time-off approver. Falls back to anyone
 * holding the `practitioner_time_off.approve_any` override permission so
 * requests don't sit unseen when no per-staff approver is set.
 */
class SendTimeOffRequestedToApproverPush implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    public string $queue = 'notifications';

    public int $tries = 3;

    public function __construct(
        public PractitionerTimeOff $timeOff
    ) {}

    public function handle(): void
    {
        $timeOff = $this->timeOff;
        $timeOff->loadMissing(['staffProfile.user']);

        $staffProfile = $timeOff->staffProfile;
        if (! $staffProfile) {
            Log::warning('Cannot send approver push: staff profile missing for time off', [
                'time_off_id' => $timeOff->id,
                'staff_profile_id' => $timeOff->staff_profile_id,
            ]);

            return;
        }

        $recipients = ApproverResolver::recipientsFor(
            $staffProfile,
            'time_off_approver_user_id',
            ApproverResolver::PERM_TIME_OFF_APPROVE_ANY,
        );

        if ($recipients->isEmpty()) {
            Log::info('No time-off approver configured for staff and no override holders', [
                'time_off_id' => $timeOff->id,
                'staff_profile_id' => $staffProfile->id,
            ]);

            return;
        }

        $pushService = app(PushNotificationService::class);

        $employeeName = $staffProfile->user?->name
            ?? trim(($staffProfile->user?->first_name ?? '').' '.($staffProfile->user?->last_name ?? ''))
            ?: "Staff #{$staffProfile->id}";

        foreach ($recipients as $approver) {
            $pushService->sendTimeOffRequestedToApproverNotification(
                approver: $approver,
                timeOffRequest: $timeOff,
                employeeName: $employeeName,
            );
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send time-off requested approver push', [
            'time_off_id' => $this->timeOff->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
