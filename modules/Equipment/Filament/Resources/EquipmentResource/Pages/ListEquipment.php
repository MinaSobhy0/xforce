<?php

namespace Modules\Equipment\Filament\Resources\EquipmentResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Modules\Equipment\Filament\Resources\EquipmentResource;

class ListEquipment extends ListRecords
{
    protected static string $resource = EquipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
