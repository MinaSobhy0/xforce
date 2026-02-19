<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('treatments')->name('treatments.')->group(function () {
    // Custom web routes
});
