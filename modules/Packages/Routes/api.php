<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('packages')->group(function () {
    // API routes for packages
});
