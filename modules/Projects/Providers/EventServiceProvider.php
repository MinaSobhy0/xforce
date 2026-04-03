<?php

namespace Modules\Projects\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the Projects module.
     */
    protected $listen = [
        // Projects module events - listeners can be added by other modules:
        // \Modules\Projects\Events\ProjectCreated::class => [],
        // \Modules\Projects\Events\ProjectCompleted::class => [],
        // \Modules\Projects\Events\TaskCreated::class => [],
        // \Modules\Projects\Events\TaskCompleted::class => [],
        // \Modules\Projects\Events\TaskAssigned::class => [],
        // \Modules\Projects\Events\TimeEntryLogged::class => [],
        // \Modules\Projects\Events\MilestoneReached::class => [],
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
