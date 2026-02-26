<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request)
    {
        if (!$request->session()->has('login.id')) {
            return redirect()->route('filament.super-admin.auth.login');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'nullable|string',
            'recovery_code' => 'nullable|string',
        ]);

        $user = Auth::user();

        if (!$user) {
            $loginId = $request->session()->get('login.id');
            // Only try to find user if ID is numeric (INT primary keys)
            if ($loginId && is_numeric($loginId)) {
                $user = \Modules\Auth\Models\User::find((int) $loginId);
            }
        }

        if (!$user) {
            // Clear any invalid session data
            $request->session()->forget('login.id');
            return redirect()->route('filament.super-admin.auth.login');
        }

        $code = $request->input('code');
        $recoveryCode = $request->input('recovery_code');

        if ($code) {
            if ($user->verifyTwoFactorCode($code)) {
                return $this->loginAndRedirect($request, $user);
            }
        } elseif ($recoveryCode) {
            if ($user->verifyRecoveryCode($recoveryCode)) {
                return $this->loginAndRedirect($request, $user);
            }
        }

        throw ValidationException::withMessages([
            'code' => [__('The provided two factor authentication code was invalid.')],
        ]);
    }

    protected function loginAndRedirect(Request $request, $user)
    {
        Auth::login($user);

        $request->session()->forget('login.id');
        $request->session()->put('two_factor_verified', true);
        $request->session()->regenerate();

        return redirect()->intended(route('filament.super-admin.pages.dashboard'));
    }
}
