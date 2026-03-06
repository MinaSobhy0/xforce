<?php

namespace Modules\Inventory\Filament\Resources\StockLocationResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;
use Modules\Inventory\Filament\Resources\StockLocationResource;

class ViewStockLocation extends BaseViewRecord
{
    protected static string $resource = StockLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
