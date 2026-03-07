<?php

namespace Modules\Marketing\Console;

use Illuminate\Console\Command;
use Modules\Marketing\Services\CampaignService;

class ProcessScheduledCampaignsCommand extends Command
{
    protected $signature = 'campaigns:process-scheduled';

    protected $description = 'Process scheduled campaigns that are due to start';

    public function handle(CampaignService $campaignService): int
    {
        $this->info('Processing scheduled campaigns...');

        $count = $campaignService->processScheduledCampaigns();

        $this->info("Started {$count} campaign(s).");

        return self::SUCCESS;
    }
}
