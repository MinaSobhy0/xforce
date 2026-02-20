<?php

namespace Modules\Equipment\Filament\Resources\EquipmentTypeResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Modules\Equipment\Filament\Resources\EquipmentTypeResource;

class ListEquipmentTypes extends ListRecords
{
    protected static string $resource = EquipmentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
