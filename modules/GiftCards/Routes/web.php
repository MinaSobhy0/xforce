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
        Route::get('/{giftCard}/print', function (GiftCard $giftCard) {
            return app(GiftCardPdfService::class)->stream($giftCard);
        })->name('print');

        // Download gift card PDF
        Route::get('/{giftCard}/download', function (GiftCard $giftCard) {
            return app(GiftCardPdfService::class)->download($giftCard);
        })->name('download');
    });
