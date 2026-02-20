<?php

namespace Modules\Loyalty\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Loyalty\Models\Referral;

class ReferralCompleted
{
    use Dispatchable, SerializesModels;

    public Referral $referral;

    public function __construct(Referral $referral)
    {
        $this->referral = $referral;
    }
}
