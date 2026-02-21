<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Auth\Models\AccessPolicy;
use Modules\Auth\Services\AccessPolicyService;
use Symfony\Component\HttpFoundation\Response;

/**
 * ApplyAccessPolicies Middleware
 *
 * This middleware pre-loads access policies for the current user and makes them
 * available for the HasAccessPolicyScope trait on models.
 *
 * Models using the HasAccessPolicyScope trait will automatically have their
 * queries filtered based on the AccessPolicy domain_filter rules.
 */
class ApplyAccessPolicies
{
    protected AccessPolicyService $accessPolicyService;

    /**
     * Models that have access policies applied automatically.
     */
    protected array $policyEnabledModels = [
        \Modules\Patients\Models\Patient::class,
        \Modules\Booking\Models\Appointment::class,
        \Modules\Billing\Models\Invoice::class,
        \Modules\Staff\Models\StaffProfile::class,
        \Modules\Equipment\Models\Equipment::class,
        \Modules\Inventory\Models\Product::class,
        \Modules\Inventory\Models\PurchaseOrder::class,
        \Modules\GiftCards\Models\GiftCard::class,
        \Modules\Packages\Models\PackageSubscription::class,
        \Modules\Memberships\Models\MembershipSubscription::class,
    ];

    public function __construct(AccessPolicyService $accessPolicyService)
    {
        $this->accessPolicyService = $accessPolicyService;
    }

    /**
     * Handle an incoming request.
     *
     * Loads and caches access policies for the current user.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return $next($request);
        }

        // Check if user is a super admin (bypasses all policies)
        $isSuperAdmin = $this->accessPolicyService->isSuperAdmin($user);
        $request->attributes->set('access_policy_bypass', $isSuperAdmin);

        if ($isSuperAdmin) {
            return $next($request);
        }

        // Pre-load and cache access policies for the user
        $this->loadUserPolicies($user);

        return $next($request);
    }

    /**
     * Pre-load access policies for the user.
     */
    protected function loadUserPolicies($user): void
    {
        // Cache policies per request to avoid repeated queries
        $cacheKey = "access_policies:{$user->id}";

        if (app()->bound($cacheKey)) {
            return;
        }

        $policies = [];

        foreach ($this->policyEnabledModels as $modelClass) {
            if (!class_exists($modelClass)) {
                continue;
            }

            try {
                $modelPolicies = $this->accessPolicyService->getPoliciesForUser($user, $modelClass, 'read');
                if ($modelPolicies->isNotEmpty()) {
                    $policies[$modelClass] = $modelPolicies;
                }
            } catch (\Exception $e) {
                // Log but continue
                \Log::debug("Could not load policies for {$modelClass}: " . $e->getMessage());
            }
        }

        // Bind to container for request lifetime
        app()->instance($cacheKey, $policies);

        // Store in request attributes for easy access
        $request = request();
        if ($request) {
            $request->attributes->set('user_access_policies', $policies);
        }
    }
}
