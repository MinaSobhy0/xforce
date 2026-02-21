<?php

namespace Modules\Payroll\Filament\Resources\PayrollRunResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Payroll\Filament\Resources\PayrollRunResource;

class ListPayrollRuns extends BaseListRecords
{
    protected static string $resource = PayrollRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
