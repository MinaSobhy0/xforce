<?php

namespace Modules\Accounting\Filament\Resources\JournalResource\Pages;

use Modules\Accounting\Filament\Resources\JournalResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;

class ListJournals extends BaseListRecords
{
    protected static string $resource = JournalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
