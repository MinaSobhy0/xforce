<?php

namespace Modules\Staff\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Booking\Events\AppointmentCompleted;
use Modules\Staff\Listeners\CalculateCommissionOnAppointmentCompleted;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        AppointmentCompleted::class => [
            CalculateCommissionOnAppointmentCompleted::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
