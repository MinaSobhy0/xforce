<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\Api\AuthController;
use Modules\Auth\Http\Controllers\Api\UserController;
use Modules\Auth\Http\Controllers\Api\RoleController;
use Modules\Auth\Http\Controllers\Api\ProfileController;
use Modules\Auth\Http\Controllers\Api\TwoFactorController;

/*
|--------------------------------------------------------------------------
| Auth Module API Routes
|--------------------------------------------------------------------------
|
| Here are the API routes for the Auth module. These routes handle
| API authentication, user management, and profile operations.
|
*/

Route::middleware(['api'])->prefix('v1')->name('api.v1.')->group(function () {

    // Public Authentication Routes
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.forgot');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.reset');

    // Email Verification
    Route::post('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('/email/resend', [AuthController::class, 'resendVerificationEmail'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // Two-Factor Authentication Challenge
    Route::post('/two-factor-challenge', [TwoFactorController::class, 'challenge'])
        ->name('two-factor.challenge');

    // Authenticated API Routes
    Route::middleware(['auth:sanctum'])->group(function () {

        // Authentication Management
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('logout.all');
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/refresh', [AuthController::class, 'refresh'])->name('refresh');

        // Profile Management
        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/', [ProfileController::class, 'show'])->name('show');
            Route::put('/', [ProfileController::class, 'update'])->name('update');
            Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');

            // Avatar Management
            Route::post('/avatar', [ProfileController::class, 'updateAvatar'])->name('avatar.update');
            Route::delete('/avatar', [ProfileController::class, 'deleteAvatar'])->name('avatar.delete');

            // Password Management
            Route::put('/password', [ProfileController::class, 'updatePassword'])->name('password.update');

            // Settings
            Route::get('/settings', [ProfileController::class, 'settings'])->name('settings');
            Route::put('/settings', [ProfileController::class, 'updateSettings'])->name('settings.update');
        });

        // Two-Factor Authentication Management
        Route::prefix('two-factor')->name('two-factor.')->group(function () {
            Route::get('/', [TwoFactorController::class, 'show'])->name('show');
            Route::post('/enable', [TwoFactorController::class, 'enable'])->name('enable');
            Route::post('/confirm', [TwoFactorController::class, 'confirm'])->name('confirm');
            Route::delete('/disable', [TwoFactorController::class, 'disable'])->name('disable');
            Route::get('/backup-codes', [TwoFactorController::class, 'getBackupCodes'])->name('backup-codes');
            Route::post('/backup-codes/regenerate', [TwoFactorController::class, 'regenerateBackupCodes'])
                ->name('backup-codes.regenerate');
        });

        // Session Management
        Route::prefix('sessions')->name('sessions.')->group(function () {
            Route::get('/', [AuthController::class, 'sessions'])->name('index');
            Route::delete('/{session}', [AuthController::class, 'deleteSession'])->name('destroy');
            Route::delete('/', [AuthController::class, 'deleteAllSessions'])->name('destroy.all');
        });

        // Admin API Routes
        Route::middleware(['role:super_admin,admin'])->prefix('admin')->name('admin.')->group(function () {

            // User Management API
            Route::apiResource('users', UserController::class);
            Route::prefix('users')->name('users.')->group(function () {
                Route::get('/search', [UserController::class, 'search'])->name('search');
                Route::get('/export', [UserController::class, 'export'])->name('export');

                Route::prefix('{user}')->group(function () {
                    Route::post('/activate', [UserController::class, 'activate'])->name('activate');
                    Route::post('/deactivate', [UserController::class, 'deactivate'])->name('deactivate');
                    Route::post('/verify-email', [UserController::class, 'verifyEmail'])->name('verify-email');
                    Route::post('/reset-password', [UserController::class, 'resetPassword'])->name('reset-password');
                    Route::post('/impersonate', [UserController::class, 'impersonate'])->name('impersonate');
                    Route::post('/disable-2fa', [UserController::class, 'disableTwoFactor'])->name('disable-2fa');
                    Route::get('/sessions', [UserController::class, 'sessions'])->name('sessions');
                    Route::delete('/sessions/{session}', [UserController::class, 'deleteSession'])->name('sessions.destroy');
                });

                // Bulk Operations
                Route::post('/bulk/activate', [UserController::class, 'bulkActivate'])->name('bulk.activate');
                Route::post('/bulk/deactivate', [UserController::class, 'bulkDeactivate'])->name('bulk.deactivate');
                Route::post('/bulk/delete', [UserController::class, 'bulkDelete'])->name('bulk.delete');
                Route::post('/bulk/assign-role', [UserController::class, 'bulkAssignRole'])->name('bulk.assign-role');
            });

            // Role Management API
            Route::apiResource('roles', RoleController::class);
            Route::prefix('roles')->name('roles.')->group(function () {
                Route::get('/search', [RoleController::class, 'search'])->name('search');
                Route::get('/permissions', [RoleController::class, 'permissions'])->name('permissions');

                Route::prefix('{role}')->group(function () {
                    Route::post('/duplicate', [RoleController::class, 'duplicate'])->name('duplicate');
                    Route::post('/activate', [RoleController::class, 'activate'])->name('activate');
                    Route::post('/deactivate', [RoleController::class, 'deactivate'])->name('deactivate');
                    Route::get('/users', [RoleController::class, 'users'])->name('users');
                });

                // Bulk Operations
                Route::post('/bulk/activate', [RoleController::class, 'bulkActivate'])->name('bulk.activate');
                Route::post('/bulk/deactivate', [RoleController::class, 'bulkDeactivate'])->name('bulk.deactivate');
                Route::post('/bulk/delete', [RoleController::class, 'bulkDelete'])->name('bulk.delete');
            });

            // Permission Management
            Route::prefix('permissions')->name('permissions.')->group(function () {
                Route::get('/', [RoleController::class, 'allPermissions'])->name('index');
                Route::get('/by-module', [RoleController::class, 'permissionsByModule'])->name('by-module');
            });

            // Statistics and Reports
            Route::prefix('stats')->name('stats.')->group(function () {
                Route::get('/users', [UserController::class, 'stats'])->name('users');
                Route::get('/roles', [RoleController::class, 'stats'])->name('roles');
                Route::get('/login-activity', [AuthController::class, 'loginActivity'])->name('login-activity');
            });
        });

        // Impersonation Management
        Route::middleware(['role:super_admin,admin'])->group(function () {
            Route::post('/impersonate/{user}', [UserController::class, 'startImpersonation'])->name('impersonate.start');
            Route::delete('/impersonate', [UserController::class, 'stopImpersonation'])->name('impersonate.stop');
            Route::get('/impersonation/status', [UserController::class, 'impersonationStatus'])->name('impersonate.status');
        });
    });
});

