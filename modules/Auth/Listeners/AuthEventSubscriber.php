<?php

namespace Modules\Auth\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Events\Dispatcher;
use Modules\Auth\Models\User;

/**
 * Wires Laravel's authentication events into two security mechanisms that
 * previously existed only as dead code:
 *
 *  - Account lockout (H-9): a failed login increments the user's
 *    failed_login_attempts counter and locks the account once the configured
 *    threshold is reached; a successful login resets the counter.
 *
 *  - Audit logging (H-8): login / logout / failed_login are written to the
 *    spatie activity log — the same mechanism (`activity()`) used across the
 *    rest of the codebase — so the events listed in
 *    config('security.audit.always_log') are actually recorded.
 *
 * Registered from AppServiceProvider::boot() so it applies to every guard and
 * panel, not just one Filament panel.
 */
class AuthEventSubscriber
{
    /**
     * Handle a failed authentication attempt.
     *
     * On the Failed event $event->user is usually null (the credentials did
     * not authenticate), so we resolve the target user by the supplied email
     * before touching the lockout counter.
     */
    public function handleFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        $user = $event->user instanceof User
            ? $event->user
            : ($email ? User::where('email', $email)->first() : null);

        // Only count attempts against a real, known account. We still audit
        // the attempt regardless so unknown-account probing is recorded.
        if ($user instanceof User) {
            try {
                $user->incrementFailedLoginAttempts();
            } catch (\Throwable $e) {
                // Never let audit/lockout bookkeeping break the auth flow.
                logger()->error('Failed-login lockout bookkeeping failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->audit('failed_login', $user, [
            'email' => $email,
        ]);
    }

    /**
     * Handle a successful login: clear the lockout counter and audit it.
     */
    public function handleLogin(Login $event): void
    {
        $user = $event->user;

        if ($user instanceof User) {
            try {
                $user->resetFailedLoginAttempts();
            } catch (\Throwable $e) {
                logger()->error('Login lockout-reset failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->audit('login', $user);
    }

    /**
     * Handle a logout: audit it.
     */
    public function handleLogout(Logout $event): void
    {
        $this->audit('logout', $event->user);
    }

    /**
     * Write an auth event to the activity log, honouring the audit toggle and
     * the always_log allow-list in config/security.php. Uses the same
     * `activity()` helper the rest of the app uses; failures are swallowed so
     * authentication is never blocked by logging.
     */
    protected function audit(string $event, ?Authenticatable $user, array $properties = []): void
    {
        if (! config('security.audit.enabled', true)) {
            return;
        }

        $alwaysLog = (array) config('security.audit.always_log', []);
        if (! empty($alwaysLog) && ! in_array($event, $alwaysLog, true)) {
            return;
        }

        if (! function_exists('activity')) {
            return;
        }

        try {
            $properties = array_merge($properties, array_filter([
                'ip' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ], fn ($value) => $value !== null));

            $logger = activity()->useLog('auth')->event($event);

            if ($user instanceof \Illuminate\Database\Eloquent\Model) {
                $logger = $logger->causedBy($user)->performedOn($user);
            }

            $logger->withProperties($properties)->log($event);
        } catch (\Throwable $e) {
            logger()->error('Auth audit logging failed', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register the listeners on the event dispatcher.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Failed::class => 'handleFailed',
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
        ];
    }
}
