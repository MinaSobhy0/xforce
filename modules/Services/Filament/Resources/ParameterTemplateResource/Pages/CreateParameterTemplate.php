<?php

namespace Modules\Services\Filament\Resources\ParameterTemplateResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Services\Filament\Resources\ParameterTemplateResource;

class CreateParameterTemplate extends CreateRecord
{
    protected static string $resource = ParameterTemplateResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
