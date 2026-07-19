<?php

namespace Modules\MobileApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payroll\Models\PayrollLine;

class PayrollController extends BaseApiController
{
    /**
     * Get current month's payslip.
     * GET /api/v2/payroll/current
     */
    public function current(): JsonResponse
    {
        $user = $this->user();
        $staffProfile = $this->staffProfile();

        if (!$staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        if (!class_exists(PayrollLine::class)) {
            return $this->error('Payroll module not available', 503);
        }

        $payslip = PayrollLine::with('payrollRun')
            ->where('staff_profile_id', $staffProfile->id)
            ->whereHas('payrollRun', function ($q) {
                $q->where('period_year', now()->year)
                  ->where('period_month', now()->month);
            })
            ->orderByDesc('created_at')
            ->first();

        if (!$payslip) {
            return $this->success([
                'status' => 'not_processed',
                'message' => __('mobile_api::mobile.payroll.no_payslip'),
            ]);
        }

        return $this->success($this->formatPayslip($payslip));
    }

    /**
     * Get payslip history.
     * GET /api/v2/payroll/history
     */
    public function history(Request $request): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!$staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        if (!class_exists(PayrollLine::class)) {
            return $this->success([]);
        }

        $query = PayrollLine::with('payrollRun')
            ->where('staff_profile_id', $staffProfile->id)
            ->whereIn('status', [PayrollLine::STATUS_APPROVED, PayrollLine::STATUS_PAID]);

        if ($request->filled('year')) {
            $query->whereHas('payrollRun', function ($q) use ($request) {
                $q->where('period_year', $request->year);
            });
        }

        $payslips = $query->orderByDesc('created_at')
            ->paginate($this->getPerPage());

        $formatted = collect($payslips->items())->map(fn($p) => [
            'id' => $p->id,
            'period' => $p->payrollRun?->period_label,
            'period_year' => $p->payrollRun?->period_year,
            'period_month' => $p->payrollRun?->period_month,
            'net_salary' => $p->net_salary,
            'status' => $p->status,
            'status_label' => PayrollLine::STATUSES[$p->status] ?? $p->status,
        ]);

        return response()->json([
            'success' => true,
            'data' => $formatted,
            'meta' => [
                'current_page' => $payslips->currentPage(),
                'last_page' => $payslips->lastPage(),
                'per_page' => $payslips->perPage(),
                'total' => $payslips->total(),
            ],
        ]);
    }

    /**
     * Get payslip details.
     * GET /api/v2/payroll/{id}
     */
    public function show(int $id): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!$staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        if (!class_exists(PayrollLine::class)) {
            return $this->notFound();
        }

        $payslip = PayrollLine::with('payrollRun')
            ->where('staff_profile_id', $staffProfile->id)
            ->find($id);

        if (!$payslip) {
            return $this->notFound();
        }

