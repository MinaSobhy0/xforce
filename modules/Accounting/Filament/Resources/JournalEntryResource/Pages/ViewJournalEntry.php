<?php

namespace Modules\Accounting\Filament\Resources\JournalEntryResource\Pages;

use Modules\Accounting\Filament\Resources\JournalEntryResource;
use Modules\Accounting\Models\JournalEntry;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;
use Filament\Notifications\Notification;

class ViewJournalEntry extends BaseViewRecord
{
    protected static string $resource = JournalEntryResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->label(__('accounting::accounting.actions.print'))
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => route('accounting.journal-entry.print', $this->record), shouldOpenInNewTab: true),

            Actions\EditAction::make()
                ->visible(fn () => $this->record->isDraft()),

            Actions\Action::make('post')
                ->label('Post Entry')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('This will post the journal entry and update account balances. This action cannot be undone.')
                ->visible(fn () => $this->record->isDraft() && $this->record->isBalanced())
                ->action(function () {
                    if ($this->record->post()) {
                        Notification::make()
                            ->title('Journal entry posted successfully')
                            ->success()
                            ->send();
                        $this->refreshFormData(['status', 'posted_at']);
                    } else {
                        Notification::make()
                            ->title('Failed to post journal entry')
                            ->body('Entry may be unbalanced or fiscal period may be closed.')
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('reset_to_draft')
                ->label(__('accounting::accounting.actions.reset_to_draft'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('accounting::accounting.actions.reset_to_draft'))
                ->modalDescription(__('accounting::accounting.messages.reset_to_draft_warning'))
                ->visible(fn () => $this->record->isPosted() && !$this->record->isReversed())
                ->action(function () {
                    if ($this->record->resetToDraft()) {
                        Notification::make()
                            ->title(__('accounting::accounting.messages.reset_to_draft_success'))
                            ->success()
                            ->send();
                        $this->refreshFormData(['status', 'posted_at']);
                    } else {
                        Notification::make()
                            ->title(__('accounting::accounting.messages.reset_to_draft_failed'))
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('reverse')
                ->label('Reverse Entry')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->isPosted() && !$this->record->isReversed())
                ->form([
                    \Filament\Forms\Components\Textarea::make('description')
                        ->label('Reversal Description')
                        ->default(fn () => 'Reversal of ' . $this->record->code)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $reversal = $this->record->reverse($data['description']);
                    if ($reversal) {
                        Notification::make()
                            ->title('Journal entry reversed successfully')
                            ->body('Reversal entry: ' . $reversal->code)
                            ->success()
                            ->send();
                        $this->redirect($this->getResource()::getUrl('view', ['record' => $reversal]));
                    }
                }),
        ];
    }
}
