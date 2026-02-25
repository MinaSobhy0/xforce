<?php

namespace Modules\Inventory\Filament\Resources\StockMovementResource\Pages;

use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Modules\Inventory\Filament\Resources\StockMovementResource;
use Modules\Inventory\Filament\Resources\PurchaseOrderResource;
use Modules\Inventory\Filament\Resources\InventoryAdjustmentResource;
use Modules\Inventory\Models\StockLevel;
use Modules\Inventory\Models\StockMovement;

class ViewStockMovement extends ViewRecord
{
    protected static string $resource = StockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reverse')
                ->label(__('inventory::inventory.actions.reverse_movement'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn () => $this->canReverse())
                ->requiresConfirmation()
                ->modalHeading(__('inventory::inventory.actions.reverse_movement'))
                ->modalDescription(__('inventory::inventory.messages.reverse_movement_confirmation'))
                ->form([
                    Forms\Components\TextInput::make('quantity')
                        ->label(__('inventory::inventory.fields.quantity'))
                        ->numeric()
                        ->default(fn () => abs($this->record->quantity))
                        ->minValue(1)
                        ->maxValue(fn () => abs($this->record->quantity))
                        ->required(),
                    Forms\Components\Textarea::make('notes')
                        ->label(__('inventory::inventory.fields.notes'))
                        ->default(fn () => __('inventory::inventory.messages.reversal_of', ['id' => $this->record->id])),
                ])
                ->action(function (array $data) {
                    $this->reverseMovement((int) $data['quantity'], $data['notes']);
                }),

            Actions\Action::make('view_source')
                ->label(__('inventory::inventory.actions.view_source'))
                ->icon('heroicon-o-document')
                ->color('info')
                ->visible(fn () => $this->record->reference_type && $this->record->reference_id && $this->getSourceUrl() !== null)
                ->url(fn () => $this->getSourceUrl()),
        ];
    }

    protected function canReverse(): bool
    {
        // Can reverse incoming or outgoing movements (not adjustments that were already reversals)
        $reversibleTypes = [
            StockMovement::TYPE_IN,
            StockMovement::TYPE_OUT,
            StockMovement::TYPE_PURCHASE_RECEIVE,
            StockMovement::TYPE_APPOINTMENT_CONSUME,
            StockMovement::TYPE_RETURN,
            StockMovement::TYPE_WASTE,
        ];

        return in_array($this->record->movement_type, $reversibleTypes);
    }

    protected function reverseMovement(int $quantity, ?string $notes): void
    {
        $stockLevel = StockLevel::where('product_id', $this->record->product_id)
            ->where('branch_id', $this->record->branch_id)
            ->first();

        if (!$stockLevel) {
            Notification::make()
                ->title(__('inventory::inventory.messages.stock_level_not_found'))
                ->danger()
                ->send();
            return;
        }

        // Reverse: if original was incoming, we decrease; if outgoing, we increase
        if ($this->record->isIncoming()) {
            $stockLevel->decrease(
                $quantity,
                StockMovement::TYPE_ADJUSTMENT,
                'movement_reversal',
                $this->record->id,
                $notes
            );
        } else {
            $stockLevel->increase(
                $quantity,
                StockMovement::TYPE_ADJUSTMENT,
                'movement_reversal',
                $this->record->id,
                $notes
            );
        }

        Notification::make()
            ->title(__('inventory::inventory.messages.movement_reversed'))
            ->success()
            ->send();

        $this->redirect(StockMovementResource::getUrl('index'));
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
