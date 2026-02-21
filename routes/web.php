<?php

use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/contact', [ContactController::class, 'submit'])->name('contact.submit');

// Two-Factor Authentication Routes
Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])
    ->name('two-factor.challenge');

// Backup Download Route (protected by auth)
Route::get('/admin/backups/{backup}/download', [BackupController::class, 'download'])
    ->middleware(['auth'])
    ->name('admin.backups.download');

