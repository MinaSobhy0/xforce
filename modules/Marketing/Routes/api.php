<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Marketing API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for the Marketing module.
| These routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group.
|
*/

// WhatsApp webhook for status updates
Route::post('webhooks/whatsapp', function () {
    // TODO: Implement WhatsApp webhook handler
    return response()->json(['status' => 'ok']);
})->name('marketing.webhooks.whatsapp');

// WhatsApp webhook verification (GET request from Meta)
Route::get('webhooks/whatsapp', function (\Illuminate\Http\Request $request) {
    $verifyToken = config('marketing.whatsapp.webhook_verify_token');

    if ($request->get('hub_mode') === 'subscribe' &&
        $request->get('hub_verify_token') === $verifyToken) {
        return response($request->get('hub_challenge'));
    }

    return response('Forbidden', 403);
})->name('marketing.webhooks.whatsapp.verify');
