<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Patient Portal Web Routes
|--------------------------------------------------------------------------
|
| Routes are handled by Filament Panel at /portal
| Additional API routes can be added here if needed
|
*/

// Logout route for patient portal
Route::post('/portal/logout', function () {
    auth('patient')->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/portal/login');
})->name('portal.logout')->middleware('web');

// Dummy route for email verification (we don't use email verification in portal)
Route::get('/portal/email-verification', function () {
    return redirect('/portal');
})->name('filament.portal.auth.email-verification.prompt')->middleware('web');
