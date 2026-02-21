<?php

namespace Modules\Inventory\Filament\Resources\ProductCategoryResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Inventory\Filament\Resources\ProductCategoryResource;

class ListProductCategories extends BaseListRecords
{
    protected static string $resource = ProductCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
