<?php

namespace Modules\Inventory\Filament\Resources\ProductResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Inventory\Filament\Resources\ProductResource;

class ListProducts extends BaseListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
