<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request)
    {
        // SECURITY: Validate login.id exists
        if (!$request->session()->has('login.id')) {
            return redirect()->route('filament.super-admin.auth.login');
        }

        // SECURITY: Set timestamp if not set (for expiration check)
        if (!$request->session()->has('login.time')) {
            $request->session()->put('login.time', now()->timestamp);
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'nullable|string',
            'recovery_code' => 'nullable|string',
        ]);

        // SECURITY: Use atomic lock to prevent race conditions
        $sessionId = $request->session()->getId();
        $lock = Cache::lock("2fa_challenge:{$sessionId}", 10);

        if (!$lock->get()) {
            throw ValidationException::withMessages([
                'code' => [__('Too many requests. Please try again.')],
            ]);
        }

        try {
            $user = Auth::user();

            if (!$user) {
                $loginId = $request->session()->get('login.id');
                $loginHash = $request->session()->get('login.hash');
                $loginTime = $request->session()->get('login.time');

                // SECURITY: Validate login.id exists
                if (!$loginId) {
                    $this->clearLoginSession($request);
                    return redirect()->route('filament.super-admin.auth.login');
                }

                // SECURITY: Verify the challenge hasn't expired (5 minutes max)
                if ($loginTime && (now()->timestamp - $loginTime > 300)) {
                    $this->clearLoginSession($request);
                    throw ValidationException::withMessages([
                        'code' => [__('The two factor challenge has expired. Please login again.')],
                    ]);
                }

                // Only try to find user if ID is numeric (INT primary keys)
                if (!is_numeric($loginId)) {
                    $this->clearLoginSession($request);
                    return redirect()->route('filament.super-admin.auth.login');
                }

                $user = \Modules\Auth\Models\User::find((int) $loginId);

                if (!$user) {
                    $this->clearLoginSession($request);
                    return redirect()->route('filament.super-admin.auth.login');
                }

                // SECURITY: If login.hash exists, verify it matches the user's password hash
                // This prevents session injection attacks where attacker changes login.id
                if ($loginHash && !hash_equals(hash('sha256', $user->password), $loginHash)) {
                    $this->clearLoginSession($request);
                    return redirect()->route('filament.super-admin.auth.login');
                }
            }

            if (!$user) {
                $this->clearLoginSession($request);
                return redirect()->route('filament.super-admin.auth.login');
            }

            $code = $request->input('code');
            $recoveryCode = $request->input('recovery_code');

            if ($code) {
                if ($user->verifyTwoFactorCode($code)) {
                    return $this->loginAndRedirect($request, $user);
                }
            } elseif ($recoveryCode) {
                $result = $user->verifyRecoveryCode($recoveryCode);
                if ($result === true) {
                    return $this->loginAndRedirect($request, $user);
                } elseif ($result === 'locked') {
                    throw ValidationException::withMessages([
                        'recovery_code' => [__('Too many failed attempts. Please try again later.')],
                    ]);
                }
            }

            throw ValidationException::withMessages([
                'code' => [__('The provided two factor authentication code was invalid.')],
            ]);
        } finally {
            $lock->release();
        }
    }

    /**
     * SECURITY: Clear all login-related session data.
     */
    protected function clearLoginSession(Request $request): void
    {
        $request->session()->forget(['login.id', 'login.hash', 'login.time']);
    }

    protected function loginAndRedirect(Request $request, $user)
    {
        Auth::login($user);

        // SECURITY: Clear all 2FA challenge session data
        $this->clearLoginSession($request);
        $request->session()->put('two_factor_verified', true);
        $request->session()->regenerate();

        return redirect()->intended(route('filament.super-admin.pages.dashboard'));
    }
}
