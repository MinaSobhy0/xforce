<?php

namespace Modules\Equipment\Filament\Resources\EquipmentParameterTemplateResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Modules\Equipment\Filament\Resources\EquipmentParameterTemplateResource;

class ListEquipmentParameterTemplates extends ListRecords
{
    protected static string $resource = EquipmentParameterTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
