<?php

use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\TenantMediaController;
use Modules\Website\Http\Controllers\WebsiteController;
use Modules\Website\Http\Middleware\WebsiteModuleMiddleware;
use Illuminate\Support\Facades\Route;

// Root route - checks if tenant has Website module enabled
// If enabled: renders tenant's custom website
// If disabled or no subdomain: shows platform landing page
Route::get('/', [WebsiteController::class, 'home'])
    ->middleware(WebsiteModuleMiddleware::class)
    ->name('home');

// Define login route for auth middleware redirect
Route::get('/login', function () {
    return redirect()->route('filament.tenant.auth.login');
})->name('login');

// Book appointment route - redirects to patient portal
Route::get('/book', function () {
    return redirect()->route('filament.portal.auth.login');
})->name('book');

// SECURITY: Rate limit contact form to prevent abuse
Route::post('/contact', [ContactController::class, 'submit'])
    ->middleware('throttle:3,5')
    ->name('contact.submit');

// Two-Factor Authentication Routes
Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])
    ->name('two-factor.challenge');

// Backup Download Route (protected by auth)
Route::get('/admin/backups/{backup}/download', [BackupController::class, 'download'])
    ->middleware(['auth'])
    ->name('admin.backups.download');

// Tenant Storage Route - serves files from tenant-specific storage
// Tenant is identified via subdomain, requires authentication
Route::get('/tenant-storage/{path}', [TenantMediaController::class, 'show'])
    ->middleware(['auth'])
    ->where('path', '.*')
    ->name('tenant.storage');

// Public Website Assets Route - serves public website images (logos, gallery, etc.)
// No auth required, only allows website/ directory
Route::get('/website-assets/{path}', [TenantMediaController::class, 'showPublic'])
    ->where('path', '.*')
    ->name('website.assets');

// Appointment action via signed URL (confirm, cancel, reschedule)
Route::get('/appointment/{appointment}/{action}', function (\Illuminate\Http\Request $request, $appointment, $action) {
    $appointmentModel = \Modules\Booking\Models\Appointment::findOrFail($appointment);
    return app(\Modules\Booking\Http\Controllers\AppointmentActionController::class)->handle($request, $appointmentModel, $action);
})->name('appointment.action');

// Mobile App QR Code Join Route
Route::get('/app/join/{code}', function (string $code) {
    $appCode = \App\Models\TenantAppCode::with('tenant')
        ->where('code', strtoupper($code))
        ->first();

    if (!$appCode || !$appCode->isValid()) {
        abort(404, 'Invalid or expired code');
    }

    $tenant = $appCode->tenant;
    if (!$tenant || !$tenant->isActive()) {
        abort(404, 'Clinic not found');
    }

    // Increment usage
    $appCode->incrementUsage();

    // Return a simple page with app download links or redirect
    return view('app-join', [
        'tenant' => $tenant,
        'code' => $appCode->code,
        'deepLink' => $appCode->deep_link,
    ]);
})->name('app.join');

