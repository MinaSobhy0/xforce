<?php

namespace Modules\OdooIntegration\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\OdooIntegration\Models\OdooSyncLog;

class SyncStarted
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public OdooSyncLog $syncLog,
    ) {}
}
