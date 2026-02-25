<?php

namespace Modules\Prescriptions\Filament\Resources\PrescriptionResource\Pages;

use Modules\Prescriptions\Filament\Resources\PrescriptionResource;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Actions;

class ListPrescriptions extends BaseListRecords
{
    protected static string $resource = PrescriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
