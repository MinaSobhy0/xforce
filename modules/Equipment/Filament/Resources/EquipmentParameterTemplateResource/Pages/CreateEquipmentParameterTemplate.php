<?php

namespace Modules\Equipment\Filament\Resources\EquipmentParameterTemplateResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Equipment\Filament\Resources\EquipmentParameterTemplateResource;

class CreateEquipmentParameterTemplate extends CreateRecord
{
    protected static string $resource = EquipmentParameterTemplateResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
