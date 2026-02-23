<?php

namespace Modules\Inventory\Filament\Resources\PurchaseOrderResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;
use Filament\Notifications\Notification;
use Modules\Inventory\Models\PurchaseOrder;
use Modules\Inventory\Filament\Resources\PurchaseOrderResource;

class ViewPurchaseOrder extends BaseViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getViewHeaderActions(): array
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
                ->url(fn () => $this->getResource()::getUrl('receive', ['record' => $this->record->getKey()])),

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

            Actions\Action::make('reset_to_draft')
                ->label(__('inventory::inventory.actions.reset_to_draft'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn () => $this->record->canResetToDraft())
                ->requiresConfirmation()
                ->action(function () {
                    if ($this->record->resetToDraft()) {
                        Notification::make()
                            ->title(__('inventory::inventory.messages.order_reset_to_draft'))
                            ->success()
                            ->send();
                        $this->refreshFormData(['status']);
                    }
                }),

            Actions\Action::make('reopen')
                ->label(__('inventory::inventory.actions.reopen'))
                ->icon('heroicon-o-lock-open')
                ->color('warning')
                ->visible(fn () => $this->record->canReopenReceiving())
                ->requiresConfirmation()
                ->modalDescription(__('inventory::inventory.messages.reopen_confirmation'))
                ->action(function () {
                    if ($this->record->reopenReceiving()) {
                        Notification::make()
                            ->title(__('inventory::inventory.messages.order_reopened'))
                            ->success()
                            ->send();
                        $this->refreshFormData(['status']);
                    }
                }),

            Actions\Action::make('reverse_receiving')
                ->label(__('inventory::inventory.actions.reverse_receiving'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn () => $this->record->canReverseReceiving())
                ->requiresConfirmation()
                ->modalHeading(__('inventory::inventory.actions.reverse_receiving'))
                ->modalDescription(__('inventory::inventory.messages.reverse_confirmation'))
                ->action(function () {
                    if ($this->record->reverseReceiving()) {
                        Notification::make()
                            ->title(__('inventory::inventory.messages.receiving_reversed'))
                            ->success()
                            ->send();
                        $this->refreshFormData(['status']);
                    }
                }),
        ];
    }
}
