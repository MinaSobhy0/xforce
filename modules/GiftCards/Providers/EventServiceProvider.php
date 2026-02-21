<?php

namespace Modules\GiftCards\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Billing\Events\InvoicePaid;
use Modules\GiftCards\Listeners\ActivateGiftCardOnInvoicePaid;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the GiftCards module.
     */
    protected $listen = [
        // When an invoice is paid, activate any gift cards on that invoice
        InvoicePaid::class => [
            ActivateGiftCardOnInvoicePaid::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
