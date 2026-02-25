<?php

namespace Modules\Inventory\Filament\Resources\InventoryAdjustmentResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Inventory\Filament\Resources\InventoryAdjustmentResource;

class ListInventoryAdjustments extends BaseListRecords
{
    protected static string $resource = InventoryAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            ...parent::getHeaderActions(),
        ];
    }
}
