<?php

namespace Modules\Auth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Auth\Models\User;

class ImpersonateController extends Controller
{
    public function login(Request $request)
    {
        $token = $request->query('token');
        $userId = $request->query('user');

        if (!$token || !$userId) {
            abort(403, 'Invalid impersonation link');
        }

        $hashedToken = hash('sha256', $token);

        $user = User::where('id', $userId)
            ->where('impersonation_token', $hashedToken)
            ->where('impersonation_token_expires_at', '>', now())
            ->first();

        if (!$user) {
            abort(403, 'Invalid or expired impersonation link');
        }

        // Clear the token
        $user->update([
            'impersonation_token' => null,
            'impersonation_token_expires_at' => null,
        ]);

        // Mark session as impersonated
        session(['impersonated_by' => 'platform_admin', 'impersonated_at' => now()]);

        // Login as the user
        Auth::login($user);

        return redirect('/admin');
    }
}
