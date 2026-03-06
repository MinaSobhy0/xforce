<?php

namespace Modules\Packages\Listeners;

use Modules\Packages\Events\PackageSessionUsed;
use Modules\Packages\Services\RevenueRecognitionService;

class RecognizePackageRevenue
{
    protected RevenueRecognitionService $revenueService;

    public function __construct(RevenueRecognitionService $revenueService)
    {
        $this->revenueService = $revenueService;
    }

    public function handle(PackageSessionUsed $event): void
    {
        // Skip if revenue recognition is disabled
        if (!config('packages.auto_revenue_recognition', true)) {
            return;
        }

        // Skip if already has revenue recognition
        if ($event->usage->hasRevenueRecognition()) {
            return;
        }

        $this->revenueService->recognizeSessionRevenue(
            $event->subscription,
            $event->usage,
            $event->appointment
        );
    }
}
