<?php

namespace Modules\Inventory\Filament\Resources\StockTransferResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use App\Filament\Resources\Pages\BaseViewRecord;
use Modules\Inventory\Filament\Resources\StockTransferResource;
use Modules\Inventory\Models\StockTransfer;

class ViewStockTransfer extends BaseViewRecord
{
    protected static string $resource = StockTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->canEdit()),

            Actions\Action::make('confirm')
                ->label(__('inventory::inventory.actions.confirm'))
                ->icon('heroicon-o-check')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->canConfirm())
                ->action(function () {
                    if ($this->record->confirm()) {
                        Notification::make()
                            ->title(__('inventory::inventory.messages.transfer_confirmed'))
                            ->success()
                            ->send();
                        $this->refreshFormData(['status']);
                    }
                }),

            Actions\Action::make('process')
                ->label(__('inventory::inventory.actions.process'))
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription(__('inventory::inventory.messages.process_transfer_confirmation'))
                ->visible(fn () => $this->record->canProcess())
                ->action(function () {
                    if ($this->record->process()) {
                        Notification::make()
                            ->title(__('inventory::inventory.messages.transfer_completed'))
                            ->success()
                            ->send();
                        $this->refreshFormData(['status', 'effective_date']);
                    }
                }),

            Actions\Action::make('cancel')
                ->label(__('inventory::inventory.actions.cancel'))
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->canCancel())
                ->action(function () {
                    if ($this->record->cancel()) {
                        Notification::make()
                            ->title(__('inventory::inventory.messages.transfer_cancelled'))
                            ->success()
                            ->send();
                        $this->refreshFormData(['status']);
                    }
                }),
        ];
    }
}
