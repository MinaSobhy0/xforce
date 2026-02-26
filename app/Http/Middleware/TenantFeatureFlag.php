<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Modules\Core\Models\Tenant;
use App\Models\SubscriptionPlan;

/**
 * Middleware to check if tenant has specific feature flags enabled.
 *
 * Usage in routes:
 *   ->middleware('tenant.feature:allow_api_access')
 *   ->middleware('tenant.feature:allow_white_label,allow_custom_domain')
 *
 * Available feature flags (from SubscriptionPlan):
 *   - allow_white_label
 *   - allow_custom_domain
 *   - allow_data_export
 *   - allow_api_access
 *   - has_priority_support
 */
class TenantFeatureFlag
{
    /**
     * Feature flags that can be checked.
     */
    protected array $validFlags = [
        'allow_white_label',
        'allow_custom_domain',
        'allow_data_export',
        'allow_api_access',
        'has_priority_support',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$flags  Feature flags to check
     */
    public function handle(Request $request, Closure $next, string ...$flags): Response
    {
        // Skip check if no flags specified
        if (empty($flags)) {
            return $next($request);
        }

        // Get current tenant
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            // No tenant context - allow request (handled by other middleware)
            return $next($request);
        }

        // Check each required feature flag
        foreach ($flags as $flag) {
            if (!$this->tenantHasFeatureFlag($tenant, $flag)) {
                return $this->denyAccess($request, $flag);
            }
        }

        return $next($request);
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

        // Try getting from session (only if numeric ID - INT primary keys)
        $tenantId = session('tenant_id');
        if ($tenantId && is_numeric($tenantId)) {
            return Tenant::find((int) $tenantId);
        }

        // Try getting from authenticated user
        try {
            $user = auth()->user();
            if ($user && method_exists($user, 'tenant')) {
                return $user->tenant;
            }
        } catch (\Exception $e) {
            // Session may contain stale user ID (e.g., UUID from before migration)
            // Just continue without user context
        }

        return null;
    }

    /**
     * Check if tenant has a feature flag enabled.
     */
    protected function tenantHasFeatureFlag(Tenant $tenant, string $flag): bool
    {
        // Validate flag name
        if (!in_array($flag, $this->validFlags)) {
            // Unknown flag - check tenant's features array
            return $tenant->hasFeature($flag);
        }

        // Check subscription plan's feature flags
        $plan = $tenant->plan;
        if ($plan) {
            return (bool) $plan->{$flag};
        }

        // Check tenant's own settings (for custom configurations)
        $settings = $tenant->settings ?? [];
        if (isset($settings[$flag])) {
            return (bool) $settings[$flag];
        }

        // Check tenant's features array (legacy support)
        return $tenant->hasFeature($flag);
    }

    /**
     * Deny access to the feature.
     */
    protected function denyAccess(Request $request, string $flag): Response
    {
        $featureName = $this->getFeatureDisplayName($flag);

        // API request - return JSON
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => __('The :feature feature is not available on your current plan. Please upgrade to access this feature.', ['feature' => $featureName]),
                'error_code' => 'FEATURE_NOT_AVAILABLE',
                'feature' => $flag,
            ], 403);
        }

        // Web request - redirect with error
        return redirect()
            ->back()
            ->with('error', __('The :feature feature is not available on your current plan. Please upgrade to access this feature.', ['feature' => $featureName]));
    }

    /**
     * Get human-readable feature name.
     */
    protected function getFeatureDisplayName(string $flag): string
    {
        return match ($flag) {
            'allow_white_label' => __('White Label'),
            'allow_custom_domain' => __('Custom Domain'),
            'allow_data_export' => __('Data Export'),
            'allow_api_access' => __('API Access'),
            'has_priority_support' => __('Priority Support'),
            default => ucwords(str_replace('_', ' ', $flag)),
        };
    }
}
