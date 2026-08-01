<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return $next($request);
        }

        // Check if 2FA is enabled for this user. Some auth models (e.g. OwnerUser)
        // don't implement 2FA — skip the challenge for them instead of erroring.
        if (method_exists($user, 'hasEnabledTwoFactorAuthentication') && $user->hasEnabledTwoFactorAuthentication()) {
            // Check if 2FA has been verified for this session
            if (! $request->session()->get('two_factor_verified')) {
                // Store the intended URL
                $request->session()->put('url.intended', $request->url());

                // Redirect to 2FA challenge
                return redirect()->route('two-factor.challenge');
            }
        }

        return $next($request);
    }
}
