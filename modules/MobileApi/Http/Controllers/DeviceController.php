<?php

namespace Modules\MobileApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\MobileApi\Models\DeviceToken;
use Modules\MobileApi\Models\NotificationPreference;
use Modules\MobileApi\Models\PushNotification;
use Modules\MobileApi\Services\PushNotificationService;

class DeviceController extends BaseApiController
{
    public function __construct(
        protected PushNotificationService $pushService
    ) {}

    /**
     * Register device for push notifications.
     * POST /api/v2/devices
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|string|max:255',
            'fcm_token' => 'required|string',
            'platform' => 'required|in:ios,android',
            'app_version' => 'nullable|string|max:20',
        ]);

        $device = DeviceToken::updateOrCreate(
            [
                'user_id' => $this->user()->id,
                'device_id' => $validated['device_id'],
            ],
            [
                'fcm_token' => $validated['fcm_token'],
                'platform' => $validated['platform'],
                'app_version' => $validated['app_version'] ?? null,
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );

        return $this->success([
            'device_id' => $device->device_id,
            'registered' => true,
        ], __('mobile_api::notifications.device.registered'));
    }

    /**
     * Unregister device.
     * DELETE /api/v2/devices/{deviceId}
     */
    public function unregister(string $deviceId): JsonResponse
    {
        $deleted = DeviceToken::where('user_id', $this->user()->id)
            ->where('device_id', $deviceId)
            ->update(['is_active' => false]);

        if (! $deleted) {
            return $this->notFound(__('mobile_api::notifications.device.not_found'));
        }

        return $this->success([
            'device_id' => $deviceId,
            'unregistered' => true,
        ], __('mobile_api::notifications.device.unregistered'));
    }

    /**
     * List user's notifications.
     * GET /api/v2/notifications
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage();
        $page = $request->input('page', 1);
        $offset = ($page - 1) * $perPage;

        $notifications = $this->pushService->getNotifications(
            $this->user()->id,
            $perPage,
            $offset
        );

        $total = PushNotification::where('user_id', $this->user()->id)->count();
        $unreadCount = $this->pushService->getUnreadCount($this->user()->id);

        return $this->success([
            'notifications' => $notifications->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'type_label' => $n->type_label,
                'title' => $n->title,
                'body' => $n->body,
                'data' => $n->data,
                'is_read' => $n->isRead(),
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at->toIso8601String(),
            ]),
            'unread_count' => $unreadCount,
            'meta' => [
                'current_page' => (int) $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * Get unread notification count.
     * GET /api/v2/notifications/unread-count
     */
    public function unreadCount(): JsonResponse
    {
        $count = $this->pushService->getUnreadCount($this->user()->id);

        return $this->success([
            'unread_count' => $count,
        ]);
    }

    /**
     * Mark notification as read.
     * POST /api/v2/notifications/{id}/read
     */
    public function markRead(int $id): JsonResponse
    {
        $notification = PushNotification::where('user_id', $this->user()->id)
            ->where('id', $id)
            ->first();

        if (! $notification) {
            return $this->notFound(__('mobile_api::notifications.not_found'));
        }

        $notification->markAsRead();

        return $this->success([
            'id' => $notification->id,
            'is_read' => true,
            'read_at' => $notification->read_at->toIso8601String(),
        ], __('mobile_api::notifications.marked_read'));
    }

    /**
     * Mark all notifications as read.
     * POST /api/v2/notifications/read-all
     */
    public function markAllRead(): JsonResponse
    {
        $count = $this->pushService->markAllAsRead($this->user()->id);

        return $this->success([
            'marked_count' => $count,
        ], __('mobile_api::notifications.all_marked_read'));
    }

    /**
     * Get notification preferences.
     * GET /api/v2/notifications/preferences
     */
    public function preferences(): JsonResponse
    {
        $preferences = NotificationPreference::getAllForUser($this->user()->id);

        return $this->success([
            'preferences' => array_values($preferences),
        ]);
    }

    /**
     * Update notification preferences.
     * PUT /api/v2/notifications/preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences' => 'required|array',
            'preferences.*.type' => 'required|string',
            'preferences.*.push_enabled' => 'boolean',
            'preferences.*.email_enabled' => 'boolean',
            'preferences.*.sms_enabled' => 'boolean',
        ]);

        NotificationPreference::updateBulk(
            $this->user()->id,
            $validated['preferences']
        );

        $preferences = NotificationPreference::getAllForUser($this->user()->id);

        return $this->success([
            'preferences' => array_values($preferences),
        ], __('mobile_api::notifications.preferences_updated'));
    }

    /**
     * Get registered devices for the current user.
     * GET /api/v2/devices
     */
    public function listDevices(): JsonResponse
    {
        $devices = DeviceToken::where('user_id', $this->user()->id)
            ->where('is_active', true)
            ->orderBy('last_used_at', 'desc')
            ->get();

        return $this->success([
            'devices' => $devices->map(fn ($d) => [
                'device_id' => $d->device_id,
                'platform' => $d->platform,
                'platform_label' => $d->platform_label,
                'app_version' => $d->app_version,
                'last_used_at' => $d->last_used_at?->toIso8601String(),
            ]),
        ]);
    }
}
