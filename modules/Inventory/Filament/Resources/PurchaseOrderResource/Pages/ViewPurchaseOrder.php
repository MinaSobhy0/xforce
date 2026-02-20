<?php

namespace Modules\Inventory\Filament\Resources\PurchaseOrderResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Modules\Inventory\Models\PurchaseOrder;
use Modules\Inventory\Filament\Resources\PurchaseOrderResource;

class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->isEditable()),

            Actions\Action::make('send')
                ->label(__('inventory::inventory.actions.send'))
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->visible(fn () => $this->record->canTransitionTo(PurchaseOrder::STATUS_SENT))
                ->requiresConfirmation()
                ->action(function () {
                    if ($this->record->send(auth()->id())) {
                        Notification::make()
                            ->title(__('inventory::inventory.messages.order_sent'))
                            ->success()
                            ->send();
                        $this->refreshFormData(['status']);
                    }
                }),

            Actions\Action::make('receive')
                ->label(__('inventory::inventory.actions.receive'))
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('success')
                ->visible(fn () => $this->record->canReceive())
                ->url(fn () => $this->getResource()::getUrl('receive', ['record' => $this->record])),

            Actions\Action::make('cancel')
                ->label(__('inventory::inventory.actions.cancel'))
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn () => $this->record->canTransitionTo(PurchaseOrder::STATUS_CANCELLED))
                ->requiresConfirmation()
                ->action(function () {
                    if ($this->record->cancel()) {
                        Notification::make()
                            ->title(__('inventory::inventory.messages.order_cancelled'))
                            ->success()
                            ->send();
                        $this->refreshFormData(['status']);
                    }
                }),
        ];
    }
}
