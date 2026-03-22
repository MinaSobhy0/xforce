<?php

namespace Modules\MobileApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Modules\Staff\Models\StaffCommissionRecord;

class StaffProfileController extends BaseApiController
{
    /**
     * Get staff profile details.
     * GET /api/v2/staff/profile
     */
    public function show(): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (! $staffProfile) {
            return $this->notFound();
        }

        $staffProfile->load(['branch', 'commissionPlan', 'user']);

        return $this->success([
            'id' => $staffProfile->id,
            'employee_number' => $staffProfile->employee_number,
            'user' => [
                'first_name' => $staffProfile->user->first_name,
                'last_name' => $staffProfile->user->last_name,
                'email' => $staffProfile->user->email,
                'phone' => $staffProfile->user->phone,
                'avatar_url' => $staffProfile->user->avatar_url,
            ],
            'job_title' => $staffProfile->job_title,
            'department' => $staffProfile->department,
            'branch' => $staffProfile->branch ? [
                'id' => $staffProfile->branch->id,
                'name' => $staffProfile->branch->name,
            ] : null,
            'hire_date' => $staffProfile->hire_date?->toDateString(),
            'bio' => $staffProfile->bio,
            'specializations' => $staffProfile->specializations,
            'emergency_contact_name' => $staffProfile->emergency_contact_name,
            'emergency_contact_phone' => $staffProfile->emergency_contact_phone,
            'commission_plan' => $staffProfile->commissionPlan ? [
                'id' => $staffProfile->commissionPlan->id,
                'name' => $staffProfile->commissionPlan->name,
                'type' => $staffProfile->commissionPlan->type,
            ] : null,
        ]);
    }

    /**
     * Update staff profile.
     * PUT /api/v2/staff/profile
     */
    public function update(Request $request): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (! $staffProfile) {
            return $this->notFound();
        }

        $validated = $request->validate([
            'phone' => 'sometimes|string|max:20',
            'emergency_contact_name' => 'sometimes|string|max:255',
            'emergency_contact_phone' => 'sometimes|string|max:20',
        ]);

        // Update user phone if provided
        if (isset($validated['phone'])) {
            $staffProfile->user->update(['phone' => $validated['phone']]);
            unset($validated['phone']);
        }

        // Update staff profile
        $staffProfile->update($validated);

        return $this->success(null, __('mobile_api::mobile.profile.updated'));
    }

    /**
     * Change user password.
     * POST /api/v2/staff/profile/change-password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $this->user();

        if (! $user) {
            return $this->notFound();
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        // Verify current password
        if (! Hash::check($validated['current_password'], $user->password)) {
            return $this->error(__('mobile_api::mobile.profile.current_password_incorrect'), 422);
        }

        // Ensure new password is different
        if (Hash::check($validated['new_password'], $user->password)) {
            return $this->error(__('mobile_api::mobile.profile.password_same_as_old'), 422);
        }

        // Update password
        $user->update([
            'password' => $validated['new_password'],
            'must_change_password' => false,
            'password_expires_at' => now()->addDays(90),
        ]);

        return $this->success(null, __('mobile_api::mobile.profile.password_changed'));
    }

    /**
     * Update user avatar.
     * POST /api/v2/staff/profile/avatar
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        $user = $this->user();

        if (! $user) {
            return $this->notFound();
        }

        $validated = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'], // 2MB max
        ]);

        // Delete old avatar if exists and is not a URL
        $oldAvatar = $user->getRawOriginal('avatar_url');
        if ($oldAvatar && ! filter_var($oldAvatar, FILTER_VALIDATE_URL)) {
            Storage::disk('public')->delete("avatars/{$oldAvatar}");
        }

        // Generate unique filename
        $filename = $user->id.'_'.time().'.'.$validated['avatar']->extension();

        // Store new avatar
        $validated['avatar']->storeAs('avatars', $filename, 'public');

        // Update user
        $user->update(['avatar_url' => $filename]);

        return $this->success([
            'avatar_url' => $user->avatar_url,
        ], __('mobile_api::mobile.profile.avatar_updated'));
    }

    /**
     * Delete user avatar (reset to default).
     * DELETE /api/v2/staff/profile/avatar
     */
    public function deleteAvatar(): JsonResponse
    {
        $user = $this->user();

        if (! $user) {
            return $this->notFound();
        }

        // Delete old avatar if exists and is not a URL
        $oldAvatar = $user->getRawOriginal('avatar_url');
        if ($oldAvatar && ! filter_var($oldAvatar, FILTER_VALIDATE_URL)) {
            Storage::disk('public')->delete("avatars/{$oldAvatar}");
        }

        // Reset to null (will use Gravatar)
        $user->update(['avatar_url' => null]);

        return $this->success([
            'avatar_url' => $user->avatar_url,
        ], __('mobile_api::mobile.profile.avatar_deleted'));
    }

    /**
     * Get commission plan details.
     * GET /api/v2/staff/commission
     */
    public function commission(): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (! $staffProfile) {
            return $this->notFound();
        }

        if (! $this->hasPermission('commission.view_own')) {
            return $this->forbidden();
        }

        $commissionPlan = $staffProfile->commissionPlan;

        if (! $commissionPlan) {
            return $this->success([
                'has_plan' => false,
                'message' => 'No commission plan assigned',
            ]);
        }

        // Get commission summary
        $summary = $this->getCommissionSummary($staffProfile);

        return $this->success([
            'has_plan' => true,
            'plan' => [
                'id' => $commissionPlan->id,
                'name' => $commissionPlan->name,
                'type' => $commissionPlan->type,
                'base_percentage' => $commissionPlan->base_percentage,
                'service_rules' => $commissionPlan->service_rules ?? [],
            ],
            'summary' => $summary,
        ]);
    }

    /**
     * Get commission history.
     * GET /api/v2/staff/commission/history
     */
    public function commissionHistory(Request $request): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (! $staffProfile) {
            return $this->notFound();
        }

        if (! $this->hasPermission('commission.view_own')) {
            return $this->forbidden();
        }

        if (! class_exists(StaffCommissionRecord::class)) {
            return $this->success([]);
        }

        $query = StaffCommissionRecord::with(['appointment.service'])
            ->where('staff_profile_id', $staffProfile->id)
            ->orderByDesc('created_at');

        // Filter by status if provided
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $records = $query->paginate($this->getPerPage());

        $formatted = collect($records->items())->map(fn ($r) => [
            'id' => $r->id,
            'amount' => $r->amount, // Uses accessor (amount_minor / 100)
            'revenue' => $r->revenue, // Uses accessor (revenue_minor / 100)
            'commission_type' => $r->commission_type,
            'commission_rate' => $r->commission_rate,
            'status' => $r->status,
            'status_label' => StaffCommissionRecord::STATUSES[$r->status] ?? $r->status,
            'service_name' => $r->appointment?->service?->name,
            'appointment_id' => $r->appointment_id,
            'appointment_date' => $r->appointment?->date?->toDateString(),
            'notes' => $r->notes,
            'approved_at' => $r->approved_at?->toDateTimeString(),
            'paid_at' => $r->paid_at?->toDateTimeString(),
            'created_at' => $r->created_at->toDateTimeString(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $formatted,
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ]);
    }

    /**
     * Get pending commissions.
     * GET /api/v2/staff/commission/pending
     */
    public function commissionPending(): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (! $staffProfile) {
            return $this->notFound();
        }

        if (! $this->hasPermission('commission.view_own')) {
            return $this->forbidden();
        }

        if (! class_exists(StaffCommissionRecord::class)) {
            return $this->success([
                'total' => 0,
                'records' => [],
            ]);
        }

        $records = StaffCommissionRecord::with(['appointment.service'])
            ->where('staff_profile_id', $staffProfile->id)
            ->pending()
            ->orderByDesc('created_at')
            ->get();

        return $this->success([
            'total' => $records->sum('amount'), // Uses accessor
            'count' => $records->count(),
            'records' => $records->map(fn ($r) => [
                'id' => $r->id,
                'amount' => $r->amount,
                'revenue' => $r->revenue,
                'commission_type' => $r->commission_type,
                'commission_rate' => $r->commission_rate,
                'service_name' => $r->appointment?->service?->name,
                'appointment_date' => $r->appointment?->date?->toDateString(),
                'created_at' => $r->created_at->toDateString(),
            ])->all(),
        ]);
    }

    /**
     * Get commission summary.
     */
    protected function getCommissionSummary($staffProfile): array
    {
        if (! class_exists(StaffCommissionRecord::class)) {
            return [
                'this_month' => 0,
                'pending' => 0,
                'approved' => 0,
                'paid' => 0,
            ];
        }

        // This month total (all statuses except cancelled)
        $thisMonth = StaffCommissionRecord::where('staff_profile_id', $staffProfile->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('status', '!=', StaffCommissionRecord::STATUS_CANCELLED)
            ->sum('amount_minor') / 100;

        // Pending total
        $pending = StaffCommissionRecord::where('staff_profile_id', $staffProfile->id)
            ->pending()
            ->sum('amount_minor') / 100;

        // Approved (waiting to be paid)
        $approved = StaffCommissionRecord::where('staff_profile_id', $staffProfile->id)
            ->approved()
            ->sum('amount_minor') / 100;

        // Paid total
        $paid = StaffCommissionRecord::where('staff_profile_id', $staffProfile->id)
            ->where('status', StaffCommissionRecord::STATUS_PAID)
            ->sum('amount_minor') / 100;

        return [
            'this_month' => round($thisMonth, 2),
            'pending' => round($pending, 2),
            'approved' => round($approved, 2),
            'paid' => round($paid, 2),
        ];
    }
}
