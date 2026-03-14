<?php

namespace App\Filament\SuperAdmin\Resources\PlatformInvoiceResource\Pages;

use App\Filament\SuperAdmin\Resources\PlatformInvoiceResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use App\Filament\Resources\Pages\BaseViewRecord;

class ViewPlatformInvoice extends BaseViewRecord
{
    protected static string $resource = PlatformInvoiceResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\Action::make('markPaid')
                ->label('Mark Paid')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => in_array($this->record->status, ['pending', 'overdue']))
                ->form([
                    Forms\Components\TextInput::make('payment_reference')
                        ->label('Payment Reference'),
                ])
                ->action(function (array $data) {
                    $this->record->markAsPaid($data['payment_reference'] ?? null);
                    Notification::make()
                        ->title('Invoice marked as paid')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('sendReminder')
                ->label('Send Reminder')
                ->icon('heroicon-o-envelope')
                ->color('warning')
                ->visible(fn () => in_array($this->record->status, ['pending', 'overdue']))
                ->action(function () {
                    Notification::make()
                        ->title('Payment reminder sent')
                        ->success()
                        ->send();
                }),

            Actions\EditAction::make(),
        ];
    }
}
