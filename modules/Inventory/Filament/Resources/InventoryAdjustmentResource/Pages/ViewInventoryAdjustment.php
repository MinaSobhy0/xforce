<?php

namespace Modules\Inventory\Filament\Resources\InventoryAdjustmentResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Modules\Inventory\Filament\Resources\InventoryAdjustmentResource;

class ViewInventoryAdjustment extends ViewRecord
{
    protected static string $resource = InventoryAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->isDraft()),

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

            Actions\Action::make('cancel')
                ->label(__('inventory::inventory.actions.cancel'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->canCancel())
                ->action(function () {
                    $this->record->cancel();
                    Notification::make()
                        ->title(__('inventory::inventory.messages.adjustment_cancelled'))
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                }),
        ];
    }
}
