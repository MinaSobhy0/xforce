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
