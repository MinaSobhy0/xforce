<?php

use Illuminate\Support\Facades\Route;
use Modules\Prescriptions\Services\PrescriptionPdfService;
use Modules\Prescriptions\Models\Prescription;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'tenant'])
    ->prefix('prescriptions')
    ->name('prescriptions.')
    ->group(function () {
        // Print prescription PDF
        Route::get('/{prescription}/print', function (Prescription $prescription) {
            // Mark as printed when actually generating the PDF
            $prescription->markPrinted();

            return app(PrescriptionPdfService::class)->stream($prescription);
        })->name('print');

        // Download prescription PDF
        Route::get('/{prescription}/download', function (Prescription $prescription) {
            // Mark as printed when actually generating the PDF
            $prescription->markPrinted();

            return app(PrescriptionPdfService::class)->download($prescription);
        })->name('download');
    });
