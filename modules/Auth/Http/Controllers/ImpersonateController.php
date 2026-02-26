<?php

namespace Modules\Auth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\User;

class ImpersonateController extends Controller
{
    public function login(Request $request)
    {
        $token = $request->query('token');
        $userId = $request->query('user');

        if (!$token || !$userId || !is_numeric($userId)) {
            abort(403, 'Invalid impersonation link');
        }

        $userId = (int) $userId;

        $hashedToken = hash('sha256', $token);

        // Validate the token using direct DB query to avoid audit triggers
        $userData = DB::table('users')
            ->where('id', $userId)
            ->where('impersonation_token', $hashedToken)
            ->where('impersonation_token_expires_at', '>', now())
            ->first();

        if (!$userData) {
            abort(403, 'Invalid or expired impersonation link');
        }

        // Clear the token using direct DB query (no audit trigger)
        DB::table('users')
            ->where('id', $userId)
            ->update([
                'impersonation_token' => null,
                'impersonation_token_expires_at' => null,
            ]);

        // Fetch the actual User model for authentication
        $user = User::find($userId);

        if (!$user) {
            abort(403, 'User not found');
        }

        // Mark session as impersonated
        session(['impersonated_by' => 'platform_admin', 'impersonated_at' => now()]);

        // Login as the user (creates normal session)
        Auth::login($user, true);

        return redirect('/admin');
    }
}
