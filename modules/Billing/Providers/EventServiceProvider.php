<?php

namespace Modules\Billing\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Billing\Listeners\CreateInvoiceOnAppointmentComplete;
use Modules\Booking\Events\AppointmentCompleted;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the Billing module.
     */
    protected $listen = [
        // Auto-create invoice when appointment is completed
        AppointmentCompleted::class => [
            CreateInvoiceOnAppointmentComplete::class,
        ],

        // Billing module events - listeners are in other modules:
        // \Modules\Billing\Events\InvoicePaid::class => [],
        // \Modules\Billing\Events\PaymentReceived::class => [],
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
