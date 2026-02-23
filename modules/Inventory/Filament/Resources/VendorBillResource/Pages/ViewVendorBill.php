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
                ->label('Record Payment')
                ->icon('heroicon-o-banknotes')
                ->color('info')
                ->visible(fn () => $this->record->canRecordPayment())
                ->form([
                    \Filament\Forms\Components\TextInput::make('amount')
                        ->label('Amount')
                        ->numeric()
                        ->required()
                        ->prefix(current_currency())
                        ->default(fn () => $this->record->remaining_minor / 100),

                    \Filament\Forms\Components\Select::make('payment_type')
                        ->label('Payment Method')
                        ->options([
                            'cash' => 'Cash',
                            'bank' => 'Bank Transfer',
                        ])
                        ->default('cash')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $amountMinor = (int) ($data['amount'] * 100);
                    $this->record->recordPayment($amountMinor);

                    // Create payment journal entry
                    $accountingService = app(\Modules\Inventory\Services\InventoryAccountingService::class);
                    $accountingService->createVendorPaymentJournalEntry($this->record, $amountMinor, $data['payment_type']);

                    Notification::make()
                        ->title('Payment recorded successfully')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'paid_minor', 'paid_at']);
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
