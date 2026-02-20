<?php

namespace Modules\Payroll\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Payroll\Models\PayrollRun;

class PayrollPaid
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PayrollRun $payrollRun
    ) {}
}
