<?php

use App\Http\Middleware\ResolveTenantFromWabaWebhook;
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
    // POST — incoming messages + status updates.
    // ResolveTenantFromWabaWebhook reads entry[0].id (the WABA id) from the
    // payload and switches to that tenant's schema before the controller
    // runs, so NotificationLog / WhatsAppMessage queries hit the right
    // tenant_xxx schema. Signature verification stays in the controller.
    Route::post('/', [WhatsAppWebhookController::class, 'handle'])
        ->middleware(ResolveTenantFromWabaWebhook::class)
        ->name('marketing.webhooks.whatsapp');

    // GET — Meta hub challenge / verify handshake. No payload, no tenant
    // context needed; the verify token alone is the auth here.
    Route::get('/', [WhatsAppWebhookController::class, 'verify'])
        ->name('marketing.webhooks.whatsapp.verify');
});
