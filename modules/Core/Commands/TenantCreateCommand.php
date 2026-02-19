<?php

namespace Modules\Core\Commands;

use Illuminate\Console\Command;

class TenantCreateCommand extends Command
{
    protected $signature = 'tenant:create {name} {--slug=}';
    protected $description = 'Create a new tenant';

    public function handle(): int
    {
        $this->info('Tenant creation will be implemented.');
        return Command::SUCCESS;
    }
}
