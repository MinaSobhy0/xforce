<?php

namespace Modules\Payroll\Filament\Resources\PayslipResource\Pages;

use Modules\Payroll\Filament\Resources\PayslipResource;
use Filament\Resources\Pages\ListRecords;

class ListPayslips extends ListRecords
{
    protected static string $resource = PayslipResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
