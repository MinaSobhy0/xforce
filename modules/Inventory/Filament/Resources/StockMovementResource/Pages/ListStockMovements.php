<?php

namespace Modules\Inventory\Filament\Resources\StockMovementResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Modules\Inventory\Filament\Resources\StockMovementResource;
use Modules\Inventory\Models\StockMovement;

class ListStockMovements extends ListRecords
{
    protected static string $resource = StockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - movements are created by other processes
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StockMovementResource\Widgets\StockMovementStats::class,
        ];
    }
}
