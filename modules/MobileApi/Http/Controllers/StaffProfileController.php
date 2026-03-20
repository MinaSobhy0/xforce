<?php

namespace Modules\MobileApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        if (!class_exists(StaffCommissionRecord::class)) {
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

        $formatted = collect($records->items())->map(fn($r) => [
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

        if (!$staffProfile) {
            return $this->notFound();
        }

        if (!$this->hasPermission('commission.view_own')) {
            return $this->forbidden();
        }

        if (!class_exists(StaffCommissionRecord::class)) {
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
            'records' => $records->map(fn($r) => [
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
        if (!class_exists(StaffCommissionRecord::class)) {
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
