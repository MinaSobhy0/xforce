<?php

namespace Modules\Billing\Filament\Resources\InvoiceResource\Pages;

use Modules\Billing\Filament\Resources\InvoiceResource;
use Modules\Billing\Models\Invoice;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),

            Actions\Action::make('issue')
                ->label('Issue Invoice')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->isDraft())
                ->action(function () {
                    $this->record->issue();
                    Notification::make()
                        ->title('Invoice issued successfully')
                        ->success()
                        ->send();
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                }),

            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->isDraft()),
        ];
    }

    protected function afterSave(): void
    {
        // Recalculate totals after editing
        $this->record->recalculateTotals();
    }

    protected function beforeFill(): void
    {
        // Prevent editing non-draft invoices
        if (!$this->record->isEditable()) {
            Notification::make()
                ->title('Invoice cannot be edited')
                ->body('Only draft invoices can be edited.')
                ->warning()
                ->send();

            $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
        }
    }
}
