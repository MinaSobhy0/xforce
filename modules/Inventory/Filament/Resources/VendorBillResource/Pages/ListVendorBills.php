<?php

namespace Modules\Inventory\Filament\Resources\VendorBillResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Inventory\Filament\Resources\VendorBillResource;

class ListVendorBills extends BaseListRecords
{
    protected static string $resource = VendorBillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            ...parent::getHeaderActions(),
        ];
    }
}
