<?php

namespace Modules\Assets\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Inventory\Events\PurchaseOrderReceived;
use Modules\Assets\Listeners\CreateAssetOnPurchaseReceived;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the Assets module.
     */
    protected $listen = [
        // When a PO is received, auto-create assets for products with asset types
        PurchaseOrderReceived::class => [
            CreateAssetOnPurchaseReceived::class,
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
