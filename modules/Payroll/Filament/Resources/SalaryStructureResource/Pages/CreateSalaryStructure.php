<?php

namespace Modules\Payroll\Filament\Resources\SalaryStructureResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Payroll\Filament\Resources\SalaryStructureResource;

class CreateSalaryStructure extends CreateRecord
{
    protected static string $resource = SalaryStructureResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        return $data;
    }
}
