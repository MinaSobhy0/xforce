<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\TenantController;
use Modules\Core\Http\Controllers\SystemSettingsController;
use Modules\Core\Http\Controllers\ModuleManagementController;

/*
|--------------------------------------------------------------------------
| Core Module Web Routes
|--------------------------------------------------------------------------
|
| Here are the web routes for the Core module. These routes are loaded
| by the RouteServiceProvider within a group which is assigned the
| "web" middleware group and the "tenant" middleware for multi-tenancy.
|
*/

Route::middleware(['web', 'auth'])->group(function () {

    // Tenant Management Routes
    Route::prefix('admin/tenants')->name('admin.tenants.')->group(function () {
        Route::get('/', [TenantController::class, 'index'])->name('index');
        Route::get('/create', [TenantController::class, 'create'])->name('create');
        Route::post('/', [TenantController::class, 'store'])->name('store');
        Route::get('/{tenant}', [TenantController::class, 'show'])->name('show');
        Route::get('/{tenant}/edit', [TenantController::class, 'edit'])->name('edit');
        Route::put('/{tenant}', [TenantController::class, 'update'])->name('update');
        Route::delete('/{tenant}', [TenantController::class, 'destroy'])->name('destroy');

        // Tenant Actions
        Route::post('/{tenant}/activate', [TenantController::class, 'activate'])->name('activate');
        Route::post('/{tenant}/suspend', [TenantController::class, 'suspend'])->name('suspend');
        Route::post('/{tenant}/reset', [TenantController::class, 'reset'])->name('reset');
        Route::post('/{tenant}/sync', [TenantController::class, 'sync'])->name('sync');
    });

    // System Settings Routes
    Route::prefix('admin/settings')->name('admin.settings.')->group(function () {
        Route::get('/', [SystemSettingsController::class, 'index'])->name('index');
        Route::post('/', [SystemSettingsController::class, 'update'])->name('update');
        Route::post('/clear-cache', [SystemSettingsController::class, 'clearCache'])->name('clear-cache');
        Route::post('/optimize', [SystemSettingsController::class, 'optimize'])->name('optimize');
    });

    // Module Management Routes
    Route::prefix('admin/modules')->name('admin.modules.')->group(function () {
        Route::get('/', [ModuleManagementController::class, 'index'])->name('index');
        Route::get('/{module}', [ModuleManagementController::class, 'show'])->name('show');
        Route::post('/{module}/enable', [ModuleManagementController::class, 'enable'])->name('enable');
        Route::post('/{module}/disable', [ModuleManagementController::class, 'disable'])->name('disable');
        Route::post('/{module}/refresh', [ModuleManagementController::class, 'refresh'])->name('refresh');
        Route::post('/scan', [ModuleManagementController::class, 'scan'])->name('scan');
        Route::post('/migrate', [ModuleManagementController::class, 'migrate'])->name('migrate');
        Route::post('/clear-cache', [ModuleManagementController::class, 'clearCache'])->name('clear-cache');
    });
});

// Tenant Switching Route (Super Admin only)
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/tenant/switch/{tenant}', [TenantController::class, 'switchTenant'])->name('tenant.switch');
    Route::get('/tenant/back', [TenantController::class, 'switchBack'])->name('tenant.back');
});

// Public tenant routes (for subdomain/domain access)
Route::middleware(['web', 'tenant'])->group(function () {
    Route::get('/tenant-info', function () {
        return response()->json([
            'tenant' => tenant(),
            'database' => config('database.connections.tenant.database'),
        ]);
    })->name('tenant.info');
});