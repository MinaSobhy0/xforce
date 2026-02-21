<?php

use Illuminate\Support\Facades\Route;
use Modules\Payroll\Models\PayrollLine;
use Modules\Payroll\Services\SalarySlipPdfService;

// Payslip download routes (using signed URLs for security)
Route::middleware('signed')->prefix('payroll')->name('payroll.')->group(function () {
    Route::get('/payslip/{payrollLine}/download', function (PayrollLine $payrollLine) {
        $service = app(SalarySlipPdfService::class);
        return $service->download($payrollLine);
    })->name('payslip.download');

    Route::get('/payslip/{payrollLine}/view', function (PayrollLine $payrollLine) {
        $service = app(SalarySlipPdfService::class);
        return $service->stream($payrollLine);
    })->name('payslip.view');
});
