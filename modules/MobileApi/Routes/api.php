<?php

use Illuminate\Support\Facades\Route;
use Modules\MobileApi\Http\Controllers\TenantDiscoveryController;
use Modules\MobileApi\Http\Controllers\AppConfigController;
use Modules\MobileApi\Http\Controllers\AuthController;
use Modules\MobileApi\Http\Controllers\ScreenController;
use Modules\MobileApi\Http\Controllers\DashboardController;
use Modules\MobileApi\Http\Controllers\StaffProfileController;
use Modules\MobileApi\Http\Controllers\AttendanceController;
use Modules\MobileApi\Http\Controllers\TimeOffController;
use Modules\MobileApi\Http\Controllers\PayrollController;
use Modules\MobileApi\Http\Controllers\ScheduleController;
use Modules\MobileApi\Http\Controllers\AppointmentsController;
use Modules\MobileApi\Http\Controllers\PatientsController;
use Modules\MobileApi\Http\Controllers\DeviceController;

/*
|--------------------------------------------------------------------------
| Mobile API v2 Routes
|--------------------------------------------------------------------------
*/

// Health check - public, no auth
Route::get('health', function () {
    return response()->json([
        'success' => true,
        'status' => 'healthy',
        'version' => config('mobile_api.version', 'v2'),
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Tenant Discovery - public, no auth, no tenant context
Route::prefix('tenant')->group(function () {
    Route::get('resolve/{code}', [TenantDiscoveryController::class, 'resolve']);
    Route::post('validate', [TenantDiscoveryController::class, 'validateTenant']);
    Route::get('lookup', [TenantDiscoveryController::class, 'lookup']);
});

// Tenant-specific routes (requires X-Tenant-Slug header)
Route::middleware([\Modules\MobileApi\Http\Middleware\ResolveTenantFromHeader::class])->group(function () {

    // App config and branding - public within tenant context
    Route::get('config', [AppConfigController::class, 'index']);
    Route::get('branding', [AppConfigController::class, 'branding']);
    Route::get('navigation', [AppConfigController::class, 'navigation']);

    // Auth routes (tenant context, no auth)
    Route::prefix('auth')->middleware('throttle:mobile-api-auth')->group(function () {
        Route::post('staff/login', [AuthController::class, 'login']);
        Route::post('staff/2fa', [AuthController::class, 'verify2fa']);
    });

    // Authenticated staff routes
    Route::middleware([
        'auth:sanctum',
        \Modules\MobileApi\Http\Middleware\ResolveBranchContext::class,
        \Modules\MobileApi\Http\Middleware\CheckStaffPermission::class,
    ])->group(function () {

        // Auth management
        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::get('me', [AuthController::class, 'me']);
        });

        // Staff Profile & Dashboard
        Route::prefix('staff')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'index']);

            Route::get('profile', [StaffProfileController::class, 'show']);
            Route::put('profile', [StaffProfileController::class, 'update']);

            // Commission
            Route::get('commission', [StaffProfileController::class, 'commission']);
            Route::get('commission/history', [StaffProfileController::class, 'commissionHistory']);
            Route::get('commission/pending', [StaffProfileController::class, 'commissionPending']);
        });

        // Attendance
        Route::prefix('attendance')->group(function () {
            Route::get('types', [AttendanceController::class, 'types']);
            Route::get('status', [AttendanceController::class, 'status']);
            Route::get('settings', [AttendanceController::class, 'settings']);
            Route::get('history', [AttendanceController::class, 'history']);
            Route::get('summary', [AttendanceController::class, 'summary']);
            Route::get('violations', [AttendanceController::class, 'violations']);
            Route::get('geofence/locations', [AttendanceController::class, 'getGeofenceLocations']);

            Route::post('check-in', [AttendanceController::class, 'checkIn']);
            Route::post('check-out', [AttendanceController::class, 'checkOut']);
            Route::post('break/start', [AttendanceController::class, 'startBreak']);
            Route::post('break/end', [AttendanceController::class, 'endBreak']);

            Route::post('violations/{id}/dispute', [AttendanceController::class, 'dispute']);

            // Validation endpoints
            Route::post('validate/qr', [AttendanceController::class, 'validateQr']);
            Route::post('validate/geofence', [AttendanceController::class, 'validateGeofence']);
            Route::get('qr-dynamic/current', [AttendanceController::class, 'getDynamicQr']);
        });

        // Time Off / Leave
        Route::prefix('time-off')->group(function () {
            Route::get('types', [TimeOffController::class, 'types']);
            Route::get('balance', [TimeOffController::class, 'balance']);
            Route::get('requests', [TimeOffController::class, 'requests']);
            Route::get('requests/{id}', [TimeOffController::class, 'show']);
            Route::get('calendar', [TimeOffController::class, 'calendar']);

            Route::post('requests', [TimeOffController::class, 'store']);
            Route::post('requests/{id}/cancel', [TimeOffController::class, 'cancel']);
        });

        // Payroll / Payslips
        Route::prefix('payroll')->group(function () {
            Route::get('current', [PayrollController::class, 'current']);
            Route::get('history', [PayrollController::class, 'history']);
            Route::get('summary', [PayrollController::class, 'summary']);
            Route::get('salary-structure', [PayrollController::class, 'salaryStructure']);
            Route::get('{id}', [PayrollController::class, 'show']);
            Route::get('{id}/download', [PayrollController::class, 'download']);
        });

        // Schedule
        Route::prefix('schedule')->group(function () {
            Route::get('current', [ScheduleController::class, 'current']);
            Route::get('shifts', [ScheduleController::class, 'shifts']);
            Route::get('shifts/{date}', [ScheduleController::class, 'shiftForDate']);
            Route::get('working-hours', [ScheduleController::class, 'workingHours']);
        });

        // Appointments
        Route::prefix('appointments')->group(function () {
            Route::get('today', [AppointmentsController::class, 'today']);
            Route::get('/', [AppointmentsController::class, 'index']);
            Route::get('{id}', [AppointmentsController::class, 'show']);
            Route::get('{id}/patient', [AppointmentsController::class, 'patient']);

            Route::post('{id}/start', [AppointmentsController::class, 'start']);
            Route::post('{id}/complete', [AppointmentsController::class, 'complete']);
            Route::post('{id}/notes', [AppointmentsController::class, 'notes']);
        });

        // Patients
        Route::prefix('patients')->group(function () {
            Route::get('search', [PatientsController::class, 'search']);
            Route::get('{id}', [PatientsController::class, 'show']);
            Route::get('{id}/appointments', [PatientsController::class, 'appointments']);
            Route::get('{id}/visits', [PatientsController::class, 'visits']);
        });

        // SDUI Screens
        Route::prefix('screens')->group(function () {
            Route::get('{screen}', [ScreenController::class, 'show']);
            Route::get('{screen}/data', [ScreenController::class, 'data']);
        });

        // Device Registration (Push Notifications)
        Route::prefix('devices')->group(function () {
            Route::get('/', [DeviceController::class, 'listDevices']);
            Route::post('/', [DeviceController::class, 'register']);
            Route::delete('{deviceId}', [DeviceController::class, 'unregister']);
        });

        // Notifications
        Route::prefix('notifications')->group(function () {
            Route::get('/', [DeviceController::class, 'index']);
            Route::get('unread-count', [DeviceController::class, 'unreadCount']);
            Route::post('{id}/read', [DeviceController::class, 'markRead']);
            Route::post('read-all', [DeviceController::class, 'markAllRead']);
            Route::get('preferences', [DeviceController::class, 'preferences']);
            Route::put('preferences', [DeviceController::class, 'updatePreferences']);
        });
    });
});
