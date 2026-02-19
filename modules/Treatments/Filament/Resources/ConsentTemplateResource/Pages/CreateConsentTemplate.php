<?php

namespace Modules\Treatments\Filament\Resources\ConsentTemplateResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Treatments\Filament\Resources\ConsentTemplateResource;

class CreateConsentTemplate extends CreateRecord
{
    protected static string $resource = ConsentTemplateResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
