<?php

namespace Modules\Inventory\Filament\Resources\SupplierResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;
use Modules\Inventory\Filament\Resources\SupplierResource;

class ViewSupplier extends BaseViewRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
