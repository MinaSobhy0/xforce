<?php

use Illuminate\Support\Facades\Route;
use Modules\MobileApi\Http\Controllers\AccountController;
use Modules\MobileApi\Http\Controllers\AppConfigController;
use Modules\MobileApi\Http\Controllers\AppointmentsController;
use Modules\MobileApi\Http\Controllers\ApprovalsController;
use Modules\MobileApi\Http\Controllers\AppVersionController;
use Modules\MobileApi\Http\Controllers\AttendanceController;
use Modules\MobileApi\Http\Controllers\AuthController;
use Modules\MobileApi\Http\Controllers\CalendarController;
use Modules\MobileApi\Http\Controllers\DashboardController;
use Modules\MobileApi\Http\Controllers\DeviceController;
use Modules\MobileApi\Http\Controllers\PatientsController;
use Modules\MobileApi\Http\Controllers\PayrollController;
use Modules\MobileApi\Http\Controllers\ScheduleController;
use Modules\MobileApi\Http\Controllers\ScreenController;
use Modules\MobileApi\Http\Controllers\StaffProfileController;
use Modules\MobileApi\Http\Controllers\TenantDiscoveryController;
use Modules\MobileApi\Http\Controllers\TimeOffController;

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
// SECURITY: Rate limited to prevent tenant enumeration attacks
Route::prefix('tenant')->middleware('throttle:mobile-api-discovery')->group(function () {
    Route::get('resolve/{code}', [TenantDiscoveryController::class, 'resolve']);
    Route::post('validate', [TenantDiscoveryController::class, 'validateTenant']);
    Route::get('lookup', [TenantDiscoveryController::class, 'lookup']);
});

// App version check — public, no tenant context required. The splash screen
// hits this BEFORE the user picks a clinic so the force-update screen can
// render even on first launch. Driven by the platform-wide App Version
// Policy page in the SuperAdmin panel.
Route::get('app/version-check', [AppVersionController::class, 'check'])
    ->middleware('throttle:mobile-api-discovery');

// Tenant-specific routes (requires X-Tenant-Slug header)
Route::middleware([\Modules\MobileApi\Http\Middleware\ResolveTenantFromHeader::class])->group(function () {

    // App config and branding - public within tenant context
    Route::get('config', [AppConfigController::class, 'index']);
    Route::get('branding', [AppConfigController::class, 'branding']);
    Route::get('navigation', [AppConfigController::class, 'navigation']);
    Route::get('quick-actions', [AppConfigController::class, 'quickActions']);

    // Auth routes (tenant context, no auth)
    Route::prefix('auth')->group(function () {
        // Login with standard auth rate limiting
        Route::post('staff/login', [AuthController::class, 'login'])
            ->middleware('throttle:mobile-api-auth');

        // SECURITY: 2FA requires stricter rate limiting to prevent brute force
        // Only 1 million possible 6-digit codes - must limit attempts
        Route::post('staff/2fa', [AuthController::class, 'verify2fa'])
            ->middleware('throttle:mobile-api-otp');
    });

    // Authenticated staff routes
    Route::middleware([
        'auth:sanctum',
        // SECURITY: must sit immediately after auth:sanctum — rejects a token
        // issued by a different tenant before any tenant data is touched.
        \Modules\MobileApi\Http\Middleware\EnsureTokenMatchesTenant::class,
        \Modules\MobileApi\Http\Middleware\ResolveBranchContext::class,
        \Modules\MobileApi\Http\Middleware\CheckStaffPermission::class,
    ])->group(function () {

        // Auth management
        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::get('me', [AuthController::class, 'me']);
        });

        // Self-service account deletion (Play Store / App Store compliance)
        Route::delete('account', [AccountController::class, 'destroy'])
            ->middleware('throttle:mobile-api-auth');

        // Staff Profile & Dashboard
        Route::prefix('staff')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'index']);

            Route::get('profile', [StaffProfileController::class, 'show']);
            Route::put('profile', [StaffProfileController::class, 'update']);
            Route::post('profile/change-password', [StaffProfileController::class, 'changePassword']);
            Route::post('profile/avatar', [StaffProfileController::class, 'updateAvatar']);
            Route::delete('profile/avatar', [StaffProfileController::class, 'deleteAvatar']);

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
            Route::post('sync', [AttendanceController::class, 'syncOffline']);
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

        // Manager approvals — records routed to the authenticated user via
        // staff_profiles.{time_off,attendance}_approver_user_id, plus an
        // HR-wide override for *.approve_any holders. See ApprovalsController.
        Route::prefix('approvals')->group(function () {
            Route::get('counts', [ApprovalsController::class, 'counts']);

            Route::get('time-off', [ApprovalsController::class, 'timeOffIndex']);
            Route::get('time-off/{id}', [ApprovalsController::class, 'timeOffShow']);
            Route::post('time-off/{id}/approve', [ApprovalsController::class, 'timeOffApprove']);
            Route::post('time-off/{id}/reject', [ApprovalsController::class, 'timeOffReject']);

            Route::get('violations', [ApprovalsController::class, 'violationsIndex']);
            Route::get('violations/{id}', [ApprovalsController::class, 'violationsShow']);
            Route::post('violations/{id}/approve', [ApprovalsController::class, 'violationsApprove']);
            Route::post('violations/{id}/waive', [ApprovalsController::class, 'violationsWaive']);
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

        // Schedule (legacy - use calendar instead)
        Route::prefix('schedule')->group(function () {
            Route::get('current', [ScheduleController::class, 'current']);
            Route::get('shifts', [ScheduleController::class, 'shifts']);
            Route::get('shifts/{date}', [ScheduleController::class, 'shiftForDate']);
            Route::get('working-hours', [ScheduleController::class, 'workingHours']);
        });

        // Calendar (unified view for doctors)
        Route::prefix('calendar')->group(function () {
            Route::get('/', [CalendarController::class, 'index']);           // Monthly calendar
            Route::get('week', [CalendarController::class, 'week']);         // Weekly calendar
            Route::get('upcoming', [CalendarController::class, 'upcoming']); // Upcoming events
            Route::get('{date}', [CalendarController::class, 'day']);        // Day detail
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
