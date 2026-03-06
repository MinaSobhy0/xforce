<?php

use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\TenantMediaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Define login route for auth middleware redirect
Route::get('/login', function () {
    return redirect()->route('filament.tenant.auth.login');
})->name('login');

Route::post('/contact', [ContactController::class, 'submit'])->name('contact.submit');

// Two-Factor Authentication Routes
Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])
    ->name('two-factor.challenge');

// Backup Download Route (protected by auth)
Route::get('/admin/backups/{backup}/download', [BackupController::class, 'download'])
    ->middleware(['auth'])
    ->name('admin.backups.download');

// Tenant Storage Route - serves files from tenant-specific storage
// Tenant is identified via subdomain, requires authentication
Route::get('/tenant-storage/{path}', [TenantMediaController::class, 'show'])
    ->middleware(['auth'])
    ->where('path', '.*')
    ->name('tenant.storage');

