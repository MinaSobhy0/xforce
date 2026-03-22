<?php

namespace Modules\Api\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Patients\Models\Patient;
use Modules\Auth\Models\User;
use Modules\PatientPortal\Services\OtpService;

class AuthController extends BaseApiController
{
    /**
     * Request OTP for patient login
     */
    public function requestOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $phone = $this->normalizePhone($request->phone);

        $patient = Patient::where('phone', $phone)
            ->orWhere('phone', 'LIKE', '%' . substr($phone, -10))
            ->first();

        if (!$patient) {
            return $this->error(__('api::api.patient_not_found'), 404);
        }

        if (!$patient->is_active) {
            return $this->error(__('api::api.account_inactive'), 403);
        }

        $otpService = app(OtpService::class);
        if ($otpService->generateAndSend($patient)) {
            return $this->success(
                ['phone' => $phone],
                __('api::api.otp_sent')
            );
        }

        return $this->error(__('api::api.otp_send_failed'), 500);
    }

    /**
     * Verify OTP and login patient
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'otp' => 'required|string|size:6',
        ]);

        $phone = $this->normalizePhone($request->phone);

        $otpService = app(OtpService::class);
        $result = $otpService->verify($phone, $request->otp);

        if (!$result['success']) {
            return $this->error($result['message'], 401);
        }

        $patient = Patient::find($result['patient_id']);
        if (!$patient) {
            return $this->error(__('api::api.patient_not_found'), 404);
        }

        // Create token
        $tokenConfig = config('api.tokens.patient');
        $token = $patient->createToken(
            $tokenConfig['name'],
            $tokenConfig['abilities'],
            now()->addDays($tokenConfig['expiration_days'])
        );

        // Update last login
        $patient->update(['last_portal_login_at' => now()]);

        return $this->success([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at,
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->full_name,
                'email' => $patient->email,
                'phone' => $patient->phone,
            ],
        ], __('api::api.login_success'));
    }

    /**
     * Staff login with email and password
     */
    public function staffLogin(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // SECURITY: Exclude soft-deleted users to prevent terminated employee access
        $user = User::withoutTrashed()->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->error(__('api::api.invalid_credentials'), 401);
        }

        if (!$user->is_active) {
            return $this->error(__('api::api.account_inactive'), 403);
        }

        // Create token
        $tokenConfig = config('api.tokens.staff');
        $token = $user->createToken(
            $tokenConfig['name'],
            $tokenConfig['abilities'],
            now()->addDays($tokenConfig['expiration_days'])
        );

        return $this->success([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
            ],
        ], __('api::api.login_success'));
    }

    /**
     * Logout (revoke current token)
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, __('api::api.logout_success'));
    }

    /**
     * Logout all devices (revoke all tokens)
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return $this->success(null, __('api::api.logout_all_success'));
    }

    /**
     * Get current user
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof Patient) {
            return $this->success([
                'type' => 'patient',
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'loyalty_points' => $user->loyalty_points ?? 0,
            ]);
        }

        return $this->success([
            'type' => 'staff',
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    /**
     * Normalize phone number
     */
    protected function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '+20' . substr($phone, 1);
        } elseif (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        return $phone;
    }
}
