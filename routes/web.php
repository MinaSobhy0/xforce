<?php

use App\Http\Controllers\Auth\TwoFactorChallengeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Two-Factor Authentication Routes
Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])
    ->name('two-factor.challenge');

