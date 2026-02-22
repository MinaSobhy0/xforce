<?php

namespace Modules\Payroll\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Payroll\Models\PayrollLine;
use Modules\Payroll\Models\PayrollRun;
use Modules\Core\Models\Tenant;

class SalarySlipPdfService
{
    /**
     * Generate PDF for a single payroll line (salary slip).
     */
    public function generate(PayrollLine $line): \Barryvdh\DomPDF\PDF
    {
        $line->load(['payrollRun', 'staffProfile.user', 'staffProfile.branch']);

        $data = $this->prepareData($line);

        return Pdf::loadView('payroll::pdf.salary-slip', $data)
            ->setPaper('a4')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);
    }

    /**
     * Generate and download the salary slip.
     */
    public function download(PayrollLine $line): \Illuminate\Http\Response
    {
        $pdf = $this->generate($line);
        $filename = $this->getFilename($line);

        return $pdf->download($filename);
    }

    /**
     * Generate and stream the salary slip.
     */
    public function stream(PayrollLine $line): \Illuminate\Http\Response
    {
        $pdf = $this->generate($line);
        $filename = $this->getFilename($line);

        return $pdf->stream($filename);
    }

    /**
     * Generate PDF for all payroll lines in a run.
     */
    public function generateBulk(PayrollRun $payrollRun): \Barryvdh\DomPDF\PDF
    {
        $payrollRun->load(['lines.staffProfile.user', 'lines.staffProfile.branch']);

        $slips = [];
        foreach ($payrollRun->lines as $line) {
            $slips[] = $this->prepareData($line);
        }

        return Pdf::loadView('payroll::pdf.salary-slip-bulk', [
            'slips' => $slips,
            'payrollRun' => $payrollRun,
        ])
            ->setPaper('a4')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);
    }

    /**
     * Prepare data for the PDF view.
     */
    protected function prepareData(PayrollLine $line): array
    {
        $payrollRun = $line->payrollRun;
        $staff = $line->staffProfile;
        $user = $staff->user ?? null;

        // Get tenant for company info
        $tenant = Tenant::find($line->tenant_id);

        // Parse bonus and deduction details
        $bonusDetails = $this->parseBonusDetails($line->bonus_details_json ?? []);
        $deductionDetails = $this->parseDeductionDetails($line->deduction_details_json ?? []);

        return [
            'line' => $line,
            'payrollRun' => $payrollRun,
            'staff' => $staff,
            'user' => $user,
            'tenant' => $tenant,
            'period' => $payrollRun->period_label,
            'periodMonth' => $payrollRun->period_month,
            'periodYear' => $payrollRun->period_year,

            // Earnings
            'baseSalary' => $line->base_salary,
            'commissions' => $line->commissions,
            'bonuses' => $line->bonuses,
            'bonusDetails' => $bonusDetails,
            'grossSalary' => $line->gross_salary_minor / 100,

            // Deductions
            'tax' => $line->tax,
            'socialInsurance' => $line->social_insurance,
            'otherDeductions' => $line->deductions,
            'deductionDetails' => $deductionDetails,
            'totalDeductions' => $line->total_deductions_minor / 100,

            // Net
            'netSalary' => $line->net_salary,

            // Meta
            'generatedAt' => now(),
            'currency' => current_currency(),
            'locale' => app()->getLocale(),
            'isRtl' => app()->getLocale() === 'ar',
        ];
    }

    /**
     * Parse bonus details array.
     */
    protected function parseBonusDetails(array $details): array
    {
        if (empty($details)) {
            return [];
        }

        return collect($details)->map(function ($item) {
            return [
                'description' => $item['description'] ?? __('payroll::payroll.bonus'),
                'amount' => ($item['amount_minor'] ?? 0) / 100,
            ];
        })->toArray();
    }

    /**
     * Parse deduction details array.
     */
    protected function parseDeductionDetails(array $details): array
    {
        if (empty($details)) {
            return [];
        }

        return collect($details)->map(function ($item) {
            return [
                'description' => $item['description'] ?? __('payroll::payroll.deduction'),
                'amount' => ($item['amount_minor'] ?? 0) / 100,
            ];
        })->toArray();
    }

    /**
     * Get filename for the salary slip.
     */
    protected function getFilename(PayrollLine $line): string
    {
        $staff = $line->staffProfile;
        $payrollRun = $line->payrollRun;

        $staffName = $staff->user?->name ?? $staff->id;
        $period = sprintf('%04d-%02d', $payrollRun->period_year, $payrollRun->period_month);

        // Sanitize filename
        $staffName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $staffName);

        return "salary-slip-{$staffName}-{$period}.pdf";
    }
}
