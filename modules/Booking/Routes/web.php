<?php

use Illuminate\Support\Facades\Route;
use Modules\Booking\Http\Controllers\AppointmentActionController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Appointment action via signed URL (confirm, cancel, reschedule)
Route::get('/appointment/{appointment}/{action}', [AppointmentActionController::class, 'handle'])
    ->name('appointment.action')
    ->middleware('signed');
