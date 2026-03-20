<?php

namespace Modules\MobileApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckStaffPermission
{
    /**
     * Handle an incoming request.
     * Checks if user is a staff member and has required permissions.
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => __('mobile_api::mobile.general.unauthorized'),
            ], 401);
        }

        // Must have staff profile
        if (!$user->staffProfile) {
            return response()->json([
                'success' => false,
                'message' => __('mobile_api::mobile.auth.not_staff'),
            ], 403);
        }

        // Check if staff profile is active
        if (!$user->staffProfile->is_active) {
            return response()->json([
                'success' => false,
                'message' => __('mobile_api::mobile.auth.account_disabled'),
            ], 403);
        }

        // Check permissions if specified
        foreach ($permissions as $permission) {
            if (!$user->can($permission)) {
                return response()->json([
                    'success' => false,
                    'message' => __('mobile_api::mobile.general.forbidden'),
                    'required_permission' => $permission,
                ], 403);
            }
        }

        return $next($request);
    }
}
