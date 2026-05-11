<?php

namespace Modules\Payroll\Filament\Resources\PayslipResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Payroll\Filament\Resources\PayslipResource;

class CreatePayslip extends CreateRecord
{
    protected static string $resource = PayslipResource::class;
}
