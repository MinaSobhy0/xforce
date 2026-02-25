<?php

namespace Modules\Inventory\Filament\Resources\StockMovementResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Modules\Inventory\Filament\Resources\StockMovementResource;
use Modules\Inventory\Filament\Resources\PurchaseOrderResource;
use Modules\Inventory\Filament\Resources\InventoryAdjustmentResource;

class ViewStockMovement extends ViewRecord
{
    protected static string $resource = StockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_source')
                ->label(__('inventory::inventory.actions.view_source'))
                ->icon('heroicon-o-document')
                ->color('info')
                ->visible(fn () => $this->record->reference_type && $this->record->reference_id && $this->getSourceUrl() !== null)
                ->url(fn () => $this->getSourceUrl()),
        ];
    }

    protected function getSourceUrl(): ?string
    {
        $referenceType = $this->record->reference_type;
        $referenceId = $this->record->reference_id;

        if (!$referenceType || !$referenceId) {
            return null;
        }

        return match ($referenceType) {
            'purchase_order' => PurchaseOrderResource::getUrl('view', ['record' => $referenceId]),
            'inventory_adjustment' => InventoryAdjustmentResource::getUrl('view', ['record' => $referenceId]),
            default => null,
        };
    }
}
