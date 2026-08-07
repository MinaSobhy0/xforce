<?php

namespace Modules\MobileApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Auth\Models\User;

class AuthController extends BaseApiController
{
    /**
     * Staff login with email and password.
     * POST /api/v2/auth/staff/login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // SECURITY: Exclude soft-deleted users to prevent terminated employee access
        $user = User::withoutTrashed()->where('email', $request->email)->first();

        // SECURITY: Per-account lockout. The route throttle is keyed on IP
        // only, and X-Forwarded-For is honoured from any proxy, so an
        // attacker rotating that header gets unlimited guesses against a
        // single account. The failed_login_attempts / locked_until columns
        // already existed on users but nothing on this path touched them —
        // the mobile login was a bypass for the web panel's lockout too.
        if ($user && $user->isLocked()) {
            return $this->error(
                __('mobile_api::mobile.auth.account_locked', [
                    'minutes' => max(1, (int) now()->diffInMinutes($user->locked_until, false)),
                ]),
                429
            );
        }

        if (!$user || !Hash::check($request->password, $user->password)) {
            $user?->incrementFailedLoginAttempts();

            return $this->error(
                __('mobile_api::mobile.auth.login_failed'),
                401
            );
        }

        $user->resetFailedLoginAttempts();

        // Check if user is active
        if (!$user->isActive()) {
            return $this->error(
                __('mobile_api::mobile.auth.account_disabled'),
                403
            );
        }

        // Check if user has staff profile
        if (!$user->staffProfile) {
            return $this->error(
                __('mobile_api::mobile.auth.not_staff'),
                403
            );
        }

        // Check if 2FA is enabled
        if ($user->two_factor_secret && $user->two_factor_confirmed_at) {
            return $this->success([
                'requires_2fa' => true,
                'temp_token' => $this->generateTempToken($user),
            ], __('mobile_api::mobile.auth.2fa_required'));
        }

        // Create token
        $token = $this->createToken($user);

        return $this->success([
            'user' => $this->formatUserResponse($user),
            'token' => $token,
        ], __('mobile_api::mobile.auth.login_success'));
    }

    /**
     * Verify 2FA code.
     * POST /api/v2/auth/staff/2fa
     *
     * SECURITY: Rate limiting + failed attempt tracking to prevent brute force.
     * A 6-digit code has only 1 million combinations - must strictly limit attempts.
     */
    public function verify2fa(Request $request): JsonResponse
    {
        $request->validate([
            'temp_token' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        $user = $this->getUserFromTempToken($request->temp_token);

        if (!$user) {
            return $this->error(
                __('mobile_api::mobile.auth.invalid_token'),
                401
            );
        }

        // SECURITY: Check if user is locked out from 2FA attempts
        $lockoutKey = "2fa_lockout:{$user->id}";
        $attemptsKey = "2fa_attempts:{$user->id}";

        if (Cache::has($lockoutKey)) {
            $lockoutMinutes = Cache::get($lockoutKey);
            return $this->error(
                __('mobile_api::mobile.auth.2fa_locked_out', ['minutes' => $lockoutMinutes]),
                429
            );
        }

        // Verify the 2FA code
        $google2fa = app(\PragmaRX\Google2FA\Google2FA::class);
        $valid = $google2fa->verifyKey(
            decrypt($user->two_factor_secret),
            $request->code
        );

        if (!$valid) {
            // SECURITY: Track failed attempts with exponential backoff
            $attempts = Cache::increment($attemptsKey);
            Cache::put($attemptsKey, $attempts, now()->addMinutes(30));

            // Lock out after 5 failed attempts with exponential backoff
            if ($attempts >= 5) {
                $lockoutMinutes = min(60, pow(2, $attempts - 5) * 5); // 5, 10, 20, 40, 60 max
                Cache::put($lockoutKey, $lockoutMinutes, now()->addMinutes($lockoutMinutes));
                Cache::forget($attemptsKey);

                return $this->error(
                    __('mobile_api::mobile.auth.2fa_locked_out', ['minutes' => $lockoutMinutes]),
                    429
                );
            }

            $remainingAttempts = 5 - $attempts;
            return $this->error(
                __('mobile_api::mobile.auth.2fa_invalid_attempts', [
                    'remaining' => $remainingAttempts,
                ]),
                401
            );
        }

        // SECURITY: Clear failed attempts on success
        Cache::forget($attemptsKey);
        Cache::forget($lockoutKey);

        // Create token
        $token = $this->createToken($user);

        return $this->success([
            'user' => $this->formatUserResponse($user),
            'token' => $token,
        ], __('mobile_api::mobile.auth.2fa_success'));
    }

    /**
     * Logout and revoke token.
     * POST /api/v2/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, __('mobile_api::mobile.auth.logout_success'));
    }

    /**
     * Refresh the token.
     * POST /api/v2/auth/refresh
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        // Create new token
        $token = $this->createToken($user);

        return $this->success([
            'token' => $token,
        ], __('mobile_api::mobile.auth.token_refresh'));
    }

    /**
     * Get current user info.
     * GET /api/v2/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('staffProfile.branch');

        return $this->success([
            'user' => $this->formatUserResponse($user),
            'staff_profile' => $this->formatStaffProfileResponse($user->staffProfile),
            'permissions' => $this->formatPermissionsResponse($user),
            'branches' => $this->formatBranchesResponse($user),
        ]);
    }

    /**
     * Create an API token for the user.
     */
    protected function createToken(User $user): string
    {
        $tokenName = config('mobile_api.tokens.staff.name', 'staff-mobile-token');
        $abilities = config('mobile_api.tokens.staff.abilities', ['staff:*']);
        $expirationDays = config('mobile_api.tokens.staff.expiration_days', 30);

        $newToken = $user->createToken(
            $tokenName,
            $abilities,
            now()->addDays($expirationDays)
        );

        // SECURITY: stamp the issuing tenant. personal_access_tokens is a
        // shared public-schema table but `users` is per-tenant-schema, so an
        // unstamped token can be replayed against another tenant's slug and
        // resolve to that tenant's user with the same numeric id.
        // EnsureTokenMatchesTenant rejects any mismatch.
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        if ($tenant) {
            $newToken->accessToken->forceFill(['tenant_id' => $tenant->id])->save();
        }

        return $newToken->plainTextToken;
    }

    /**
     * Generate a temporary token for 2FA.
     */
    protected function generateTempToken(User $user): string
    {
        return encrypt([
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(5)->timestamp,
        ]);
    }

    /**
     * Get user from temporary token.
     */
    protected function getUserFromTempToken(string $token): ?User
    {
        try {
            $data = decrypt($token);

            if ($data['expires_at'] < now()->timestamp) {
                return null;
            }

            // Re-check the same gates login enforced. The account can be
            // deleted, disabled, locked or stripped of its staff profile in
            // the window between password success and 2FA submission.
            $user = User::withoutTrashed()->find($data['user_id']);

            if (! $user || ! $user->isActive() || $user->isLocked() || ! $user->staffProfile) {
                return null;
            }

            return $user;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Format user response.
     */
    protected function formatUserResponse(User $user): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_url,
            'locale' => $user->locale ?? 'en',
            'timezone' => $user->timezone,
        ];
    }

    /**
     * Format staff profile response.
     */
    protected function formatStaffProfileResponse($staffProfile): ?array
    {
        if (!$staffProfile) {
            return null;
        }

        return [
            'id' => $staffProfile->id,
            'employee_number' => $staffProfile->employee_number,
            'job_title' => $staffProfile->job_title,
            'department' => $staffProfile->department,
            'branch' => $staffProfile->branch ? [
                'id' => $staffProfile->branch->id,
                'name' => $staffProfile->branch->name,
            ] : null,
            'hire_date' => $staffProfile->hire_date?->toDateString(),
            'is_active' => $staffProfile->is_active,
        ];
    }

    /**
     * Format permissions response grouped by resource.
     */
    protected function formatPermissionsResponse(User $user): array
    {
        $permissions = $user->getAllPermissions()->pluck('name');

        $grouped = [];
        foreach ($permissions as $permission) {
            $parts = explode('.', $permission);
            if (count($parts) === 2) {
                $resource = $parts[0];
                $action = $parts[1];
                $grouped[$resource][] = $action;
            }
        }

        return $grouped;
    }

    /**
     * Format branches response.
     */
    protected function formatBranchesResponse(User $user): array
    {
        $branches = $user->branches ?? collect();
        $primaryBranchId = $user->staffProfile?->branch_id;

        return $branches->map(fn($branch) => [
            'id' => $branch->id,
            'name' => $branch->name,
            'is_primary' => $branch->id === $primaryBranchId,
        ])->values()->all();
    }
}
