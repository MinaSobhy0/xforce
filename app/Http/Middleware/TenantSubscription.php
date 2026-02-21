<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Modules\Core\Models\Tenant;
use Modules\Core\Models\TenantStatus;

/**
 * Middleware to check tenant subscription status.
 *
 * Usage in routes:
 *   ->middleware('tenant.subscription')           // Check if subscription is active
 *   ->middleware('tenant.subscription:active')    // Require active subscription
 *   ->middleware('tenant.subscription:trial')     // Allow trial subscriptions
 *   ->middleware('tenant.subscription:grace')     // Allow grace period
 *
 * Default behavior (no params): Requires active subscription or valid trial
 */
class TenantSubscription
{
    /**
     * Routes that bypass subscription check (always accessible).
     */
    protected array $bypassRoutes = [
        'filament.owner.pages.my-subscription',
        'filament.owner.pages.billing',
        'filament.tenant.pages.subscription-expired',
        'logout',
        'login',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$allowedStatuses  Allowed subscription statuses
     */
    public function handle(Request $request, Closure $next, string ...$allowedStatuses): Response
    {
        // Check if route bypasses subscription check
        if ($this->shouldBypass($request)) {
            return $next($request);
        }

        // Get current tenant
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            // No tenant context - allow request (handled by other middleware)
            return $next($request);
        }

        // Default allowed statuses
        if (empty($allowedStatuses)) {
            $allowedStatuses = ['active', 'trial'];
        }

        // Check subscription status
        $subscriptionStatus = $this->getSubscriptionStatus($tenant);

        if (!in_array($subscriptionStatus, $allowedStatuses)) {
            return $this->handleInvalidSubscription($request, $tenant, $subscriptionStatus);
        }

        // Additional check: is subscription expired?
        if ($this->isSubscriptionExpired($tenant) && !in_array('grace', $allowedStatuses)) {
            // Check if in grace period
            if ($this->isInGracePeriod($tenant)) {
                // Add warning to session
                session()->flash('warning', __('Your subscription has expired. You have :days days remaining in the grace period.', [
                    'days' => $this->getGracePeriodDaysRemaining($tenant)
                ]));
            } else {
                return $this->handleExpiredSubscription($request, $tenant);
            }
        }

        return $next($request);
    }

    /**
     * Check if request should bypass subscription check.
     */
    protected function shouldBypass(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        if ($routeName && in_array($routeName, $this->bypassRoutes)) {
            return true;
        }

        // Allow subscription management pages
        if ($request->is('*/subscription*', '*/billing*', '*/upgrade*')) {
            return true;
        }

        return false;
    }

    /**
     * Get the current tenant from context.
     */
    protected function getCurrentTenant(): ?Tenant
    {
        // Try getting from request attributes (set by IdentifyTenant middleware)
        $tenant = request()->attributes->get('tenant');

        if ($tenant) {
            return $tenant;
        }

        // Try getting from session
        $tenantId = session('tenant_id');
        if ($tenantId) {
            return Tenant::find($tenantId);
        }

        // Try getting from authenticated user
        $user = auth()->user();
        if ($user && method_exists($user, 'tenant')) {
            return $user->tenant;
        }

        return null;
    }

    /**
     * Get tenant's subscription status.
     */
    protected function getSubscriptionStatus(Tenant $tenant): string
    {
        // Check tenant status first
        if ($tenant->status === TenantStatus::SUSPENDED) {
            return 'suspended';
        }

        // Check if in trial
        if ($tenant->trial_ends_at && $tenant->trial_ends_at->isFuture()) {
            return 'trial';
        }

        // Check subscription record
        $subscription = $tenant->subscription;
        if ($subscription) {
            if ($subscription->isActive()) {
                return 'active';
            }
            if ($subscription->isCanceled()) {
                return 'canceled';
            }
            if ($subscription->isInGracePeriod()) {
                return 'grace';
            }
            if ($subscription->isExpired()) {
                return 'expired';
            }
        }

        // Check legacy subscription_status field
        $legacyStatus = $tenant->subscription_status;
        if ($legacyStatus) {
            return $legacyStatus;
        }

        // Default: check expiration
        if ($tenant->subscription_expires_at) {
            return $tenant->subscription_expires_at->isFuture() ? 'active' : 'expired';
        }

        // No subscription info - assume active (free tier)
        return 'active';
    }

    /**
     * Check if subscription is expired.
     */
    protected function isSubscriptionExpired(Tenant $tenant): bool
    {
        // Check subscription record
        $subscription = $tenant->subscription;
        if ($subscription) {
            return $subscription->isExpired();
        }

        // Check legacy field
        if ($tenant->subscription_expires_at) {
            return $tenant->subscription_expires_at->isPast();
        }

        // Check trial
        if ($tenant->trial_ends_at) {
            return $tenant->trial_ends_at->isPast();
        }

        return false;
    }

    /**
     * Check if tenant is in grace period.
     */
    protected function isInGracePeriod(Tenant $tenant): bool
    {
        $subscription = $tenant->subscription;
        if ($subscription) {
            return $subscription->isInGracePeriod();
        }

        // Default grace period: 7 days after expiration
        $expiresAt = $tenant->subscription_expires_at ?? $tenant->trial_ends_at;
        if ($expiresAt && $expiresAt->isPast()) {
            $gracePeriodDays = config('xlinic.subscription.grace_period_days', 7);
            return $expiresAt->addDays($gracePeriodDays)->isFuture();
        }

        return false;
    }

    /**
     * Get remaining days in grace period.
     */
    protected function getGracePeriodDaysRemaining(Tenant $tenant): int
    {
        $subscription = $tenant->subscription;
        if ($subscription && $subscription->grace_period_ends_at) {
            return max(0, now()->diffInDays($subscription->grace_period_ends_at, false));
        }

        $expiresAt = $tenant->subscription_expires_at ?? $tenant->trial_ends_at;
        if ($expiresAt) {
            $gracePeriodDays = config('xlinic.subscription.grace_period_days', 7);
            $graceEndsAt = $expiresAt->addDays($gracePeriodDays);
            return max(0, now()->diffInDays($graceEndsAt, false));
        }

        return 0;
    }

    /**
     * Handle invalid subscription status.
     */
    protected function handleInvalidSubscription(Request $request, Tenant $tenant, string $status): Response
    {
        $message = match ($status) {
            'suspended' => __('Your account has been suspended. Please contact support.'),
            'canceled' => __('Your subscription has been canceled. Please renew to continue.'),
            'expired' => __('Your subscription has expired. Please renew to continue.'),
            'trial' => __('Your trial has ended. Please subscribe to continue.'),
            default => __('Your subscription status does not allow access to this feature.'),
        };

        // API request - return JSON
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'error_code' => 'SUBSCRIPTION_INVALID',
                'status' => $status,
            ], 403);
        }

        // Web request - redirect to subscription page
        return redirect()
            ->route('filament.owner.pages.my-subscription')
            ->with('error', $message);
    }

    /**
     * Handle expired subscription.
     */
    protected function handleExpiredSubscription(Request $request, Tenant $tenant): Response
    {
        $message = __('Your subscription has expired. Please renew to continue using the platform.');

        // API request - return JSON
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'error_code' => 'SUBSCRIPTION_EXPIRED',
                'expires_at' => $tenant->subscription_expires_at?->toIso8601String(),
            ], 403);
        }

        // Web request - redirect to subscription page
        return redirect()
            ->route('filament.owner.pages.my-subscription')
            ->with('error', $message);
    }
}
