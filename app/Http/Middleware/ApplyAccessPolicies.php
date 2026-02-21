<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Auth\Models\AccessPolicy;
use Symfony\Component\HttpFoundation\Response;

class ApplyAccessPolicies
{
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
    ];

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
            $modelPolicies = AccessPolicy::getPoliciesForUser($user, $modelClass);
            if ($modelPolicies->isNotEmpty()) {
                $policies[$modelClass] = $modelPolicies;
            }
        }

        // Bind to container for request lifetime
        app()->instance($cacheKey, $policies);

        // Store in request attributes for easy access
        request()->attributes->set('user_access_policies', $policies);
    }
}
