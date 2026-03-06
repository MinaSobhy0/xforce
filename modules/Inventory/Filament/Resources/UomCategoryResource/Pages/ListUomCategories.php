<?php

namespace Modules\Inventory\Filament\Resources\UomCategoryResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Inventory\Filament\Resources\UomCategoryResource;

class ListUomCategories extends BaseListRecords
{
    protected static string $resource = UomCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
