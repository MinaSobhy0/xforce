<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Two-Factor Authentication Enforcement Middleware
 *
 * Forces users to set up 2FA before accessing protected routes.
 * This is different from EnsureTwoFactorAuthenticated which verifies
 * an already-configured 2FA - this middleware ensures 2FA is SET UP.
 *
 * Usage in routes:
 *   Route::middleware('2fa-enforce')->group(...)           // Enforce for all authenticated users
 *   Route::middleware('2fa-enforce:admin')->group(...)     // Enforce only for admin role
 *   Route::middleware('2fa-enforce:admin,manager')->group(...) // Enforce for multiple roles
 *
 * Configuration (config/security.php):
 *   '2fa' => [
 *       'enforce' => true,
 *       'enforce_for_roles' => ['admin', 'manager', 'owner'],
 *       'grace_period_days' => 7,  // Days before enforcement kicks in
 *       'setup_route' => 'profile.2fa.setup',
 *   ]
 */
class TwoFactorEnforce
{
    /**
     * Routes to exclude from 2FA enforcement (allow access to set up 2FA).
     */
    protected array $excludedRoutes = [
        'profile/two-factor*',
        'two-factor*',
        '2fa*',
        'logout',
        'livewire/*',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Skip if not authenticated
        if (!$user) {
            return $next($request);
        }

        // Skip excluded routes (2FA setup pages, logout, etc.)
        if ($this->isExcludedRoute($request)) {
            return $next($request);
        }

        // Skip if 2FA enforcement is disabled globally
        if (!config('security.2fa.enforce', true)) {
            return $next($request);
        }

        // Skip if user already has 2FA enabled
        if ($this->userHas2FAEnabled($user)) {
            return $next($request);
        }

        // Check if enforcement applies to this user
        if (!$this->shouldEnforce($user, $roles)) {
            return $next($request);
        }

        // Check grace period
        if ($this->isWithinGracePeriod($user)) {
            // Optionally show a warning but allow access
            $this->addGracePeriodWarning($request, $user);
            return $next($request);
        }

        // Redirect to 2FA setup
        return $this->redirectTo2FASetup($request);
    }

