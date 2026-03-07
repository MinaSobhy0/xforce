<?php

use Illuminate\Support\Facades\Route;
use Modules\Marketing\Http\Controllers\WhatsAppWebhookController;

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

// WhatsApp webhook routes
Route::prefix('webhooks/whatsapp')->group(function () {
    // POST - Handle incoming messages and status updates
    Route::post('/', [WhatsAppWebhookController::class, 'handle'])
        ->name('marketing.webhooks.whatsapp');

    // GET - Webhook verification from Meta
    Route::get('/', [WhatsAppWebhookController::class, 'verify'])
        ->name('marketing.webhooks.whatsapp.verify');
});
