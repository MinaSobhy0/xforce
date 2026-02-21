<?php

namespace Modules\Inventory\Filament\Resources\ProductResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;
use Modules\Inventory\Filament\Resources\ProductResource;

class ViewProduct extends BaseViewRecord
{
    protected static string $resource = ProductResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
