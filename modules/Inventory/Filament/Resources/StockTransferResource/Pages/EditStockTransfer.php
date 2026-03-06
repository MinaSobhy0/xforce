<?php

namespace Modules\Inventory\Filament\Resources\StockTransferResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use App\Filament\Resources\Pages\BaseEditRecord;
use Modules\Inventory\Filament\Resources\StockTransferResource;
use Modules\Inventory\Models\StockTransfer;

class EditStockTransfer extends BaseEditRecord
{
    protected static string $resource = StockTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
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
                        $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                    }
                }),
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->status === StockTransfer::STATUS_DRAFT),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
