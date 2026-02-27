<?php

namespace Modules\Inventory\Filament\Resources\VendorBillResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;
use Filament\Notifications\Notification;
use Modules\Inventory\Filament\Resources\VendorBillResource;
use Modules\Inventory\Filament\Resources\VendorBillResource\RelationManagers;
use Modules\Inventory\Models\VendorBill;

class ViewVendorBill extends BaseViewRecord
{
    protected static string $resource = VendorBillResource::class;

    public function getRelationManagers(): array
    {
        return [
            RelationManagers\LinesRelationManager::class,
            RelationManagers\PaymentsRelationManager::class,
        ];
    }

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->isDraft()),

            Actions\Action::make('validate')
                ->label('Validate')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('This will validate the bill and create journal entries. This action cannot be undone.')
                ->visible(fn () => $this->record->canValidate())
                ->action(function () {
                    if ($this->record->validate()) {
                        Notification::make()
                            ->title('Bill validated successfully')
                            ->success()
                            ->send();

                        $this->refreshFormData(['status', 'validated_at']);
                    } else {
                        Notification::make()
                            ->title('Failed to validate bill')
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('record_payment')
                ->label(__('inventory::inventory.actions.record_payment'))
                ->icon('heroicon-o-banknotes')
                ->color('info')
                ->visible(fn () => $this->record->canRecordPayment())
                ->url(fn () => $this->getResource()::getUrl('record-payment', ['record' => $this->record])),

            Actions\Action::make('reset_to_draft')
                ->label(__('inventory::inventory.actions.reset_to_draft'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription(__('inventory::inventory.messages.reset_bill_to_draft_confirmation'))
                ->visible(fn () => $this->record->canResetToDraft())
                ->action(function () {
                    if ($this->record->resetToDraft()) {
                        Notification::make()
                            ->title(__('inventory::inventory.messages.bill_reset_to_draft'))
                            ->success()
                            ->send();
                        $this->refreshFormData(['status', 'validated_at', 'journal_entry_id']);
                    } else {
                        Notification::make()
                            ->title(__('inventory::inventory.messages.bill_reset_failed'))
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('cancel')
                ->label('Cancel')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->canCancel())
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->label('Cancellation Reason')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->record->cancel($data['reason']);
                    Notification::make()
                        ->title('Bill cancelled')
                        ->success()
                        ->send();
                    $this->refreshFormData(['status', 'cancelled_at', 'cancellation_reason']);
                }),
        ];
    }
}
