<?php

namespace Modules\MobileApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Attendance\Models\AttendanceViolation;
use Modules\Booking\Models\PractitionerTimeOff;
use Modules\Staff\Models\StaffProfile;

/**
 * Manager-side approval endpoints. Each authenticated user sees only the
 * records routed to them by the staff_profiles configuration:
 *
 *   - `staff_profiles.time_off_approver_user_id`     → time-off requests
 *   - `staff_profiles.attendance_approver_user_id`   → attendance violations
 *
 * Holders of the `practitioner_time_off.approve_any` /
 * `attendance_violations.approve_any` override permissions see every
 * pending record across the tenant.
 *
 * Approve / reject (and violation waive) calls go through the same model
 * methods the web admin panel uses, so the side effects (allocation
 * deductions, audit, requester-side push notifications) are identical.
 *
 * Note (xforce): PractitionerTimeOff links via `staff_profile_id`
 * directly (not `user_id` like xlinic), so the scope query joins via
 * staff_profile_id.
 */
class ApprovalsController extends BaseApiController
{
    /**
     * GET /api/v2/approvals/counts
     *
     * Lightweight payload for tab badges on the mobile shell.
     */
    public function counts(): JsonResponse
    {
        $this->ensureStaff();

        return $this->success([
            'time_off'   => $this->scopeTimeOff(PractitionerTimeOff::query())
                ->where('status', PractitionerTimeOff::STATUS_PENDING)
                ->count(),
            'violations' => $this->scopeViolations(AttendanceViolation::query())
                ->where('status', AttendanceViolation::STATUS_PENDING)
                ->count(),
        ]);
    }

    // ------------------------------------------------------------------
    //  Time-off
    // ------------------------------------------------------------------

