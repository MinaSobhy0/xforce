<?php

namespace Modules\Equipment\Filament\Resources\EquipmentResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Modules\Equipment\Filament\Resources\EquipmentResource;

class EditEquipment extends EditRecord
{
    protected static string $resource = EquipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
