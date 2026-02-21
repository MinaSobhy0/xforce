<?php

namespace Modules\Payroll\Filament\Resources\SalaryStructureResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Modules\Payroll\Filament\Resources\SalaryStructureResource;

class EditSalaryStructure extends BaseEditRecord
{
    protected static string $resource = SalaryStructureResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
