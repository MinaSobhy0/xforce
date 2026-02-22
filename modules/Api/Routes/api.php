<?php

use Illuminate\Support\Facades\Route;
use Modules\Api\Http\Controllers\AuthController;
use Modules\Api\Http\Controllers\PatientController;
use Modules\Api\Http\Controllers\BookingController;
use Modules\Api\Http\Controllers\AttendanceController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| REST API endpoints for patient and staff access
|
*/

// Health check
Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'version' => config('api.version', 'v1'),
    'timestamp' => now()->toIso8601String(),
]));

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    // Patient OTP authentication
    Route::post('/patient/request-otp', [AuthController::class, 'requestOtp'])
        ->middleware('throttle:api-otp');

    Route::post('/patient/verify-otp', [AuthController::class, 'verifyOtp'])
        ->middleware('throttle:api-auth');

    // Staff authentication
    Route::post('/staff/login', [AuthController::class, 'staffLogin'])
        ->middleware('throttle:api-auth');

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    });
});

/*
|--------------------------------------------------------------------------
| Patient Routes (requires patient token)
|--------------------------------------------------------------------------
*/
Route::prefix('patient')
    ->middleware(['auth:sanctum', 'ability:patient:*'])
    ->group(function () {
        // Profile
        Route::get('/profile', [PatientController::class, 'profile']);
        Route::put('/profile', [PatientController::class, 'updateProfile']);

        // Appointments
        Route::get('/appointments', [PatientController::class, 'appointments']);

        // Invoices
        Route::get('/invoices', [PatientController::class, 'invoices']);

        // Loyalty
        Route::get('/loyalty', [PatientController::class, 'loyaltyPoints']);

        // Gift Cards
        Route::post('/gift-cards/check', [PatientController::class, 'checkGiftCard']);
    });

/*
|--------------------------------------------------------------------------
| Booking Routes (requires patient token)
|--------------------------------------------------------------------------
*/
Route::prefix('booking')
    ->middleware(['auth:sanctum', 'ability:patient:*'])
    ->group(function () {
        // Available options
        Route::get('/treatments', [BookingController::class, 'treatments']);
        Route::get('/branches', [BookingController::class, 'branches']);
        Route::get('/slots', [BookingController::class, 'availableSlots']);

        // Book appointment
        Route::post('/book', [BookingController::class, 'book']);
        Route::post('/appointments/{appointment}/cancel', [BookingController::class, 'cancel']);
    });

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::prefix('public')->group(function () {
    // Treatments list (for display purposes)
    Route::get('/treatments', [BookingController::class, 'treatments']);

    // Branches list
    Route::get('/branches', [BookingController::class, 'branches']);
});

/*
|--------------------------------------------------------------------------
| Staff Attendance Routes (requires staff token)
|--------------------------------------------------------------------------
*/
Route::prefix('attendance')
    ->middleware(['auth:sanctum', 'ability:staff:*'])
    ->group(function () {
        // Status & Info
        Route::get('/status', [AttendanceController::class, 'status']);
        Route::get('/schedule', [AttendanceController::class, 'schedule']);
        Route::get('/history', [AttendanceController::class, 'history']);
        Route::get('/summary', [AttendanceController::class, 'monthlySummary']);

        // Check-in/out
        Route::post('/check-in', [AttendanceController::class, 'checkIn']);
        Route::post('/check-out', [AttendanceController::class, 'checkOut']);

        // Breaks
        Route::post('/break/start', [AttendanceController::class, 'startBreak']);
        Route::post('/break/end', [AttendanceController::class, 'endBreak']);

        // Violations
        Route::get('/violations', [AttendanceController::class, 'violations']);
        Route::post('/violations/{violation}/dispute', [AttendanceController::class, 'disputeViolation']);
    });
