<?php

namespace Modules\Equipment\Filament\Resources\EquipmentResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Equipment\Filament\Resources\EquipmentResource;

class ListEquipment extends BaseListRecords
{
    protected static string $resource = EquipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