        return $this->success($this->formatPayslip($payslip, true));
    }

    /**
     * Get payslip download URL.
     * GET /api/v2/payroll/{id}/download
     * SECURITY: Returns a signed URL to prevent IDOR attacks
     */
    public function download(int $id): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!$staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        if (!class_exists(PayrollLine::class)) {
            return $this->notFound();
        }

        $payslip = PayrollLine::where('staff_profile_id', $staffProfile->id)->find($id);

        if (!$payslip) {
            return $this->notFound();
        }

        // SECURITY: Generate signed URL with short expiry to prevent URL sharing/manipulation
        $downloadUrl = \URL::temporarySignedRoute(
            'payroll.download',
            now()->addMinutes(15),
            ['id' => $id, 'staff' => $staffProfile->id]
        );

        $filenamePeriod = $payslip->payrollRun?->period_label ?? "slip-{$payslip->id}";

        return $this->success([
            'download_url' => $downloadUrl,
            'filename' => "payslip_{$filenamePeriod}.pdf",
            'expires_in' => 900,
        ]);
    }

    /**
     * Get salary structure for the staff member.
     * GET /api/v2/payroll/salary-structure
     */
    public function salaryStructure(): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!$staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        // Get active salary structure
        if (class_exists(\Modules\Payroll\Models\EmployeeSalaryStructure::class)) {
            $structure = \Modules\Payroll\Models\EmployeeSalaryStructure::with('salaryStructure')
                ->where('staff_profile_id', $staffProfile->id)
                ->where('is_active', true)
                ->first();

            if ($structure) {
                return $this->success([
                    'structure_name' => $structure->salaryStructure?->name,
                    'base_salary' => $staffProfile->base_salary_minor / 100,
                    'effective_from' => $structure->effective_from?->toDateString(),
                ]);
            }
        }

        return $this->success([
            'base_salary' => $staffProfile->base_salary_minor / 100,
        ]);
    }

    /**
     * Get yearly summary.
     * GET /api/v2/payroll/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!$staffProfile) {
            return $this->forbidden(__('mobile_api::mobile.auth.not_staff'));
        }

        if (!class_exists(PayrollLine::class)) {
            return $this->success([]);
        }

        $year = $request->integer('year', now()->year);

        $payslips = PayrollLine::with('payrollRun')
            ->where('staff_profile_id', $staffProfile->id)
            ->whereIn('status', [PayrollLine::STATUS_APPROVED, PayrollLine::STATUS_PAID])
            ->whereHas('payrollRun', fn ($q) => $q->where('period_year', $year))
            ->get();

        return $this->success([
            'year' => $year,
            'total_gross' => $payslips->sum('gross_salary_minor') / 100,
            'total_net' => $payslips->sum('net_salary_minor') / 100,
            'total_tax' => $payslips->sum('tax_minor') / 100,
            'total_deductions' => $payslips->sum('total_deductions_minor') / 100,
            'total_commissions' => $payslips->sum('commissions_minor') / 100,
            'total_bonuses' => $payslips->sum('bonuses_minor') / 100,
            'payslips_count' => $payslips->count(),
        ]);
    }

    /**
     * Format payslip for response.
     *
     * Minimal view by default: employees see only allowances, deductions,
     * and net salary. Every other line (gross, base salary, commissions,
     * bonuses, tax/social-insurance breakdown, rule breakdown, notes) is
     * gated on a tenant flag — a clinic that wants a more transparent
     * payslip flips the flag on in tenants.settings.payslip_display.
     */
    protected function formatPayslip(PayrollLine $payslip, bool $detailed = false): array
    {
        $run = $payslip->payrollRun;
        $display = static::payslipDisplayConfig();

        // Always emitted: metadata + the three numbers HR agreed employees
        // may see (allowances, deductions total, net).
        $data = [
            'id' => $payslip->id,
            'period' => $run?->period_label,
            'period_year' => $run?->period_year,
            'period_month' => $run?->period_month,
            'status' => $payslip->status,
            'status_label' => PayrollLine::STATUSES[$payslip->status] ?? $payslip->status,

            'allowances' => (float) $payslip->allowances,
            'deductions' => $payslip->total_deductions_minor / 100,
            'net_salary' => (float) $payslip->net_salary,
        ];

        // Optional lines — each gated on tenant config, off by default.
        if ($display['show_gross_salary']) {
            $data['gross_salary'] = $payslip->gross_salary_minor / 100;
        }

        if ($detailed) {
            if ($display['show_earnings_breakdown']) {
                $data['earnings'] = [
                    'base_salary' => $payslip->base_salary,
                    'allowances' => $payslip->allowances,
                    'commissions' => $payslip->commissions,
                    'bonuses' => $payslip->bonuses,
                ];
            }

            if ($display['show_deductions_breakdown']) {
                $data['deductions_breakdown'] = [
                    'tax' => $payslip->tax,
                    'social_insurance' => $payslip->social_insurance,
                    'other' => $payslip->deductions,
                ];
            }

            if ($display['show_rule_breakdown'] && ! empty($payslip->rule_amounts_json)) {
                $data['rule_breakdown'] = $payslip->rule_amounts_json;
            }

            if ($display['show_notes']) {
                $data['notes'] = $payslip->notes;
            }

            $data['created_at'] = $payslip->created_at->toDateTimeString();
        }

        return $data;
    }

    /**
     * Tenant-configurable payslip display flags. Read from
     * tenants.settings.payslip_display via Tenant::getSetting().
     *
     * Defaults ship a minimal payslip (allowances + deductions total + net
     * salary + status). Every "premium" line is opt-in per tenant:
     *   show_gross_salary        default false
     *   show_earnings_breakdown  default false  (base / allowances / commissions / bonuses)
     *   show_deductions_breakdown default false (tax / social_insurance / other)
     *   show_rule_breakdown      default false  (rule_amounts_json)
     *   show_notes               default false  (HR notes on the slip)
     *
     * Public + static so /api/v2/config can emit the same block without
     * duplicating the defaults.
     *
     * @return array{show_gross_salary:bool,show_earnings_breakdown:bool,show_deductions_breakdown:bool,show_rule_breakdown:bool,show_notes:bool}
     */
    public static function payslipDisplayConfig(): array
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $stored = $tenant ? ($tenant->getSetting('payslip_display', []) ?: []) : [];

        return [
            'show_gross_salary' => (bool) ($stored['show_gross_salary'] ?? false),
            'show_earnings_breakdown' => (bool) ($stored['show_earnings_breakdown']
                // back-compat with the previous flag name if a tenant already
                // wrote it before this rename shipped
                ?? $stored['show_allowances_breakdown']
                ?? false),
            'show_deductions_breakdown' => (bool) ($stored['show_deductions_breakdown'] ?? false),
            'show_rule_breakdown' => (bool) ($stored['show_rule_breakdown'] ?? false),
            'show_notes' => (bool) ($stored['show_notes'] ?? false),
        ];
    }
}
