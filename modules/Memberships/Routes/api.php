<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('memberships')->group(function () {
    // API routes for memberships
});
