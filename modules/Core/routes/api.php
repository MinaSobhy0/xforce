<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Api\TenantController;
use Modules\Core\Http\Controllers\Api\ModuleController;
use Modules\Core\Http\Controllers\Api\SystemController;

/*
|--------------------------------------------------------------------------
| Core Module API Routes
|--------------------------------------------------------------------------
|
| Here are the API routes for the Core module. These routes are loaded
| by the RouteServiceProvider within a group which is assigned the
| "api" middleware group and includes rate limiting and authentication.
|
*/

Route::middleware(['api'])->prefix('v1')->name('api.v1.')->group(function () {

    // Public API routes
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0',
        ]);
    })->name('health');

    // System Information (public)
    Route::get('/system/info', [SystemController::class, 'info'])->name('system.info');

    // Authenticated API routes
    Route::middleware(['auth:sanctum'])->group(function () {

        // Tenant Management API
        Route::apiResource('tenants', TenantController::class);
        Route::prefix('tenants/{tenant}')->name('tenants.')->group(function () {
            Route::post('/activate', [TenantController::class, 'activate'])->name('activate');
            Route::post('/suspend', [TenantController::class, 'suspend'])->name('suspend');
            Route::post('/reset', [TenantController::class, 'reset'])->name('reset');
            Route::post('/sync', [TenantController::class, 'sync'])->name('sync');
            Route::get('/usage', [TenantController::class, 'usage'])->name('usage');
            Route::get('/statistics', [TenantController::class, 'statistics'])->name('statistics');
        });

        // Module Management API
        Route::get('/modules', [ModuleController::class, 'index'])->name('modules.index');
        Route::get('/modules/{module}', [ModuleController::class, 'show'])->name('modules.show');
        Route::post('/modules/{module}/enable', [ModuleController::class, 'enable'])->name('modules.enable');
        Route::post('/modules/{module}/disable', [ModuleController::class, 'disable'])->name('modules.disable');
        Route::post('/modules/{module}/refresh', [ModuleController::class, 'refresh'])->name('modules.refresh');
        Route::post('/modules/scan', [ModuleController::class, 'scan'])->name('modules.scan');
        Route::post('/modules/migrate', [ModuleController::class, 'migrate'])->name('modules.migrate');

        // System Management API
        Route::get('/system/settings', [SystemController::class, 'settings'])->name('system.settings');
        Route::post('/system/settings', [SystemController::class, 'updateSettings'])->name('system.update-settings');
        Route::post('/system/cache/clear', [SystemController::class, 'clearCache'])->name('system.clear-cache');
        Route::post('/system/optimize', [SystemController::class, 'optimize'])->name('system.optimize');
        Route::get('/system/stats', [SystemController::class, 'stats'])->name('system.stats');
    });
});

// Tenant-specific API routes
Route::middleware(['api', 'tenant', 'auth:sanctum'])->prefix('tenant/v1')->name('tenant.api.v1.')->group(function () {

    // Current tenant information
    Route::get('/info', function (Request $request) {
        $tenant = tenant();

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
                'features' => $tenant->features,
                'limits' => [
                    'max_users' => $tenant->max_users,
                    'max_patients' => $tenant->max_patients,
                    'max_storage_mb' => $tenant->max_storage_mb,
                ],
                'settings' => [
                    'timezone' => $tenant->timezone,
                    'locale' => $tenant->locale,
                    'currency' => $tenant->currency,
                    'tax_rate' => $tenant->tax_rate,
                ],
            ],
            'user' => $request->user(),
        ]);
    })->name('info');

    // Tenant usage statistics
    Route::get('/usage', function () {
        $tenant = tenant();

        return response()->json([
            'users' => [
                'current' => $tenant->users()->count(),
                'limit' => $tenant->max_users,
                'percentage' => $tenant->max_users > 0 ? round(($tenant->users()->count() / $tenant->max_users) * 100, 2) : 0,
            ],
            // Add more usage statistics here
        ]);
    })->name('usage');
});