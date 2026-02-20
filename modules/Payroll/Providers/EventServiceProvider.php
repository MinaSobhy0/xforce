<?php

namespace Modules\Payroll\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Payroll\Events\PayrollPaid;
use Modules\Payroll\Listeners\CreateSalaryJournalOnPayrollPaid;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        PayrollPaid::class => [
            CreateSalaryJournalOnPayrollPaid::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
