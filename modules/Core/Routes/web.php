<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web'])->prefix('core')->name('core.')->group(function () {
    // Core web routes
});
