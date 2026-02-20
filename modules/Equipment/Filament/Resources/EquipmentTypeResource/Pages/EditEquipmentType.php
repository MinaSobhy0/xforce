<?php

namespace Modules\Equipment\Filament\Resources\EquipmentTypeResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Modules\Equipment\Filament\Resources\EquipmentTypeResource;

class EditEquipmentType extends EditRecord
{
    protected static string $resource = EquipmentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
