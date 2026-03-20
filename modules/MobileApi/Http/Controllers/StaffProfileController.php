<?php

namespace Modules\MobileApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffProfileController extends BaseApiController
{
    /**
     * Get staff profile details.
     * GET /api/v2/staff/profile
     */
    public function show(): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!$staffProfile) {
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

        if (!$staffProfile) {
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

        return $this->success(null, 'Profile updated successfully');
    }

    /**
     * Get commission plan details.
     * GET /api/v2/staff/commission
     */
    public function commission(): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!$staffProfile) {
            return $this->notFound();
        }

        if (!$this->hasPermission('commission.view_own')) {
            return $this->forbidden();
        }

        $commissionPlan = $staffProfile->commissionPlan;

        if (!$commissionPlan) {
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

        if (!$staffProfile) {
            return $this->notFound();
        }

        if (!$this->hasPermission('commission.view_own')) {
            return $this->forbidden();
        }

        if (!class_exists(\Modules\Staff\Models\CommissionRecord::class)) {
            return $this->success([]);
        }

        $records = \Modules\Staff\Models\CommissionRecord::where('staff_profile_id', $staffProfile->id)
            ->orderByDesc('created_at')
            ->paginate($this->getPerPage());

        return $this->paginated($records);
    }

    /**
     * Get pending commissions.
     * GET /api/v2/staff/commission/pending
     */
    public function commissionPending(): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!$staffProfile) {
            return $this->notFound();
        }

        if (!$this->hasPermission('commission.view_own')) {
            return $this->forbidden();
        }

        if (!class_exists(\Modules\Staff\Models\CommissionRecord::class)) {
            return $this->success([
                'total' => 0,
                'records' => [],
            ]);
        }

        $records = \Modules\Staff\Models\CommissionRecord::where('staff_profile_id', $staffProfile->id)
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get();

        return $this->success([
            'total' => $records->sum('amount'),
            'count' => $records->count(),
            'records' => $records->map(fn($r) => [
                'id' => $r->id,
                'amount' => $r->amount,
                'service_name' => $r->service_name ?? $r->service?->name,
                'appointment_date' => $r->appointment?->scheduled_at?->toDateString(),
                'created_at' => $r->created_at->toDateString(),
            ])->all(),
        ]);
    }

    /**
     * Get commission summary.
     */
    protected function getCommissionSummary($staffProfile): array
    {
        if (!class_exists(\Modules\Staff\Models\CommissionRecord::class)) {
            return [
                'this_month' => 0,
                'pending' => 0,
                'paid' => 0,
            ];
        }

        $thisMonth = \Modules\Staff\Models\CommissionRecord::where('staff_profile_id', $staffProfile->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        $pending = \Modules\Staff\Models\CommissionRecord::where('staff_profile_id', $staffProfile->id)
            ->where('status', 'pending')
            ->sum('amount');

        $paid = \Modules\Staff\Models\CommissionRecord::where('staff_profile_id', $staffProfile->id)
            ->where('status', 'paid')
            ->sum('amount');

        return [
            'this_month' => $thisMonth,
            'pending' => $pending,
            'paid' => $paid,
        ];
    }
}
