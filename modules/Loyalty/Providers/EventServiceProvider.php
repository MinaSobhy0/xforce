<?php

namespace Modules\Loyalty\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Billing\Events\PaymentReceived;
use Modules\Booking\Events\AppointmentCompleted;
use Modules\Loyalty\Events\ReferralCompleted;
use Modules\Loyalty\Listeners\AwardPointsOnPayment;
use Modules\Loyalty\Listeners\AwardPointsOnVisit;
use Modules\Loyalty\Listeners\AwardReferralBonus;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the Loyalty module.
     */
    protected $listen = [
        // Award points when a payment is received
        PaymentReceived::class => [
            AwardPointsOnPayment::class,
        ],

        // Award points when an appointment is completed
        AppointmentCompleted::class => [
            AwardPointsOnVisit::class,
        ],

        // Award referral bonus when referral is completed
        ReferralCompleted::class => [
            AwardReferralBonus::class,
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
