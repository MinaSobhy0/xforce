<?php

namespace Modules\Treatments\Filament\Resources\TreatmentResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Treatments\Filament\Resources\TreatmentResource;

class CreateTreatment extends CreateRecord
{
    protected static string $resource = TreatmentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
