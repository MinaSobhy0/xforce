<?php

namespace Modules\Equipment\Filament\Resources\EquipmentTypeResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Equipment\Filament\Resources\EquipmentTypeResource;

class ListEquipmentTypes extends BaseListRecords
{
    protected static string $resource = EquipmentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
