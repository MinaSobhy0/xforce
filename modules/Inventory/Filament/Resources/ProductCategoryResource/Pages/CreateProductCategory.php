<?php

namespace Modules\Inventory\Filament\Resources\ProductCategoryResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Inventory\Filament\Resources\ProductCategoryResource;

class CreateProductCategory extends CreateRecord
{
    protected static string $resource = ProductCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
