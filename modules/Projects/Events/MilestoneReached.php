<?php

namespace Modules\Projects\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Projects\Models\ProjectMilestone;

class MilestoneReached
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ProjectMilestone $milestone
    ) {}
}
