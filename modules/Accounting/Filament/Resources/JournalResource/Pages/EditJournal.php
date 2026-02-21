<?php

namespace Modules\Accounting\Filament\Resources\JournalResource\Pages;

use Modules\Accounting\Filament\Resources\JournalResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditJournal extends BaseEditRecord
{
    protected static string $resource = JournalResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->entries()->count() === 0),
        ];
    }
}
