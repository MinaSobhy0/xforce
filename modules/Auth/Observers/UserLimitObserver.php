<?php

namespace Modules\Auth\Observers;

use Modules\Auth\Models\User;
use Modules\Auth\Notifications\UserLimitExceededNotification;
use Modules\Auth\Notifications\UserLimitExceededAdminNotification;
use Modules\Core\Models\Tenant;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class UserLimitObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $this->checkUserLimit();
    }

    /**
     * Handle the User "restored" event (undeleted).
     */
    public function restored(User $user): void
    {
        $this->checkUserLimit();
    }

    /**
     * Check if tenant has exceeded user limit and handle accordingly.
     */
    protected function checkUserLimit(): void
    {
        $tenant = current_tenant();
        if (!$tenant) {
            return;
        }

        $effectiveLimit = $tenant->getEffectiveLimit('users');

        // If unlimited, nothing to check
        if ($effectiveLimit === null) {
            return;
        }

        $currentUserCount = User::count();

        // If not over limit, clear any overage tracking
        if ($currentUserCount <= $effectiveLimit) {
            if ($tenant->users_overage_at) {
                $tenant->update([
                    'users_overage_at' => null,
                    'users_overage_notified' => false,
                ]);
            }
            return;
        }

        // Over limit - check if this is first time
        if (!$tenant->users_overage_at) {
            $tenant->update([
                'users_overage_at' => now(),
                'users_overage_notified' => false,
            ]);

            $this->notifyTenantAdmins($tenant, $currentUserCount, $effectiveLimit);
            $this->notifyPlatformAdmin($tenant, $currentUserCount, $effectiveLimit);

            Log::warning('Tenant exceeded user limit', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'current_users' => $currentUserCount,
                'limit' => $effectiveLimit,
                'grace_period_ends' => now()->addDays(14)->toDateString(),
            ]);
        }
    }

    /**
     * Notify tenant admins about the user limit exceeded.
     */
    protected function notifyTenantAdmins(Tenant $tenant, int $currentCount, int $limit): void
    {
        try {
            // Get all users with admin role in this tenant
            $admins = User::role('admin')->get();

            foreach ($admins as $admin) {
                $admin->notify(new UserLimitExceededNotification(
                    $currentCount,
                    $limit,
                    $tenant->users_overage_at->addDays(14)
                ));
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify tenant admins about user limit', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify platform admin about tenant exceeding user limit.
     */
    protected function notifyPlatformAdmin(Tenant $tenant, int $currentCount, int $limit): void
    {
        try {
            $platformAdminEmail = config('xlinic.platform_admin_email', config('mail.from.address'));

            if (!$platformAdminEmail) {
                Log::warning('No platform admin email configured for user limit notifications');
                return;
            }

            Mail::to($platformAdminEmail)->send(
                new \Modules\Auth\Mail\UserLimitExceededMail($tenant, $currentCount, $limit)
            );

            // Mark as notified
            $tenant->update(['users_overage_notified' => true]);
        } catch (\Exception $e) {
            Log::error('Failed to notify platform admin about user limit', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
