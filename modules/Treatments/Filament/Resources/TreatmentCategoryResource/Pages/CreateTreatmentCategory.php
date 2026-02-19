<?php

namespace Modules\Treatments\Filament\Resources\TreatmentCategoryResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Treatments\Filament\Resources\TreatmentCategoryResource;

class CreateTreatmentCategory extends CreateRecord
{
    protected static string $resource = TreatmentCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
