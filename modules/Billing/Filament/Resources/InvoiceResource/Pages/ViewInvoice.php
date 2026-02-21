<?php

namespace Modules\Billing\Filament\Resources\InvoiceResource\Pages;

use Modules\Billing\Filament\Resources\InvoiceResource;
use Modules\Billing\Models\Invoice;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;
use Filament\Notifications\Notification;

class ViewInvoice extends BaseViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->isEditable()),

            Actions\Action::make('issue')
                ->label('Issue Invoice')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->requiresConfirmation()
                ->modalDescription('This will issue the invoice and send it to the customer. This action cannot be undone.')
                ->visible(fn () => $this->record->isDraft())
                ->action(function () {
                    $this->record->issue();
                    Notification::make()
                        ->title('Invoice issued successfully')
                        ->success()
                        ->send();
                    $this->refreshFormData(['status', 'issued_at']);
                }),

            Actions\Action::make('record_payment')
                ->label('Record Payment')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn () => $this->record->canRecordPayment())
                ->url(fn () => $this->getResource()::getUrl('record-payment', ['record' => $this->record])),

            Actions\Action::make('print')
                ->label('Print')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => '#') // TODO: Implement print route
                ->openUrlInNewTab(),

            Actions\Action::make('cancel')
                ->label('Cancel Invoice')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Are you sure you want to cancel this invoice? This action cannot be undone.')
                ->visible(fn () => $this->record->canTransitionTo(Invoice::STATUS_CANCELLED))
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->label('Cancellation Reason')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->record->cancel($data['reason']);
                    Notification::make()
                        ->title('Invoice cancelled')
                        ->success()
                        ->send();
                    $this->refreshFormData(['status', 'cancelled_at', 'cancellation_reason']);
                }),
        ];
    }

    public function getRelationManagers(): array
    {
        return [
            InvoiceResource\RelationManagers\LinesRelationManager::class,
            InvoiceResource\RelationManagers\PaymentsRelationManager::class,
        ];
    }
}
