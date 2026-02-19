<?php

namespace Modules\Core\Commands;

use Illuminate\Console\Command;

class TenantListCommand extends Command
{
    protected $signature = 'tenant:list';
    protected $description = 'List all tenants';

    public function handle(): int
    {
        $this->info('Tenant listing will be implemented.');
        return Command::SUCCESS;
    }
}
