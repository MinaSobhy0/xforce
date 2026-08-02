<?php

namespace Modules\OdooIntegration\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\OdooIntegration\Events\ConflictDetected;
use Modules\OdooIntegration\Events\RecordSynced;
use Modules\OdooIntegration\Events\SyncCompleted;
use Modules\OdooIntegration\Events\SyncFailed;
use Modules\OdooIntegration\Events\SyncStarted;
use Modules\OdooIntegration\Listeners\HandleConflict;
use Modules\OdooIntegration\Listeners\LogSyncActivity;
use Modules\OdooIntegration\Listeners\NotifySyncFailure;
use Modules\OdooIntegration\Listeners\ReconcileWorkScheduleAssignments;
use Modules\OdooIntegration\Listeners\SyncAttendanceConfigFromOdoo;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the module.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        SyncStarted::class => [
            LogSyncActivity::class,
        ],
        SyncCompleted::class => [
            LogSyncActivity::class,
            ReconcileWorkScheduleAssignments::class,
            SyncAttendanceConfigFromOdoo::class,
        ],
        SyncFailed::class => [
            LogSyncActivity::class,
            NotifySyncFailure::class,
        ],
        RecordSynced::class => [
            LogSyncActivity::class,
        ],
        ConflictDetected::class => [
            HandleConflict::class,
            LogSyncActivity::class,
        ],
    ];

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
