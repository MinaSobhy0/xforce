<?php

namespace Modules\Loyalty\Listeners;

use Modules\Loyalty\Services\LoyaltyService;
use Illuminate\Contracts\Queue\ShouldQueue;

class AwardReferralBonus implements ShouldQueue
{
    protected LoyaltyService $loyaltyService;

    public function __construct(LoyaltyService $loyaltyService)
    {
        $this->loyaltyService = $loyaltyService;
    }

    public function handle($event): void
    {
        $referral = $event->referral ?? $event;

        // Process referral rewards
        $this->loyaltyService->processReferralReward($referral);
    }
}