// Tenant-specific Auth API routes
Route::middleware(['api', 'tenant', 'auth:sanctum'])->prefix('tenant/v1')->name('tenant.api.v1.')->group(function () {

    // Current user in tenant context
    Route::get('/me', function (Request $request) {
        $user = $request->user();
        $tenant = tenant();

        return response()->json([
            'user' => $user->load(['roles', 'permissions']),
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'features' => $tenant->features,
            ],
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'roles' => $user->roles->pluck('name'),
        ]);
    })->name('me');

    // Tenant-specific user management (for tenant admins)
    Route::middleware(['permission:users.view'])->group(function () {
        Route::get('/users', [UserController::class, 'tenantUsers'])->name('users.index');
        Route::get('/users/{user}', [UserController::class, 'showTenantUser'])->name('users.show');

        Route::middleware(['permission:users.create,users.edit'])->group(function () {
            Route::post('/users', [UserController::class, 'storeTenantUser'])->name('users.store');
            Route::put('/users/{user}', [UserController::class, 'updateTenantUser'])->name('users.update');
        });
    });

    // Tenant-specific role management
    Route::middleware(['permission:roles.view'])->group(function () {
        Route::get('/roles', [RoleController::class, 'tenantRoles'])->name('roles.index');
        Route::get('/roles/{role}', [RoleController::class, 'showTenantRole'])->name('roles.show');
    });
});