<?php

namespace Modules\OdooIntegration\Listeners;

use Modules\OdooIntegration\Events\ConflictDetected;
use Modules\OdooIntegration\Enums\ConflictResolution;
use Modules\OdooIntegration\Services\Sync\ConflictResolver;

class HandleConflict
{
    public function __construct(
        protected ConflictResolver $conflictResolver,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(ConflictDetected $event): void
    {
        $conflict = $event->conflict;
        $mapping = $conflict->entityMapping;

        // If not manual resolution, auto-resolve immediately
        if ($mapping->conflict_resolution !== ConflictResolution::MANUAL) {
            // The conflict resolver already handles non-manual resolution
            // during sync, so we don't need to do anything here
            return;
        }

        // For manual resolution, the conflict is left pending
        // It will be resolved through the UI
    }
}
