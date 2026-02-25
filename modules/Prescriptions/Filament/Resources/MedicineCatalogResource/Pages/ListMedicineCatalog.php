<?php

namespace Modules\Prescriptions\Filament\Resources\MedicineCatalogResource\Pages;

use Modules\Prescriptions\Filament\Resources\MedicineCatalogResource;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Actions;

class ListMedicineCatalog extends BaseListRecords
{
    protected static string $resource = MedicineCatalogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
