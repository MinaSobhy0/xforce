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

        // Generate download URL
        $downloadUrl = url("/payroll/payslip/{$id}/download");

        return $this->success([
            'download_url' => $downloadUrl,
            'filename' => "payslip_{$payslip->payrollRun->period_label}.pdf",
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

        $data = [
            'id' => $payslip->id,
            'period' => $run->period_label,
            'period_year' => $run->period_year,
            'period_month' => $run->period_month,
            'status' => $run->status,
            'status_label' => PayrollRun::STATUSES[$run->status] ?? $run->status,

            // Summary
            'gross_salary' => $payslip->gross_salary_minor / 100,
            'total_deductions' => $payslip->total_deductions_minor / 100,
            'net_salary' => $payslip->net_salary,
        ];

        if ($detailed) {
            $data['earnings'] = [
                'base_salary' => $payslip->base_salary,
                'allowances' => $payslip->allowances,
                'commissions' => $payslip->commissions,
                'bonuses' => $payslip->bonuses,
            ];

            $data['deductions'] = [
                'tax' => $payslip->tax,
                'social_insurance' => $payslip->social_insurance,
                'other' => $payslip->deductions,
            ];

            // Include rule breakdown if available
            if (!empty($payslip->rule_amounts_json)) {
                $data['rule_breakdown'] = $payslip->rule_amounts_json;
            }

            $data['notes'] = $payslip->notes;
            $data['created_at'] = $payslip->created_at->toDateTimeString();
        }

        return $data;
    }
}
