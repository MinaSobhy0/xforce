<?php

namespace Modules\Marketing\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Marketing\Listeners\SendAppointmentReminder;
use Modules\Marketing\Listeners\SendFollowUpMessage;
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
        // These events will be wired when the corresponding modules dispatch them
        // 'Modules\Booking\Events\AppointmentConfirmed' => [
        //     SendAppointmentReminder::class,
        // ],
        // 'Modules\Booking\Events\AppointmentCompleted' => [
        //     SendFollowUpMessage::class,
        // ],
        // 'Modules\Billing\Events\InvoicePaid' => [
        //     SendInvoiceReceipt::class,
        // ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
