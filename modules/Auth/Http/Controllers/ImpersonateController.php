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

        // SECURITY: Fetch user data first, then perform timing-safe comparison
        // This prevents attackers from detecting valid user IDs via timing differences
        $userData = DB::table('users')
            ->where('id', $userId)
            ->first(['id', 'impersonation_token', 'impersonation_token_expires_at']);

        // SECURITY: Always perform all checks to prevent timing-based enumeration
        // Use timing-safe comparison for the token to prevent character-by-character guessing
        $tokenValid = $userData
            && $userData->impersonation_token !== null
            && hash_equals((string) $userData->impersonation_token, $hashedToken);

        $tokenNotExpired = $userData
            && $userData->impersonation_token_expires_at !== null
            && now()->lt($userData->impersonation_token_expires_at);

        if (!$tokenValid || !$tokenNotExpired) {
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
