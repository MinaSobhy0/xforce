<?php

namespace Modules\Treatments\Filament\Resources\ConsentTemplateResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Modules\Treatments\Filament\Resources\ConsentTemplateResource;
use Illuminate\Database\Eloquent\Builder;

class ListConsentTemplates extends ListRecords
{
    protected static string $resource = ConsentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', true)),
            'inactive' => Tab::make('Inactive')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', false)),
        ];
    }
}
