<?php

namespace Modules\MobileApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends BaseApiController
{
    /**
     * Get current payroll period.
     * GET /api/v2/payroll/current
     */
    public function current(): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!class_exists(\Modules\Payroll\Models\PayrollRecord::class)) {
            return $this->error('Payroll module not available', 503);
        }

        $currentPeriod = \Modules\Payroll\Models\PayrollRecord::where('staff_profile_id', $staffProfile->id)
            ->whereMonth('period_start', now()->month)
            ->whereYear('period_start', now()->year)
            ->first();

        if (!$currentPeriod) {
            return $this->success([
                'status' => 'not_processed',
                'message' => __('mobile_api::mobile.payroll.no_payslip'),
            ]);
        }

        return $this->success($this->formatPayrollRecord($currentPeriod));
    }

    /**
     * Get payroll history.
     * GET /api/v2/payroll/history
     */
    public function history(Request $request): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!class_exists(\Modules\Payroll\Models\PayrollRecord::class)) {
            return $this->success([]);
        }

        $records = \Modules\Payroll\Models\PayrollRecord::where('staff_profile_id', $staffProfile->id)
            ->orderByDesc('period_start')
            ->paginate($this->getPerPage());

        $formatted = collect($records->items())->map(fn($r) => [
            'id' => $r->id,
            'period' => $r->period_start->format('F Y'),
            'net_salary' => $r->net_salary,
            'status' => $r->status,
            'paid_at' => $r->paid_at?->toDateString(),
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
     * Get payroll record details.
     * GET /api/v2/payroll/{id}
     */
    public function show(int $id): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!class_exists(\Modules\Payroll\Models\PayrollRecord::class)) {
            return $this->notFound();
        }

        $record = \Modules\Payroll\Models\PayrollRecord::where('staff_profile_id', $staffProfile->id)
            ->find($id);

        if (!$record) {
            return $this->notFound();
        }

        return $this->success($this->formatPayrollRecord($record));
    }

    /**
     * Download payslip PDF.
     * GET /api/v2/payroll/{id}/payslip
     */
    public function payslip(int $id): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!class_exists(\Modules\Payroll\Models\PayrollRecord::class)) {
            return $this->notFound();
        }

        $record = \Modules\Payroll\Models\PayrollRecord::where('staff_profile_id', $staffProfile->id)
            ->find($id);

        if (!$record) {
            return $this->notFound();
        }

        // Generate PDF URL (this would typically be a signed URL)
        $pdfUrl = route('api.v2.payroll.payslip.download', ['id' => $id]);

        return $this->success([
            'download_url' => $pdfUrl,
            'expires_in' => 3600, // 1 hour
        ], __('mobile_api::mobile.payroll.payslip_downloaded'));
    }

    /**
     * Get salary structure.
     * GET /api/v2/payroll/salary-structure
     */
    public function salaryStructure(): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!class_exists(\Modules\Payroll\Models\SalaryStructure::class)) {
            return $this->success([
                'base_salary' => $staffProfile->base_salary ?? 0,
            ]);
        }

        $structure = \Modules\Payroll\Models\SalaryStructure::where('staff_profile_id', $staffProfile->id)
            ->where('is_active', true)
            ->first();

        if (!$structure) {
            return $this->success([
                'base_salary' => $staffProfile->base_salary ?? 0,
            ]);
        }

        return $this->success([
            'base_salary' => $structure->base_salary,
            'allowances' => $structure->allowances ?? [],
            'deductions' => $structure->deductions ?? [],
            'effective_from' => $structure->effective_from?->toDateString(),
        ]);
    }

    /**
     * Get deductions breakdown.
     * GET /api/v2/payroll/deductions
     */
    public function deductions(Request $request): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!class_exists(\Modules\Payroll\Models\PayrollRecord::class)) {
            return $this->success([]);
        }

        $month = $request->month ?? now()->month;
        $year = $request->year ?? now()->year;

        $record = \Modules\Payroll\Models\PayrollRecord::where('staff_profile_id', $staffProfile->id)
            ->whereMonth('period_start', $month)
            ->whereYear('period_start', $year)
            ->first();

        if (!$record) {
            return $this->success([]);
        }

        return $this->success([
            'period' => \Carbon\Carbon::create($year, $month)->format('F Y'),
            'items' => $record->deductions ?? [],
            'total' => $record->total_deductions ?? 0,
        ]);
    }

    /**
     * Format payroll record for response.
     */
    protected function formatPayrollRecord($record): array
    {
        return [
            'id' => $record->id,
            'period' => $record->period_start->format('F Y'),
            'period_start' => $record->period_start->toDateString(),
            'period_end' => $record->period_end->toDateString(),
            'status' => $record->status,

            // Earnings
            'base_salary' => $record->base_salary,
            'allowances' => $record->allowances ?? [],
            'total_allowances' => $record->total_allowances ?? 0,
            'commissions' => $record->commissions ?? 0,
            'bonuses' => $record->bonuses ?? 0,
            'gross_salary' => $record->gross_salary,

            // Deductions
            'deductions' => $record->deductions ?? [],
            'total_deductions' => $record->total_deductions ?? 0,
            'tax' => $record->tax ?? 0,
            'social_insurance' => $record->social_insurance ?? 0,

            // Net
            'net_salary' => $record->net_salary,

            // Payment info
            'paid_at' => $record->paid_at?->toDateString(),
            'payment_method' => $record->payment_method,
        ];
    }
}
