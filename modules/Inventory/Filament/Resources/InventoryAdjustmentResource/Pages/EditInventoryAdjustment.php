<?php

namespace Modules\Inventory\Filament\Resources\InventoryAdjustmentResource\Pages;

use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Actions;
use Filament\Notifications\Notification;
use Modules\Inventory\Filament\Resources\InventoryAdjustmentResource;

class EditInventoryAdjustment extends BaseEditRecord
{
    protected static string $resource = InventoryAdjustmentResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Auto-load products if this is a count type and no lines exist
        if ($this->record->isDraft() && $this->record->lines()->count() === 0) {
            $this->record->loadProductsFromStock();
            $this->record->refresh();
        }

        return $data;
    }

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),

            Actions\Action::make('load_products')
                ->label(__('inventory::inventory.actions.load_products'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn () => $this->record->isDraft())
                ->requiresConfirmation()
                ->modalDescription(__('inventory::inventory.messages.load_products_confirmation'))
                ->action(function () {
                    $this->record->loadProductsFromStock();
                    Notification::make()
                        ->title(__('inventory::inventory.messages.products_loaded'))
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            Actions\Action::make('validate')
                ->label(__('inventory::inventory.actions.validate'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('inventory::inventory.actions.validate_adjustment'))
                ->modalDescription(__('inventory::inventory.messages.validate_confirmation'))
                ->visible(fn () => $this->record->canValidate())
                ->action(function () {
                    if ($this->record->validate()) {
                        Notification::make()
                            ->title(__('inventory::inventory.messages.adjustment_validated'))
                            ->success()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                    } else {
                        Notification::make()
                            ->title(__('inventory::inventory.messages.validation_failed'))
                            ->danger()
                            ->send();
                    }
                }),

            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->isDraft()),
        ];
    }
}
