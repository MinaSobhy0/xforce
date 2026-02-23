<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\Http\Controllers\Api\AttendanceSettingsController;

/*
|--------------------------------------------------------------------------
| Attendance API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['api', 'auth:sanctum'])->prefix('attendance')->group(function () {
    // Attendance Type Settings
    Route::prefix('settings')->group(function () {
        Route::get('/', [AttendanceSettingsController::class, 'index']);
        Route::get('/{type}', [AttendanceSettingsController::class, 'show']);
    });

    // Dynamic QR Code
    Route::get('/qr-dynamic/current', [AttendanceSettingsController::class, 'getDynamicQrCode']);

    // Validation endpoints
    Route::post('/validate/qr', [AttendanceSettingsController::class, 'validateQrCode']);
    Route::post('/validate/geofence', [AttendanceSettingsController::class, 'validateGeofence']);

    // Geofence locations for map
    Route::get('/geofence/locations', [AttendanceSettingsController::class, 'getGeofenceLocations']);
});
