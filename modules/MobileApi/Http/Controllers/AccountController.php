<?php

namespace Modules\MobileApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends BaseApiController
{
    /**
     * Soft-delete the authenticated user's account and revoke all their tokens.
     *
     * Requires the user's current password for confirmation.
     *
     * DELETE /api/v2/account
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ], [
            'current_password.current_password' => __('mobile_api::mobile.account.password_incorrect'),
        ]);

        $user = $request->user();

        // Revoke every Sanctum token so the caller (and any other device)
        // is immediately logged out.
        $user->tokens()->delete();

        // Soft delete — SoftDeletes trait on User stamps deleted_at.
        // The login flow already filters via User::withoutTrashed(), so the
        // account cannot authenticate again.
        $user->delete();

        return $this->success(null, __('mobile_api::mobile.account.deleted'));
    }
}
