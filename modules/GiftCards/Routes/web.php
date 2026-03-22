<?php

use Illuminate\Support\Facades\Route;
use Modules\GiftCards\Services\GiftCardPdfService;
use Modules\GiftCards\Models\GiftCard;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'tenant'])
    ->prefix('giftcards')
    ->name('giftcards.')
    ->group(function () {
        // Print gift card PDF
        // SECURITY: Added authorization check to prevent IDOR
        Route::get('/{giftCard}/print', function (GiftCard $giftCard) {
            $user = auth()->user();

            // Check if user has permission to view gift cards
            if (!$user->can('gift_cards.view') && !$user->can('gift_cards.manage')) {
                abort(403, 'Unauthorized access to gift card');
            }

            return app(GiftCardPdfService::class)->stream($giftCard);
        })->name('print');

        // Download gift card PDF
        // SECURITY: Added authorization check to prevent IDOR
        Route::get('/{giftCard}/download', function (GiftCard $giftCard) {
            $user = auth()->user();

            // Check if user has permission to view gift cards
            if (!$user->can('gift_cards.view') && !$user->can('gift_cards.manage')) {
                abort(403, 'Unauthorized access to gift card');
            }

            return app(GiftCardPdfService::class)->download($giftCard);
        })->name('download');
    });
