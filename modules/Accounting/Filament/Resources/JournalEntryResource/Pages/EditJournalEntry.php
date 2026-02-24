<?php

namespace Modules\Accounting\Filament\Resources\JournalEntryResource\Pages;

use Modules\Accounting\Filament\Resources\JournalEntryResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Notifications\Notification;

class EditJournalEntry extends BaseEditRecord
{
    protected static string $resource = JournalEntryResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),

            Actions\Action::make('post')
                ->label(__('accounting::accounting.entry_actions.post'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('This will post the journal entry and update account balances. This action cannot be undone.')
                ->visible(fn () => $this->record->isDraft() && $this->record->isBalanced())
                ->action(function () {
                    if ($this->record->post()) {
                        Notification::make()
                            ->title(__('accounting::accounting.messages.entry_posted'))
                            ->success()
                            ->send();
                        $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                    } else {
                        Notification::make()
                            ->title('Failed to post journal entry')
                            ->body('Entry may be unbalanced or fiscal period may be closed.')
                            ->danger()
                            ->send();
                    }
                }),

            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->isDraft()),
        ];
    }

    protected function beforeFill(): void
    {
        if (!$this->record->isDraft()) {
            Notification::make()
                ->title('Cannot edit posted entries')
                ->body('Only draft journal entries can be edited.')
                ->warning()
                ->send();

            $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
        }
    }

    protected function afterSave(): void
    {
        $this->record->recalculateTotals();
    }
}
