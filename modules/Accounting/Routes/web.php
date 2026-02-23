<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\JournalEntryPrintController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth', 'tenant'])
    ->prefix('accounting')
    ->name('accounting.')
    ->group(function () {
        Route::get('journal-entry/{journalEntry}/print', JournalEntryPrintController::class)
            ->name('journal-entry.print');
    });
