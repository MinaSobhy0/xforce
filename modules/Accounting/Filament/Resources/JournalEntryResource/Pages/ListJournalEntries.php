<?php

namespace Modules\Accounting\Filament\Resources\JournalEntryResource\Pages;

use Modules\Accounting\Filament\Resources\JournalEntryResource;
use Modules\Accounting\Models\JournalEntry;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListJournalEntries extends BaseListRecords
{
    protected static string $resource = JournalEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),

            'draft' => Tab::make('Draft')
                ->modifyQueryUsing(fn (Builder $query) => $query->draft())
                ->badge(JournalEntry::draft()->count())
                ->badgeColor('gray'),

            'posted' => Tab::make('Posted')
                ->modifyQueryUsing(fn (Builder $query) => $query->posted())
                ->badge(JournalEntry::posted()->count())
                ->badgeColor('success'),
        ];
    }
}
