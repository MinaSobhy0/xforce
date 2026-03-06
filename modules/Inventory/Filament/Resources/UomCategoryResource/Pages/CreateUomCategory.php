<?php

namespace Modules\Inventory\Filament\Resources\UomCategoryResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Inventory\Filament\Resources\UomCategoryResource;

class CreateUomCategory extends CreateRecord
{
    protected static string $resource = UomCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
