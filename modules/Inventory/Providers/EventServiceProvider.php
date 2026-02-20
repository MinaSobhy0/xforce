<?php

namespace Modules\Inventory\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Booking\Events\AppointmentCompleted;
use Modules\Inventory\Listeners\DeductStockOnAppointmentCompleted;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        AppointmentCompleted::class => [
            DeductStockOnAppointmentCompleted::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
