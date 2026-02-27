<?php

namespace Modules\Marketing\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Marketing\Listeners\SendAppointmentReminder;
// use Modules\Marketing\Listeners\SendFollowUpMessage; // TODO: Create this listener
use Modules\Marketing\Listeners\SendInvoiceReceipt;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * Events from other modules that trigger marketing actions:
     * - AppointmentConfirmed → Send confirmation + schedule reminders
     * - AppointmentCompleted → Schedule follow-up message
     * - InvoicePaid → Send receipt via preferred channel
     */
    protected $listen = [
        \Modules\Booking\Events\AppointmentConfirmed::class => [
            SendAppointmentReminder::class,
        ],
        // TODO: Create SendFollowUpMessage listener
        // \Modules\Booking\Events\AppointmentCompleted::class => [
        //     SendFollowUpMessage::class,
        // ],
        \Modules\Billing\Events\InvoicePaid::class => [
            SendInvoiceReceipt::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
