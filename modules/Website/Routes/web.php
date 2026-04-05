<?php

use Illuminate\Support\Facades\Route;
use Modules\Website\Http\Controllers\WebsiteController;
use Modules\Website\Http\Middleware\WebsiteModuleMiddleware;

/*
|--------------------------------------------------------------------------
| Website Routes
|--------------------------------------------------------------------------
|
| Routes for the public-facing tenant website. These routes use the
| WebsiteModuleMiddleware to determine if the tenant's custom website
| should be rendered or the platform landing page.
|
*/

// Website page routes - handled by middleware to determine render behavior
Route::middleware([WebsiteModuleMiddleware::class])->group(function () {
    // Page by slug - must not conflict with admin/api/etc routes
    Route::get('/{slug}', [WebsiteController::class, 'show'])
        ->where('slug', '^(?!admin|owner|platform|api|login|register|tenant-storage|livewire|filament|_debugbar|sanctum|two-factor-challenge|app|appointment|book|portal).*$')
        ->name('website.page');
});
