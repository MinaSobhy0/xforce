<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Reporting module web routes.
| Report pages are registered through Filament Panel provider.
|
*/

Route::prefix('reports')->middleware(['web', 'auth'])->group(function () {
    // Export routes could be added here if needed
});
