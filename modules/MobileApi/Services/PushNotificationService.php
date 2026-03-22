<?php

namespace Modules\MobileApi\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Modules\Auth\Models\User;
use Modules\MobileApi\Models\DeviceToken;
use Modules\MobileApi\Models\NotificationPreference;
use Modules\MobileApi\Models\PushNotification;

class PushNotificationService
{
    protected ?Messaging $messaging = null;

    /**
     * Get Firebase messaging instance lazily.
     */
    protected function getMessaging(): ?Messaging
    {
        if ($this->messaging === null) {
            try {
                $this->messaging = Firebase::messaging();
            } catch (\Exception $e) {
                Log::error('Failed to initialize Firebase messaging', [
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        }

        return $this->messaging;
    }

    /**
     * Send push notification to a user.
     */
    public function sendToUser(
        User $user,
        string $type,
        string $title,
        string $body,
        array $data = [],
        ?Model $reference = null,
        bool $checkPreferences = true
    ): ?PushNotification {
        // Check user preferences
        if ($checkPreferences && ! $this->shouldSendPush($user, $type)) {
            Log::debug('Push notification skipped due to user preferences', [
                'user_id' => $user->id,
                'type' => $type,
            ]);

            return null;
        }

        // Get active device tokens
        $tokens = DeviceToken::where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('fcm_token')
            ->toArray();

        if (empty($tokens)) {
            Log::debug('No active device tokens for user', [
                'user_id' => $user->id,
            ]);

            return null;
        }

        // Create notification record
        $notification = PushNotification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->id,
            'status' => PushNotification::STATUS_PENDING,
        ]);

        // Send via Firebase
        $this->dispatchToFcm($notification, $tokens, $user);

        return $notification;
    }

    /**
     * Send notification to multiple users.
     */
    public function sendToUsers(
        Collection $users,
        string $type,
        string $title,
        string $body,
        array $data = [],
        bool $checkPreferences = true
    ): array {
        $notifications = [];

        foreach ($users as $user) {
            $notification = $this->sendToUser(
                $user,
                $type,
                $title,
                $body,
                $data,
                null,
                $checkPreferences
            );

            if ($notification) {
                $notifications[] = $notification;
            }
        }

        return $notifications;
    }

    /**
     * Dispatch notification to Firebase Cloud Messaging.
     */
    protected function dispatchToFcm(PushNotification $notification, array $tokens, User $user): void
    {
        $messaging = $this->getMessaging();

        if (! $messaging) {
            $notification->markAsFailed('Firebase messaging not configured');

            return;
        }

        try {
            // Get tenant context for data payload (safely handle missing binding)
            $tenant = null;
            try {
                $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
            } catch (\Exception $e) {
                // Tenant context not available (e.g., CLI environment)
            }

            $payload = array_merge($notification->data ?? [], [
                'notification_id' => (string) $notification->id,
                'type' => $notification->type,
                'click_action' => $this->getClickAction($notification->type),
                'tenant_slug' => $tenant?->slug ?? '',
                'tenant_name' => $tenant?->name ?? '',
            ]);

            // Build the FCM message
            $message = CloudMessage::new()
                ->withNotification(Notification::create(
                    $notification->title,
                    $notification->body
                ))
                ->withData($this->normalizePayload($payload));

            // Send to all tokens
            if (count($tokens) === 1) {
                $singleMessage = CloudMessage::new()
                    ->withNotification(Notification::create(
                        $notification->title,
                        $notification->body
                    ))
                    ->withData($this->normalizePayload($payload))
                    ->withToken($tokens[0]);
                $response = $messaging->send($singleMessage);
                $notification->markAsSent(is_array($response) ? ($response['name'] ?? null) : $response);
            } else {
                $report = $messaging->sendMulticast($message, $tokens);

                // Log results
                Log::info('Push notification multicast sent', [
                    'notification_id' => $notification->id,
                    'user_id' => $user->id,
                    'successes' => $report->successes()->count(),
                    'failures' => $report->failures()->count(),
                ]);

                // Mark notification status based on results
                $successes = $report->successes()->getItems();
                $failures = $report->failures()->getItems();

                if (count($successes) > 0) {
                    $result = $successes[0]->result();
                    $notification->markAsSent(is_array($result) ? ($result['name'] ?? null) : null);
                } elseif (count($failures) > 0) {
                    $notification->markAsFailed(
                        $failures[0]->error()?->getMessage() ?? 'All tokens failed'
                    );
                }

                // Handle invalid tokens
                $this->handleInvalidTokens($report, $tokens, $user);
            }
        } catch (MessagingException $e) {
            $notification->markAsFailed($e->getMessage());

            Log::error('Push notification failed', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());

            Log::error('Push notification unexpected error', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if push notification should be sent based on user preferences.
     */
    protected function shouldSendPush(User $user, string $type): bool
    {
        return NotificationPreference::isPushEnabledFor($user->id, $type);
    }

    /**
     * Get the click action for deep linking in the mobile app.
     */
    protected function getClickAction(string $type): string
    {
        return match ($type) {
            PushNotification::TYPE_ATTENDANCE_VIOLATION,
            PushNotification::TYPE_VIOLATION_STATUS_CHANGED => 'OPEN_VIOLATIONS',
            PushNotification::TYPE_CHECK_IN_REMINDER => 'OPEN_ATTENDANCE',
            PushNotification::TYPE_TIME_OFF_APPROVED,
            PushNotification::TYPE_TIME_OFF_REJECTED => 'OPEN_TIME_OFF',
            PushNotification::TYPE_APPOINTMENT_REMINDER,
            PushNotification::TYPE_APPOINTMENT_CANCELLED => 'OPEN_APPOINTMENTS',
            PushNotification::TYPE_PAYSLIP_READY => 'OPEN_PAYSLIP',
            PushNotification::TYPE_COMMISSION_EARNED => 'OPEN_COMMISSION',
            PushNotification::TYPE_SCHEDULE_CHANGED => 'OPEN_SCHEDULE',
            default => 'OPEN_APP',
        };
    }

    /**
     * Normalize payload values to strings (FCM requires string values).
     */
    protected function normalizePayload(array $payload): array
    {
        $normalized = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $normalized[$key] = json_encode($value);
            } elseif (is_bool($value)) {
                $normalized[$key] = $value ? 'true' : 'false';
            } elseif ($value === null) {
                $normalized[$key] = '';
            } else {
                $normalized[$key] = (string) $value;
            }
        }

        return $normalized;
    }

    /**
     * Handle invalid tokens from FCM response.
     */
    protected function handleInvalidTokens(object $report, array $tokens, User $user): void
    {
        // Use the built-in methods to get invalid/unknown tokens
        $invalidTokens = array_merge(
            $report->invalidTokens(),
            $report->unknownTokens()
        );

        if (empty($invalidTokens)) {
            return;
        }

        // Deactivate all invalid tokens
        DeviceToken::where('user_id', $user->id)
            ->whereIn('fcm_token', $invalidTokens)
            ->update(['is_active' => false]);

        if (count($invalidTokens) > 0) {
            Log::info('Deactivated invalid FCM tokens', [
                'user_id' => $user->id,
                'count' => count($invalidTokens),
            ]);
        }
    }

    /**
     * Get unread notification count for user.
     */
    public function getUnreadCount(int $userId): int
    {
        return PushNotification::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Get notifications for user.
     */
    public function getNotifications(int $userId, int $limit = 50, int $offset = 0): Collection
    {
        return PushNotification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();
    }

    /**
     * Mark all notifications as read for user.
     */
    public function markAllAsRead(int $userId): int
    {
        return PushNotification::where('user_id', $userId)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'status' => PushNotification::STATUS_READ,
            ]);
    }

    /**
     * Send attendance violation notification.
     */
    public function sendAttendanceViolationNotification(
        User $user,
        Model $violation,
        string $violationType,
        string $violationDate
    ): ?PushNotification {
        return $this->sendToUser(
            user: $user,
            type: PushNotification::TYPE_ATTENDANCE_VIOLATION,
            title: __('mobile_api::notifications.violation.title'),
            body: __('mobile_api::notifications.violation.body', [
                'type' => $violationType,
                'date' => $violationDate,
            ]),
            data: [
                'violation_id' => $violation->id,
                'violation_type' => $violation->violation_type ?? null,
            ],
            reference: $violation
        );
    }

    /**
     * Send time-off status changed notification.
     */
    public function sendTimeOffStatusNotification(
        User $user,
        Model $timeOffRequest,
        string $status,
        ?string $reason = null
    ): ?PushNotification {
        $type = match ($status) {
            'approved' => PushNotification::TYPE_TIME_OFF_APPROVED,
            'rejected' => PushNotification::TYPE_TIME_OFF_REJECTED,
            default => null,
        };

        if (! $type) {
            return null;
        }

        $body = $status === 'approved'
            ? __('mobile_api::notifications.time_off.approved_body', [
                'start_date' => $timeOffRequest->start_date->format('M d'),
                'end_date' => $timeOffRequest->end_date->format('M d, Y'),
            ])
            : __('mobile_api::notifications.time_off.rejected_body', [
                'reason' => $reason ?? __('mobile_api::notifications.time_off.no_reason'),
            ]);

        return $this->sendToUser(
            user: $user,
            type: $type,
            title: __("mobile_api::notifications.time_off.{$status}_title"),
            body: $body,
            data: [
                'request_id' => $timeOffRequest->id,
                'status' => $status,
            ],
            reference: $timeOffRequest
        );
    }

    /**
     * Send appointment reminder notification.
     */
    public function sendAppointmentReminderNotification(
        User $user,
        Model $appointment,
        string $patientName,
        string $time
    ): ?PushNotification {
        return $this->sendToUser(
            user: $user,
            type: PushNotification::TYPE_APPOINTMENT_REMINDER,
            title: __('mobile_api::notifications.appointment.reminder_title'),
            body: __('mobile_api::notifications.appointment.reminder_body', [
                'patient_name' => $patientName,
                'time' => $time,
            ]),
            data: [
                'appointment_id' => $appointment->id,
            ],
            reference: $appointment
        );
    }

    /**
     * Send payslip ready notification.
     */
    public function sendPayslipReadyNotification(
        User $user,
        Model $payroll,
        string $period
    ): ?PushNotification {
        return $this->sendToUser(
            user: $user,
            type: PushNotification::TYPE_PAYSLIP_READY,
            title: __('mobile_api::notifications.payslip.ready_title'),
            body: __('mobile_api::notifications.payslip.ready_body', [
                'period' => $period,
            ]),
            data: [
                'payroll_id' => $payroll->id,
            ],
            reference: $payroll
        );
    }
}
