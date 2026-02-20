<?php

namespace Modules\GiftCards\Listeners;

use Modules\Billing\Events\InvoicePaid;
use Modules\GiftCards\Models\GiftCard;

class ActivateGiftCardOnInvoicePaid
{
    public function handle(InvoicePaid $event): void
    {
        // Find any draft gift cards purchased via this invoice
        $giftCards = GiftCard::where('purchased_via_invoice_id', $event->invoice->id)
            ->where('status', GiftCard::STATUS_DRAFT)
            ->get();

        foreach ($giftCards as $giftCard) {
            $giftCard->activate();
        }
    }
}
