<?php

namespace Modules\Booking\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the Booking module.
     *
     * Booking emits events like AppointmentCompleted, AppointmentCancelled
     * that other modules (Loyalty, Billing) listen to in their own
     * EventServiceProviders.
     */
    protected $listen = [
        // Booking module events - listeners are registered in other modules:
        // \Modules\Booking\Events\AppointmentCompleted::class => [],
        // \Modules\Booking\Events\AppointmentCancelled::class => [],
        // \Modules\Booking\Events\AppointmentRescheduled::class => [],
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
