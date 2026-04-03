<?php

namespace Modules\OdooIntegration\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\OdooIntegration\Models\OdooSyncRecord;

class RecordSynced
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public OdooSyncRecord $syncRecord,
        public string $direction, // import, export
        public string $action, // created, updated, skipped
    ) {}
}
