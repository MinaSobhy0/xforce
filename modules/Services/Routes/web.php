<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('services')->name('services.')->group(function () {
    // Custom web routes
});
