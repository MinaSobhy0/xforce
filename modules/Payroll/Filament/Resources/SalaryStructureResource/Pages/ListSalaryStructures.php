<?php

namespace Modules\Payroll\Filament\Resources\SalaryStructureResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Payroll\Filament\Resources\SalaryStructureResource;

class ListSalaryStructures extends BaseListRecords
{
    protected static string $resource = SalaryStructureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
