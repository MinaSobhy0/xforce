<?php

namespace Modules\Inventory\Filament\Resources\PurchaseOrderResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Inventory\Filament\Resources\PurchaseOrderResource;

class ListPurchaseOrders extends BaseListRecords
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
