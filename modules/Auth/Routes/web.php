<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web'])->prefix('auth')->name('auth.')->group(function () {
    // Auth web routes
});
