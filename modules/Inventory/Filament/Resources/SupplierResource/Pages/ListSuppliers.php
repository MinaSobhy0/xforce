<?php

namespace Modules\Inventory\Filament\Resources\SupplierResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Inventory\Filament\Resources\SupplierResource;

class ListSuppliers extends BaseListRecords
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
