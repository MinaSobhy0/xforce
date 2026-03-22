<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant Usage Limit Middleware
 *
 * Enforces tenant resource limits (branches, users, patients, etc.)
 * based on the tenant's subscription plan.
 *
 * Usage in routes:
 *   Route::middleware('tenant-limit:branches')->group(...)
 *   Route::middleware('tenant-limit:users')->group(...)
 *   Route::middleware('tenant-limit:patients')->group(...)
 */
class TenantUsageLimit
{
    /**
     * Map of resource types to their model classes and limit fields.
     */
    protected array $resourceMap = [
        'branches' => [
            'model' => \Modules\Core\Models\Branch::class,
            'limit_field' => 'max_branches',
            'name' => 'branches',
        ],
        'users' => [
            'model' => \App\Models\User::class,
            'limit_field' => 'max_users',
            'name' => 'users',
        ],
        'patients' => [
            'model' => \Modules\Patients\Models\Patient::class,
            'limit_field' => 'max_patients',
            'name' => 'patients',
        ],
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $resource): Response
    {
        // Only check on create/store actions
        if (!$this->isCreateAction($request)) {
            return $next($request);
        }

        $tenant = app('currentTenant');

        if (!$tenant) {
            return $next($request);
        }

        if (!isset($this->resourceMap[$resource])) {
            return $next($request);
        }

        $config = $this->resourceMap[$resource];
        $modelClass = $config['model'];

        if (!class_exists($modelClass)) {
            return $next($request);
        }

        $currentCount = $modelClass::count();

        // SECURITY: Calculate total allowed including base limit + extra purchased capacity
        $baseLimit = $tenant->{$config['limit_field']} ?? 0;
        $extraField = 'extra_' . $resource;
        $extraAllowed = $tenant->{$extraField} ?? 0;
        $maxAllowed = $baseLimit + $extraAllowed;

        // If no limit is set (0), allow unlimited (backwards compatibility)
        if ($maxAllowed <= 0) {
            return $next($request);
        }

        if ($currentCount >= $maxAllowed) {
            // Log the limit enforcement for audit
            \Log::warning('Tenant usage limit enforced', [
                'tenant_id' => $tenant->id,
                'resource' => $resource,
                'current' => $currentCount,
                'max' => $maxAllowed,
                'user_id' => auth()->id(),
            ]);

            return $this->limitExceededResponse($request, $config['name'], $currentCount, $maxAllowed);
        }

        return $next($request);
    }

    /**
     * Check if the current request is a create action.
     */
    protected function isCreateAction(Request $request): bool
    {
        // Check for POST method (typical create)
        if ($request->isMethod('POST')) {
            return true;
        }

        // Check URL patterns for create pages
        $path = $request->path();

        return str_contains($path, '/create') || str_ends_with($path, '/create');
    }

    /**
     * Return appropriate response when limit is exceeded.
     */
    protected function limitExceededResponse(Request $request, string $resource, int $current, int $max): Response
    {
        $message = __("You have reached the maximum of :max :resource allowed for your plan. You currently have :current.", [
            'max' => $max,
            'resource' => $resource,
            'current' => $current,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error' => 'limit_exceeded',
                'message' => $message,
                'resource' => $resource,
                'current' => $current,
                'max' => $max,
            ], 403);
        }

        session()->flash('error', $message);

        // Redirect back or to the resource index
        return redirect()->back();
    }
}
