<?php

namespace Modules\Equipment\Filament\Resources\EquipmentParameterTemplateResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Modules\Equipment\Filament\Resources\EquipmentParameterTemplateResource;

class ViewEquipmentParameterTemplate extends ViewRecord
{
    protected static string $resource = EquipmentParameterTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn ($record) => !$record->is_system),
        ];
    }
}
