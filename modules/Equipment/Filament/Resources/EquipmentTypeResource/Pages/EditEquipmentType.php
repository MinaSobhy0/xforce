<?php

namespace Modules\Equipment\Filament\Resources\EquipmentTypeResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Modules\Equipment\Filament\Resources\EquipmentTypeResource;

class EditEquipmentType extends BaseEditRecord
{
    protected static string $resource = EquipmentTypeResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
