<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth:sanctum'])->prefix('v1/treatments')->name('api.treatments.')->group(function () {
    Route::get('/', function () {
        return \Modules\Treatments\Models\Treatment::with(['category'])->where('is_active', true)->paginate();
    })->name('index');

    Route::get('/categories', function () {
        return \Modules\Treatments\Models\TreatmentCategory::whereNull('parent_id')->with('children')->get();
    })->name('categories');

    Route::get('/{treatment}', function (\Modules\Treatments\Models\Treatment $treatment) {
        return $treatment->load(['category', 'branchPricing', 'consentTemplate']);
    })->name('show');
});
