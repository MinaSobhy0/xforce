<?php

namespace Modules\MobileApi\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Attendance\Models\AttendanceViolation;
use Modules\MobileApi\Services\PushNotificationService;
use Modules\MobileApi\Support\ApproverResolver;

/**
 * Fan out a "violation needs review" push to the configured attendance
 * approver(s) of the offending staff. Falls back to anyone holding the
 * `attendance_violations.approve_any` override permission so the violation
 * doesn't sit unseen when no per-staff approver is set.
 *
 * The violator (the staff member who broke the rule) is NOT pushed here —
 * a separate listener / type handles employee-side notices if needed.
 */
class SendAttendanceViolationPush implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    public string $queue = 'notifications';

    public int $tries = 3;

    public function __construct(
        public AttendanceViolation $violation
    ) {}

    public function handle(): void
    {
        $violation = $this->violation;
        $violation->loadMissing(['staffProfile.user']);

        $staffProfile = $violation->staffProfile;
        if (! $staffProfile) {
            Log::warning('Cannot send approver push: staff profile missing', [
                'violation_id' => $violation->id,
                'staff_profile_id' => $violation->staff_profile_id,
            ]);

            return;
        }

        $recipients = ApproverResolver::recipientsFor(
            $staffProfile,
            'attendance_approver_user_id',
            ApproverResolver::PERM_VIOLATIONS_APPROVE_ANY,
        );

        if ($recipients->isEmpty()) {
            Log::info('No attendance approver configured for staff and no override holders', [
                'violation_id' => $violation->id,
                'staff_profile_id' => $staffProfile->id,
            ]);

            return;
        }

        $pushService = app(PushNotificationService::class);

        $employeeName = $staffProfile->user?->name
            ?? trim(($staffProfile->user?->first_name ?? '').' '.($staffProfile->user?->last_name ?? ''))
            ?: "Staff #{$staffProfile->id}";

        foreach ($recipients as $approver) {
            $pushService->sendAttendanceViolationPendingNotification(
                approver: $approver,
                violation: $violation,
                employeeName: $employeeName,
                violationType: $violation->type_label ?? ($violation->violation_type ?? 'violation'),
                violationDate: optional($violation->violation_date)->format('M d, Y') ?? '-',
            );
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send attendance violation approver push', [
            'violation_id' => $this->violation->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
