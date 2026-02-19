<?php

namespace Modules\Core\Commands;

use Illuminate\Console\Command;

class SystemMaintenanceCommand extends Command
{
    protected $signature = 'system:maintenance {action}';
    protected $description = 'System maintenance operations';

    public function handle(): int
    {
        $this->info('System maintenance will be implemented.');
        return Command::SUCCESS;
    }
}
