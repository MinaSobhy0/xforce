<?php

namespace Modules\Prescriptions\Filament\Resources\MedicineCatalogResource\Pages;

use Modules\Prescriptions\Filament\Resources\MedicineCatalogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMedicineCatalog extends ListRecords
{
    protected static string $resource = MedicineCatalogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
