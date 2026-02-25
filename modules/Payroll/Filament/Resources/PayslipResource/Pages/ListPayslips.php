<?php

namespace Modules\Payroll\Filament\Resources\PayslipResource\Pages;

use Modules\Payroll\Filament\Resources\PayslipResource;
use App\Filament\Resources\Pages\BaseListRecords;

class ListPayslips extends BaseListRecords
{
    protected static string $resource = PayslipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
        ];
    }
}