    /**
     * GET /api/v2/approvals/time-off
     *
     * Paginated list. Default filter is `status=pending`; pass
     * `?status=approved|rejected|all` to see other states. Always
     * scoped to records this user is allowed to act on.
     */
    public function timeOffIndex(Request $request): JsonResponse
    {
        $this->ensureStaff();

        $query = PractitionerTimeOff::with(['staffProfile.user', 'timeOffType', 'approvedBy'])
            ->orderByDesc('start_date');

        $status = $request->input('status', PractitionerTimeOff::STATUS_PENDING);
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $query = $this->scopeTimeOff($query);

        $paginator = $query->paginate($this->getPerPage());

        return response()->json([
            'success' => true,
            'data' => collect($paginator->items())
                ->map(fn (PractitionerTimeOff $r) => $this->formatTimeOff($r))
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /**
     * GET /api/v2/approvals/time-off/{id}
     */
    public function timeOffShow(int $id): JsonResponse
    {
        $this->ensureStaff();

        $timeOff = PractitionerTimeOff::with(['staffProfile.user', 'timeOffType', 'approvedBy'])->find($id);
        if (! $timeOff) {
            return $this->notFound();
        }

        if (! $this->canActOnTimeOff($timeOff)) {
            return $this->forbidden(__('mobile_api::mobile.approvals.not_authorized'));
        }

        return $this->success($this->formatTimeOff($timeOff, detailed: true));
    }

    /**
     * POST /api/v2/approvals/time-off/{id}/approve
     */
    public function timeOffApprove(int $id): JsonResponse
    {
        $this->ensureStaff();

        $timeOff = PractitionerTimeOff::find($id);
        if (! $timeOff) {
            return $this->notFound();
        }

        if (! $this->canActOnTimeOff($timeOff)) {
            return $this->forbidden(__('mobile_api::mobile.approvals.not_authorized'));
        }

        if (! $timeOff->isPending()) {
            return $this->error(__('mobile_api::mobile.approvals.time_off_not_pending'), 422);
        }

        if (! $timeOff->approve((string) $this->user()->id)) {
            return $this->error(__('mobile_api::mobile.approvals.action_failed'), 422);
        }

        return $this->success(
            $this->formatTimeOff($timeOff->fresh(['staffProfile.user', 'timeOffType', 'approvedBy']), detailed: true),
            __('mobile_api::mobile.approvals.time_off_approved')
        );
    }

    /**
     * POST /api/v2/approvals/time-off/{id}/reject
     */
    public function timeOffReject(Request $request, int $id): JsonResponse
    {
        $this->ensureStaff();

        $data = $request->validate([
            'notes' => 'required|string|max:1000',
        ]);

        $timeOff = PractitionerTimeOff::find($id);
        if (! $timeOff) {
            return $this->notFound();
        }

        if (! $this->canActOnTimeOff($timeOff)) {
            return $this->forbidden(__('mobile_api::mobile.approvals.not_authorized'));
        }

        if (! $timeOff->isPending()) {
            return $this->error(__('mobile_api::mobile.approvals.time_off_not_pending'), 422);
        }

        if (! $timeOff->reject((string) $this->user()->id, $data['notes'])) {
            return $this->error(__('mobile_api::mobile.approvals.action_failed'), 422);
        }

        return $this->success(
            $this->formatTimeOff($timeOff->fresh(['staffProfile.user', 'timeOffType', 'approvedBy']), detailed: true),
            __('mobile_api::mobile.approvals.time_off_rejected')
        );
    }

    // ------------------------------------------------------------------
    //  Attendance violations
    // ------------------------------------------------------------------

    /**
     * GET /api/v2/approvals/violations
     */
    public function violationsIndex(Request $request): JsonResponse
    {
        $this->ensureStaff();

        $query = AttendanceViolation::with(['staffProfile.user', 'rule'])
            ->orderByDesc('violation_date');

        $status = $request->input('status', AttendanceViolation::STATUS_PENDING);
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $query = $this->scopeViolations($query);

        $paginator = $query->paginate($this->getPerPage());

        return response()->json([
            'success' => true,
            'data' => collect($paginator->items())
                ->map(fn (AttendanceViolation $v) => $this->formatViolation($v))
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /**
     * GET /api/v2/approvals/violations/{id}
     */
    public function violationsShow(int $id): JsonResponse
    {
        $this->ensureStaff();

        $violation = AttendanceViolation::with(['staffProfile.user', 'rule', 'approvedBy', 'waivedBy'])->find($id);
        if (! $violation) {
            return $this->notFound();
        }

        if (! $this->canActOnViolation($violation)) {
            return $this->forbidden(__('mobile_api::mobile.approvals.not_authorized'));
        }

        return $this->success($this->formatViolation($violation, detailed: true));
    }

    /**
     * POST /api/v2/approvals/violations/{id}/approve
     */
    public function violationsApprove(Request $request, int $id): JsonResponse
    {
        $this->ensureStaff();

        $data = $request->validate([
            'manager_notes' => 'nullable|string|max:1000',
        ]);

        $violation = AttendanceViolation::find($id);
        if (! $violation) {
            return $this->notFound();
        }

        if (! $this->canActOnViolation($violation)) {
            return $this->forbidden(__('mobile_api::mobile.approvals.not_authorized'));
        }

        if (! $violation->canBeApproved()) {
            return $this->error(__('mobile_api::mobile.approvals.violation_not_actionable'), 422);
        }

        if (! $violation->approve($this->user(), $data['manager_notes'] ?? null)) {
            return $this->error(__('mobile_api::mobile.approvals.action_failed'), 422);
        }

        return $this->success(
            $this->formatViolation($violation->fresh(['staffProfile.user', 'rule', 'approvedBy', 'waivedBy']), detailed: true),
            __('mobile_api::mobile.approvals.violation_approved')
        );
    }

    /**
     * POST /api/v2/approvals/violations/{id}/waive
     */
    public function violationsWaive(Request $request, int $id): JsonResponse
    {
        $this->ensureStaff();

        $data = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $violation = AttendanceViolation::find($id);
        if (! $violation) {
            return $this->notFound();
        }

        if (! $this->canActOnViolation($violation)) {
            return $this->forbidden(__('mobile_api::mobile.approvals.not_authorized'));
        }

        if (! $violation->canBeWaived()) {
            return $this->error(__('mobile_api::mobile.approvals.violation_not_actionable'), 422);
        }

        if (! $violation->waive($this->user(), $data['reason'])) {
            return $this->error(__('mobile_api::mobile.approvals.action_failed'), 422);
        }

        return $this->success(
            $this->formatViolation($violation->fresh(['staffProfile.user', 'rule', 'approvedBy', 'waivedBy']), detailed: true),
            __('mobile_api::mobile.approvals.violation_waived')
        );
    }

    // ------------------------------------------------------------------
    //  Scoping + gates
    // ------------------------------------------------------------------

    /**
     * Limit a PractitionerTimeOff query to records this user is allowed
     * to act on. Note: xforce's PractitionerTimeOff links to staff
     * directly via `staff_profile_id`, not `user_id` like xlinic, so the
     * scope filter uses profile IDs.
     */
    protected function scopeTimeOff($query)
    {
        $actor = $this->user();

        if ($actor->can('practitioner_time_off.approve_any')) {
            return $query;
        }

        return $query->whereIn('staff_profile_id',
            StaffProfile::query()
                ->where('time_off_approver_user_id', $actor->id)
                ->pluck('id')
        );
    }

    /**
     * Per-record check used by show/approve/reject. Falls back to
     * "anyone with the resource permission can act" when no approver is
     * configured on the requester's staff profile — same convention as
     * the web admin panel.
     */
    protected function canActOnTimeOff(PractitionerTimeOff $record): bool
    {
        $actor = $this->user();
        if (! $actor) {
            return false;
        }

        if ($actor->can('practitioner_time_off.approve_any')) {
            return true;
        }

        $approver = $record->staffProfile?->timeOffApprover;
        if (! $approver) {
            return true;
        }

        return (int) $approver->id === (int) $actor->id;
    }

    protected function scopeViolations($query)
    {
        $actor = $this->user();

        if ($actor->can('attendance_violations.approve_any')) {
            return $query;
        }

        return $query->whereIn('staff_profile_id',
            StaffProfile::query()
                ->where('attendance_approver_user_id', $actor->id)
                ->pluck('id')
        );
    }

    protected function canActOnViolation(AttendanceViolation $record): bool
    {
        $actor = $this->user();
        if (! $actor) {
            return false;
        }

        if ($actor->can('attendance_violations.approve_any')) {
            return true;
        }

        $approver = $record->staffProfile?->attendanceApprover;
        if (! $approver) {
            return true;
        }

        return (int) $approver->id === (int) $actor->id;
    }

    /**
     * Block requests from users without a staff profile entirely —
     * approval is an HR/manager concept, not a patient or admin
     * convenience.
     */
    protected function ensureStaff(): void
    {
        $user = $this->user();
        if (! $user || ! $user->staffProfile) {
            abort(response()->json([
                'success' => false,
                'message' => __('mobile_api::mobile.auth.not_staff'),
            ], 403));
        }
    }

    // ------------------------------------------------------------------
    //  Formatters
    // ------------------------------------------------------------------

    protected function formatTimeOff(PractitionerTimeOff $timeOff, bool $detailed = false): array
    {
        $staff = $timeOff->staffProfile;
        $user  = $staff?->user;

        $data = [
            'id' => $timeOff->id,
            'requester' => [
                'staff_profile_id' => $staff?->id,
                'user_id'          => $user?->id,
                'name'             => $user?->full_name,
                'avatar'           => $user?->avatar_url,
                'job_title'        => $staff?->job_title,
            ],
            'type'         => $timeOff->timeOffType?->translated_name ?? $timeOff->type_label,
            'color'        => $timeOff->timeOffType?->color ?? 'gray',
            'start_date'   => optional($timeOff->start_date)->toDateString(),
            'end_date'     => optional($timeOff->end_date)->toDateString(),
            'is_full_day'  => $timeOff->is_full_day,
            'duration'     => $timeOff->display_duration,
            'status'       => $timeOff->status,
            'status_label' => $timeOff->status_label,
            'status_color' => $timeOff->status_color,
            'created_at'   => $timeOff->created_at?->toDateTimeString(),
        ];

        if ($detailed) {
            $data['start_time']       = $timeOff->start_time;
            $data['end_time']         = $timeOff->end_time;
            $data['reason']           = $timeOff->reason;
            $data['days_requested']   = $timeOff->days_requested ?? null;
            $data['hours_requested']  = $timeOff->hours_requested ?? null;
            $data['approved_by']      = $timeOff->approvedBy?->full_name;
            $data['approved_at']      = optional($timeOff->approved_at)->toDateTimeString();
            $data['notes']            = $timeOff->notes;
        }

        return $data;
    }

    protected function formatViolation(AttendanceViolation $v, bool $detailed = false): array
    {
        $staff = $v->staffProfile;
        $user  = $staff?->user;

        $data = [
            'id'           => $v->id,
            'staff' => [
                'id'        => $staff?->id,
                'user_id'   => $user?->id,
                'name'      => $user?->full_name,
                'avatar'    => $user?->avatar_url,
                'job_title' => $staff?->job_title,
            ],
            'date'         => optional($v->violation_date)->toDateString(),
            'type'         => $v->violation_type,
            'type_label'   => AttendanceViolation::TYPES[$v->violation_type] ?? $v->violation_type,
            'duration'     => $v->formatted_violation_duration,
            'penalty'      => $v->formatted_penalty,
            'status'       => $v->status,
            'status_label' => AttendanceViolation::STATUSES[$v->status] ?? $v->status,
            'can_approve'  => $v->canBeApproved(),
            'can_waive'    => $v->canBeWaived(),
            'created_at'   => $v->created_at?->toDateTimeString(),
        ];

        if ($detailed) {
            $data['rule_name']       = $v->rule?->name;
            $data['manager_notes']   = $v->manager_notes;
            $data['waived_reason']   = $v->waived_reason;
            $data['dispute_reason']  = $v->dispute_reason;
            $data['approved_by']     = $v->approvedBy?->full_name;
            $data['approved_at']     = optional($v->approved_at)->toDateTimeString();
            $data['waived_by']       = $v->waivedBy?->full_name;
        }

        return $data;
    }
}
