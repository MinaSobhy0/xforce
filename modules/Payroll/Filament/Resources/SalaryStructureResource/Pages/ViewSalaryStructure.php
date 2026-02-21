<?php

namespace Modules\Payroll\Filament\Resources\SalaryStructureResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;
use Modules\Payroll\Filament\Resources\SalaryStructureResource;

class ViewSalaryStructure extends BaseViewRecord
{
    protected static string $resource = SalaryStructureResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
