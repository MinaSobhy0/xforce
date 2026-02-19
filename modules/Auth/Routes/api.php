<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['api'])->prefix('api/auth')->name('auth.')->group(function () {
    // Auth API routes
});
