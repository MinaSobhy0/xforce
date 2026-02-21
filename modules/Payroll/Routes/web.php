<?php

use Illuminate\Support\Facades\Route;
use Modules\Payroll\Models\PayrollLine;
use Modules\Payroll\Services\SalarySlipPdfService;

// Payslip download routes
Route::prefix('payroll')->name('payroll.')->group(function () {
    Route::get('/payslip/{id}/download', function (string $id) {
        // Check if user is authenticated via session
        if (!auth()->check()) {
            abort(403, 'Unauthorized');
        }

        $payrollLine = PayrollLine::findOrFail($id);
        $service = app(SalarySlipPdfService::class);
        return $service->download($payrollLine);
    })->name('payslip.download');

    Route::get('/payslip/{id}/view', function (string $id) {
        if (!auth()->check()) {
            abort(403, 'Unauthorized');
        }

        $payrollLine = PayrollLine::findOrFail($id);
        $service = app(SalarySlipPdfService::class);
        return $service->stream($payrollLine);
    })->name('payslip.view');
});
