<?php

namespace Modules\PatientPortal\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePatientAuthenticated
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('patient')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('filament.portal.auth.login');
        }

        // Check if patient is active and has portal access
        $patient = Auth::guard('patient')->user();
        if ($patient && ($patient->status !== 'active' || !$patient->portal_access_enabled)) {
            Auth::guard('patient')->logout();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Account is inactive.'], 403);
            }

            return redirect()->route('filament.portal.auth.login')
                ->with('error', __('patientportal::portal.account_inactive'));
        }

        return $next($request);
    }
}
