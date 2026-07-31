<?php

namespace Modules\MobileApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payroll\Models\PayrollLine;
use Modules\Payroll\Models\PayrollRun;

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

        // Get the current month's payslip
        $payslip = PayrollLine::with('payrollRun')
            ->where('staff_profile_id', $staffProfile->id)
            ->whereHas('payrollRun', function ($q) {
                $q->where('period_year', now()->year)
                  ->where('period_month', now()->month);
            })
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
            ->whereHas('payrollRun', function ($q) {
                $q->whereIn('status', [PayrollRun::STATUS_APPROVED, PayrollRun::STATUS_PAID]);
            });

        // Filter by year if provided
        if ($request->filled('year')) {
            $query->whereHas('payrollRun', function ($q) use ($request) {
                $q->where('period_year', $request->year);
            });
        }

        $payslips = $query->orderByDesc('created_at')
            ->paginate($this->getPerPage());

        $formatted = collect($payslips->items())->map(fn($p) => [
            'id' => $p->id,
            'period' => $p->payrollRun->period_label,
            'period_year' => $p->payrollRun->period_year,
            'period_month' => $p->payrollRun->period_month,
            'net_salary' => $p->net_salary,
            'status' => $p->payrollRun->status,
            'status_label' => PayrollRun::STATUSES[$p->payrollRun->status] ?? $p->payrollRun->status,
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

        return $this->success([
            'download_url' => $downloadUrl,
            'filename' => "payslip_{$payslip->payrollRun->period_label}.pdf",
            'expires_in' => 900, // 15 minutes in seconds
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
            ->whereHas('payrollRun', function ($q) use ($year) {
                $q->where('period_year', $year)
                  ->whereIn('status', [PayrollRun::STATUS_APPROVED, PayrollRun::STATUS_PAID]);
            })
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
     */
    protected function formatPayslip(PayrollLine $payslip, bool $detailed = false): array
    {
        $run = $payslip->payrollRun;

        $display = static::payslipDisplayConfig();

        $data = [
            'id' => $payslip->id,
            'period' => $run->period_label,
            'period_year' => $run->period_year,
            'period_month' => $run->period_month,
            'status' => $run->status,
            'status_label' => PayrollRun::STATUSES[$run->status] ?? $run->status,

            // Summary — detailed breakdowns stay behind the tenant flags.
            'total_deductions' => $payslip->total_deductions_minor / 100,
            'net_salary' => $payslip->net_salary,
        ];

        // Tenant-configurable: default policy hides gross from employees.
        if ($display['show_gross_salary']) {
            $data['gross_salary'] = $payslip->gross_salary_minor / 100;
        }

        if ($detailed) {
            if ($display['show_allowances_breakdown']) {
                $data['earnings'] = [
                    'base_salary' => $payslip->base_salary,
                    'allowances' => $payslip->allowances,
                    'commissions' => $payslip->commissions,
                    'bonuses' => $payslip->bonuses,
                ];
            }

            if ($display['show_deductions_breakdown']) {
                $data['deductions'] = [
                    'tax' => $payslip->tax,
                    'social_insurance' => $payslip->social_insurance,
                    'other' => $payslip->deductions,
                ];
            }

            // Rule breakdown: only rules the employee is meant to see
            // (SalaryRule.appears_on_payslip), behind its own tenant flag.
            if ($display['show_rule_breakdown'] && !empty($payslip->rule_amounts_json)) {
                $breakdown = $this->visibleRuleBreakdown($payslip->rule_amounts_json);
                if (!empty($breakdown)) {
                    $data['rule_breakdown'] = $breakdown;
                }
            }

            $data['notes'] = $payslip->notes;
            $data['created_at'] = $payslip->created_at->toDateTimeString();
        }

        return $data;
    }

    /**
     * Tenant-configurable payslip display flags, read from
     * tenants.settings.payslip_display. Defaults hide gross salary while
     * keeping the breakdowns on.
     *
     * @return array{show_gross_salary:bool,show_allowances_breakdown:bool,show_deductions_breakdown:bool,show_rule_breakdown:bool}
     */
    public static function payslipDisplayConfig(): array
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $stored = $tenant ? ($tenant->getSetting('payslip_display', []) ?: []) : [];

        return [
            'show_gross_salary' => (bool) ($stored['show_gross_salary'] ?? false),
            'show_allowances_breakdown' => (bool) ($stored['show_allowances_breakdown'] ?? true),
            'show_deductions_breakdown' => (bool) ($stored['show_deductions_breakdown'] ?? true),
            'show_rule_breakdown' => (bool) ($stored['show_rule_breakdown'] ?? true),
        ];
    }

    /**
     * Filter a rule_amounts_json breakdown down to employee-visible entries.
     *
     * Entries may carry a 'visible' flag; entries without it fall back to
     * the local SalaryRule.appears_on_payslip column, defaulting to visible
     * when the rule can't be resolved. The internal flag is stripped from
     * the response.
     */
    protected function visibleRuleBreakdown(array $breakdown): array
    {
        $unresolvedIds = collect($breakdown)
            ->filter(fn($e) => !array_key_exists('visible', $e) && !empty($e['rule_id']))
            ->pluck('rule_id')
            ->unique()
            ->values();

        $localVisibility = [];
        if ($unresolvedIds->isNotEmpty() && class_exists(\Modules\Payroll\Models\SalaryRule::class)) {
            $localVisibility = \Modules\Payroll\Models\SalaryRule::whereIn('id', $unresolvedIds)
                ->pluck('appears_on_payslip', 'id')
                ->map(fn($v) => (bool) $v)
                ->all();
        }

        return collect($breakdown)
            ->filter(function ($entry) use ($localVisibility) {
                if (array_key_exists('visible', $entry)) {
                    return (bool) $entry['visible'];
                }

                $ruleId = $entry['rule_id'] ?? null;

                return $ruleId === null || ($localVisibility[$ruleId] ?? true);
            })
            ->map(function ($entry) {
                unset($entry['visible']);

                return $entry;
            })
            ->values()
            ->all();
    }
}
