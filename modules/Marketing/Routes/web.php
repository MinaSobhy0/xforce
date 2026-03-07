<?php

use Illuminate\Support\Facades\Route;
use Modules\Marketing\Http\Controllers\ShortLinkController;

/*
|--------------------------------------------------------------------------
| Marketing Web Routes
|--------------------------------------------------------------------------
|
| Web routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Short link redirect
Route::get('/l/{code}', [ShortLinkController::class, 'redirect'])->name('short-link.redirect');
