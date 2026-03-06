<?php

namespace Modules\Inventory\Filament\Resources\StockLocationResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Inventory\Filament\Resources\StockLocationResource;

class CreateStockLocation extends CreateRecord
{
    protected static string $resource = StockLocationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
