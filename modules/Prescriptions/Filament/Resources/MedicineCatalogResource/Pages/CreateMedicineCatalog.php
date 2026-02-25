<?php

namespace Modules\Prescriptions\Filament\Resources\MedicineCatalogResource\Pages;

use Modules\Prescriptions\Filament\Resources\MedicineCatalogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMedicineCatalog extends CreateRecord
{
    protected static string $resource = MedicineCatalogResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
