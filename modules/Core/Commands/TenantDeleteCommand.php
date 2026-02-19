<?php

namespace Modules\Core\Commands;

use Illuminate\Console\Command;

class TenantDeleteCommand extends Command
{
    protected $signature = 'tenant:delete {slug}';
    protected $description = 'Delete a tenant';

    public function handle(): int
    {
        $this->info('Tenant deletion will be implemented.');
        return Command::SUCCESS;
    }
}
