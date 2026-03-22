<?php

namespace Modules\Billing\Filament\Resources\InvoiceResource\Pages;

use Modules\Billing\Filament\Resources\InvoiceResource;
use Modules\Billing\Models\Invoice;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class EditInvoice extends BaseEditRecord
{
    protected static string $resource = InvoiceResource::class;

    /**
     * SECURITY: Prevent editing non-draft invoices by aborting the request.
     * This is more secure than redirect-based protection.
     */
    public static function canEdit(Model $record): bool
    {
        return $record->isEditable();
    }

    /**
     * SECURITY: Authorize access to the page.
     * Abort with 403 if invoice is not editable to prevent direct URL access.
     */
    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        // SECURITY: Abort if invoice is not editable (paid, issued, cancelled, etc.)
        abort_unless($this->record->isEditable(), 403, 'This invoice cannot be edited.');
    }

    protected function getEditHeaderActions(): array
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
        // Additional check (belt and suspenders with authorizeAccess)
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
