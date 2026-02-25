<?php

namespace Modules\Equipment\Filament\Resources\EquipmentParameterTemplateResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Equipment\Filament\Resources\EquipmentParameterTemplateResource;

class ListEquipmentParameterTemplates extends BaseListRecords
{
    protected static string $resource = EquipmentParameterTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            ...parent::getHeaderActions(),
        ];
    }
}
