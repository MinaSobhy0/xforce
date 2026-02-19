<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Patients Module Web Routes
|--------------------------------------------------------------------------
|
| Web routes are handled primarily by Filament resources.
| Add custom web routes here if needed.
|
*/

Route::middleware(['web', 'auth'])->prefix('patients')->name('patients.')->group(function () {
    // Custom web routes can be added here
});
