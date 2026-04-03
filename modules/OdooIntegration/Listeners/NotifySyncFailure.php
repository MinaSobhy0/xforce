<?php

namespace Modules\OdooIntegration\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\OdooIntegration\Events\SyncFailed;
use Modules\Auth\Models\User;

class NotifySyncFailure
{
    /**
     * Handle the event.
     */
    public function handle(SyncFailed $event): void
    {
        if (!config('odoo-integration.notify_on_failure', true)) {
            return;
        }

        $log = $event->syncLog;
        $exception = $event->exception;

        // Get admin users to notify
        $admins = $this->getAdminUsers($log->tenant_id);

        if ($admins->isEmpty()) {
            return;
        }

        // Send notification (can be customized to use different channels)
        foreach ($admins as $admin) {
            // Using Laravel's notification system
            // You can create a custom notification class
            // For now, we'll just log that we would notify
            // $admin->notify(new OdooSyncFailedNotification($log, $exception));
        }
    }

    /**
     * Get admin users to notify.
     */
    protected function getAdminUsers(?int $tenantId): \Illuminate\Support\Collection
    {
        return User::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
            ->get();
    }
}
