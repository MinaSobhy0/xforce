<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['api'])->prefix('api/core')->name('core.')->group(function () {
    // Core API routes
});
