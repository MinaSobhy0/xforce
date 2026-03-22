<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthController;
use Modules\Auth\Http\Controllers\ProfileController;
use Modules\Auth\Http\Controllers\TwoFactorController;
use Modules\Auth\Http\Controllers\UserController;
use Modules\Auth\Http\Controllers\RoleController;

/*
|--------------------------------------------------------------------------
| Auth Module Web Routes
|--------------------------------------------------------------------------
|
| Here are the web routes for the Auth module. These routes handle
| authentication, user management, roles, and profile management.
|
*/

// Authentication Routes
Route::middleware(['web', 'guest'])->group(function () {
    // Login Routes
    // SECURITY: Rate limit login attempts to prevent brute force attacks
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1'); // 5 attempts per minute

    // Registration Routes (if enabled)
    // SECURITY: Rate limit registration to prevent mass account creation
    Route::get('/register', [AuthController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:3,5'); // 3 attempts per 5 minutes

    // Password Reset Routes
    // SECURITY: Rate limit password reset to prevent enumeration and abuse
    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLinkEmail'])
        ->middleware('throttle:3,5')
        ->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:5,5')
        ->name('password.update');

    // Email Verification Notice
    Route::get('/email/verify', [AuthController::class, 'showVerifyEmailForm'])->name('verification.notice');
});

// Authenticated Routes
Route::middleware(['web', 'auth'])->group(function () {

    // Logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Email Verification
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('/email/verification-notification', [AuthController::class, 'sendVerificationEmail'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // Profile Management Routes
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'show'])->name('show');
        Route::get('/edit', [ProfileController::class, 'edit'])->name('edit');
        Route::put('/', [ProfileController::class, 'update'])->name('update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');

        // Avatar Management
        Route::post('/avatar', [ProfileController::class, 'updateAvatar'])->name('avatar.update');
        Route::delete('/avatar', [ProfileController::class, 'deleteAvatar'])->name('avatar.delete');

        // Password Change
        Route::put('/password', [ProfileController::class, 'updatePassword'])->name('password.update');
    });

    // Two-Factor Authentication Routes
    Route::prefix('two-factor')->name('two-factor.')->group(function () {
        Route::get('/', [TwoFactorController::class, 'show'])->name('show');
        Route::post('/enable', [TwoFactorController::class, 'enable'])->name('enable');
        Route::post('/confirm', [TwoFactorController::class, 'confirm'])->name('confirm');
        Route::post('/disable', [TwoFactorController::class, 'disable'])->name('disable');
        Route::get('/backup-codes', [TwoFactorController::class, 'showBackupCodes'])->name('backup-codes.show');
        Route::post('/backup-codes', [TwoFactorController::class, 'regenerateBackupCodes'])->name('backup-codes.regenerate');
    });

    // Two-Factor Challenge Routes
    // SECURITY: Rate limit 2FA challenge to prevent code enumeration
    Route::middleware(['guest'])->group(function () {
        Route::get('/two-factor-challenge', [TwoFactorController::class, 'showChallengeForm'])->name('two-factor.login');
        Route::post('/two-factor-challenge', [TwoFactorController::class, 'challenge'])
            ->middleware('throttle:5,1'); // 5 attempts per minute
    });

    // Admin Routes (Role-based access)
    Route::middleware(['role:super_admin,admin'])->prefix('admin')->name('admin.')->group(function () {

        // User Management Routes
        Route::resource('users', UserController::class);
        Route::prefix('users/{user}')->name('users.')->group(function () {
            Route::post('/activate', [UserController::class, 'activate'])->name('activate');
            Route::post('/deactivate', [UserController::class, 'deactivate'])->name('deactivate');
            Route::post('/verify-email', [UserController::class, 'verifyEmail'])->name('verify-email');
            Route::post('/reset-password', [UserController::class, 'resetPassword'])->name('reset-password');
            Route::post('/impersonate', [UserController::class, 'impersonate'])->name('impersonate');
            Route::post('/disable-2fa', [UserController::class, 'disableTwoFactor'])->name('disable-2fa');
        });

        // Role Management Routes
        Route::resource('roles', RoleController::class);
        Route::prefix('roles/{role}')->name('roles.')->group(function () {
            Route::post('/duplicate', [RoleController::class, 'duplicate'])->name('duplicate');
            Route::post('/activate', [RoleController::class, 'activate'])->name('activate');
            Route::post('/deactivate', [RoleController::class, 'deactivate'])->name('deactivate');
        });

        // Bulk Actions
        Route::prefix('bulk')->name('bulk.')->group(function () {
            Route::post('/users/activate', [UserController::class, 'bulkActivate'])->name('users.activate');
            Route::post('/users/deactivate', [UserController::class, 'bulkDeactivate'])->name('users.deactivate');
            Route::post('/users/delete', [UserController::class, 'bulkDelete'])->name('users.delete');
            Route::post('/roles/activate', [RoleController::class, 'bulkActivate'])->name('roles.activate');
            Route::post('/roles/deactivate', [RoleController::class, 'bulkDeactivate'])->name('roles.deactivate');
        });
    });

    // Impersonation Routes
    Route::middleware(['role:super_admin,admin'])->group(function () {
        Route::post('/impersonate/{user}', [UserController::class, 'impersonate'])->name('impersonate');
        Route::post('/leave-impersonation', [UserController::class, 'leaveImpersonation'])->name('impersonate.leave');
    });
});

// Password Change Enforcement
Route::middleware(['web', 'auth', 'password.change'])->group(function () {
    Route::get('/change-password', [AuthController::class, 'showChangePasswordForm'])->name('password.change');
    Route::post('/change-password', [AuthController::class, 'changePassword']);
});

// Account Lockout/Suspension Routes
Route::middleware(['web'])->group(function () {
    Route::get('/account-suspended', function () {
        return view('auth::account-suspended');
    })->name('account.suspended');

    Route::get('/account-locked', function () {
        return view('auth::account-locked');
    })->name('account.locked');
});