    /**
     * Check if the current route is excluded from enforcement.
     */
    protected function isExcludedRoute(Request $request): bool
    {
        foreach ($this->excludedRoutes as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has 2FA enabled.
     */
    protected function userHas2FAEnabled($user): bool
    {
        // Check for Laravel Fortify 2FA
        if (method_exists($user, 'hasEnabledTwoFactorAuthentication')) {
            return $user->hasEnabledTwoFactorAuthentication();
        }

        // Check for custom 2FA implementation
        if (property_exists($user, 'two_factor_secret') || isset($user->two_factor_secret)) {
            return !empty($user->two_factor_secret);
        }

        // Check for 2FA via settings
        if (method_exists($user, 'getSetting')) {
            return (bool) $user->getSetting('2fa_enabled', false);
        }

        return false;
    }

    /**
     * Determine if 2FA should be enforced for this user.
     */
    protected function shouldEnforce($user, array $specifiedRoles): bool
    {
        // If specific roles are passed to middleware, check those
        if (!empty($specifiedRoles)) {
            return $this->userHasAnyRole($user, $specifiedRoles);
        }

        // Otherwise, check config for enforced roles
        $enforcedRoles = config('security.2fa.enforce_for_roles', []);

        // If no roles specified in config, enforce for all users
        if (empty($enforcedRoles)) {
            return true;
        }

        return $this->userHasAnyRole($user, $enforcedRoles);
    }

    /**
     * Check if user has any of the specified roles.
     */
    protected function userHasAnyRole($user, array $roles): bool
    {
        // Check using Spatie Permission
        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole($roles);
        }

        // Check using hasRole method
        if (method_exists($user, 'hasRole')) {
            foreach ($roles as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }
            return false;
        }

        // Check via role relationship
        if (method_exists($user, 'roles')) {
            $userRoles = $user->roles->pluck('name')->toArray();
            return !empty(array_intersect($roles, $userRoles));
        }

        // Check via simple role attribute
        if (property_exists($user, 'role') || isset($user->role)) {
            return in_array($user->role, $roles);
        }

        // Default: enforce for all if we can't determine role
        return true;
    }

    /**
     * Check if user is within the grace period.
     */
    protected function isWithinGracePeriod($user): bool
    {
        $gracePeriodDays = config('security.2fa.grace_period_days', 0);

        if ($gracePeriodDays <= 0) {
            return false;
        }

        // Check when enforcement started for this user
        $enforcementStartDate = $this->getEnforcementStartDate($user);

        if (!$enforcementStartDate) {
            // First time - set the enforcement start date
            $this->setEnforcementStartDate($user);
            return true;
        }

        return $enforcementStartDate->addDays($gracePeriodDays)->isFuture();
    }

    /**
     * Get the date when 2FA enforcement started for the user.
     */
    protected function getEnforcementStartDate($user): ?\Carbon\Carbon
    {
        // Check in user settings
        if (method_exists($user, 'getSetting')) {
            $date = $user->getSetting('2fa_enforcement_started_at');
            return $date ? \Carbon\Carbon::parse($date) : null;
        }

        // Check in user meta/attributes
        if (isset($user->meta['2fa_enforcement_started_at'])) {
            return \Carbon\Carbon::parse($user->meta['2fa_enforcement_started_at']);
        }

        return null;
    }

    /**
     * Set the enforcement start date for the user.
     */
    protected function setEnforcementStartDate($user): void
    {
        $now = now()->toIso8601String();

        // Try to save to user settings
        if (method_exists($user, 'setSetting')) {
            $user->setSetting('2fa_enforcement_started_at', $now);
            $user->save();
            return;
        }

        // Try to save to user meta
        if (method_exists($user, 'setMeta')) {
            $user->setMeta('2fa_enforcement_started_at', $now);
            $user->save();
            return;
        }

        // Fall back to session
        session()->put('2fa_enforcement_started_at', $now);
    }

    /**
     * Add a grace period warning to the session.
     */
    protected function addGracePeriodWarning(Request $request, $user): void
    {
        $gracePeriodDays = config('security.2fa.grace_period_days', 0);
        $enforcementStartDate = $this->getEnforcementStartDate($user);

        if (!$enforcementStartDate) {
            return;
        }

        $daysRemaining = now()->diffInDays($enforcementStartDate->addDays($gracePeriodDays), false);

        if ($daysRemaining > 0 && $daysRemaining <= 3) {
            session()->flash('2fa_warning', "Two-factor authentication will be required in {$daysRemaining} day(s). Please set it up in your profile settings.");
        }
    }

    /**
     * Redirect to 2FA setup page.
     */
    protected function redirectTo2FASetup(Request $request): Response
    {
        // Store intended URL for redirect after 2FA setup
        session()->put('url.intended', $request->fullUrl());

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error' => '2fa_required',
                'message' => 'Two-factor authentication setup is required to continue.',
                'setup_url' => $this->get2FASetupUrl(),
            ], 403);
        }

        // Flash message for the user
        session()->flash('2fa_required', 'For your security, please set up two-factor authentication to continue.');

        // Redirect to 2FA setup page
        return redirect($this->get2FASetupUrl());
    }

    /**
     * Get the 2FA setup URL.
     */
    protected function get2FASetupUrl(): string
    {
        $routeName = config('security.2fa.setup_route', 'profile.two-factor.setup');

        try {
            return route($routeName);
        } catch (\Exception $e) {
            // Fallback URLs
            $fallbacks = [
                '/admin/user/two-factor-authentication',
                '/profile/two-factor',
                '/settings/security',
            ];

            foreach ($fallbacks as $fallback) {
                return url($fallback);
            }
        }

        return url('/profile');
    }
}
