<?php

namespace Modules\Auth\Observers;

use Modules\Auth\Models\User;
use Modules\Auth\Models\UserStatus;
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
     * Handle the User "updating" event — block reactivation that
     * would exceed the seat cap. Counts of "billable seats" filter
     * to status=active (see User::countExistingRecords). If we
     * didn't gate this, a tenant at cap could deactivate one user,
     * create another, then reactivate the original — back-door
     * around the limit.
     */
    public function updating(User $user): void
    {
        if (! $user->isDirty('status')) {
            return;
        }

        $newStatus = $user->status;
        $oldStatus = $user->getOriginal('status');

        // Cast enum vs string defensively — getOriginal returns the
        // raw column value, the dirty attribute might be the enum
        // instance depending on where the change came from.
        $newValue = $newStatus instanceof UserStatus ? $newStatus->value : $newStatus;
        $oldValue = $oldStatus instanceof UserStatus ? $oldStatus->value : $oldStatus;

        if ($newValue !== UserStatus::ACTIVE->value || $oldValue === UserStatus::ACTIVE->value) {
            return;
        }

        $tenant = current_tenant();

        if (! $tenant) {
            return;
        }

        $effectiveLimit = $tenant->getEffectiveLimit('users');

        if ($effectiveLimit === null || $effectiveLimit <= 0) {
            return;
        }

        // Active count excludes the user being reactivated (its
        // current status is still the OLD value at this point in the
        // event). Adding +1 simulates the post-save state.
        $activeAfter = User::query()->where('status', UserStatus::ACTIVE)->count() + 1;

        if ($activeAfter <= $effectiveLimit) {
            return;
        }

        $message = __("Cannot reactivate user. You have reached the maximum of :max active users allowed for your plan (currently: :current).", [
            'max' => $effectiveLimit,
            'current' => $activeAfter - 1,
        ]);

        // Same UX shape as EnforcesTenantLimits — Filament toast +
        // Halt inside a panel, plain exception elsewhere — so
        // reactivation-over-cap fails the same way creation-over-cap
        // fails.
        if (! app()->runningInConsole() && function_exists('filament')) {
            try {
                if (filament()->getCurrentPanel() !== null) {
                    if (class_exists(\Filament\Notifications\Notification::class)) {
                        \Filament\Notifications\Notification::make()
                            ->title($message)
                            ->body(__('Upgrade your plan or deactivate another user to free a seat.'))
                            ->danger()
                            ->persistent()
                            ->send();
                    }

                    if (class_exists(\Filament\Support\Exceptions\Halt::class)) {
                        throw new \Filament\Support\Exceptions\Halt();
                    }
                }
            } catch (\Filament\Support\Exceptions\Halt $e) {
                throw $e;
            } catch (\Throwable $e) {
                // Filament not booted — fall through to RuntimeException.
            }
        }

        throw new \RuntimeException($message);
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

        // Match the gate: count active users only. Inactive /
        // suspended / pending users don't consume a seat, so they
        // shouldn't trigger overage notifications either.
        $currentUserCount = User::query()->where('status', UserStatus::ACTIVE)->count();

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
