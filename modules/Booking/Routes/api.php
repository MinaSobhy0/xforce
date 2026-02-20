<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->prefix('booking')->group(function () {
    // Available slots endpoint for calendar
    Route::get('/available-slots', function () {
        // Will be handled by AvailabilityService
        return response()->json(['slots' => []]);
    })->name('booking.available-slots');
});
