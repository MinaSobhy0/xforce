<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\ImpersonateController;

Route::middleware(['web'])->prefix('auth')->name('auth.')->group(function () {
    // Auth web routes
});

// Impersonation route (no auth required - uses token)
Route::middleware(['web'])->group(function () {
    Route::get('/admin/impersonate', [ImpersonateController::class, 'login'])->name('impersonate.login');
});
