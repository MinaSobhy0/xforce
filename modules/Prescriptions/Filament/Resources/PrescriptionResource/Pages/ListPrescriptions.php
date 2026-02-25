<?php

namespace Modules\Prescriptions\Filament\Resources\PrescriptionResource\Pages;

use Modules\Prescriptions\Filament\Resources\PrescriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPrescriptions extends ListRecords
{
    protected static string $resource = PrescriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
