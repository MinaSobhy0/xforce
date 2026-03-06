<?php

namespace Modules\Packages\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Booking\Models\Appointment;
use Modules\Packages\Models\PackageSessionUsage;
use Modules\Packages\Models\PackageSubscription;

class PackageSessionUsed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PackageSubscription $subscription,
        public PackageSessionUsage $usage,
        public ?Appointment $appointment = null
    ) {}
}
