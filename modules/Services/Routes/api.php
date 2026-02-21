<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth:sanctum'])->prefix('v1/services')->name('api.services.')->group(function () {
    Route::get('/', function () {
        return \Modules\Services\Models\Service::with(['category'])->where('is_active', true)->paginate();
    })->name('index');

    Route::get('/categories', function () {
        return \Modules\Services\Models\ServiceCategory::whereNull('parent_id')->with('children')->get();
    })->name('categories');

    Route::get('/{service}', function (\Modules\Services\Models\Service $service) {
        return $service->load(['category', 'branchPricing', 'consentTemplate']);
    })->name('show');
});
