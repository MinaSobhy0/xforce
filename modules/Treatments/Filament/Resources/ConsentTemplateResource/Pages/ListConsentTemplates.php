<?php

namespace Modules\Treatments\Filament\Resources\ConsentTemplateResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Resources\Components\Tab;
use Modules\Treatments\Filament\Resources\ConsentTemplateResource;
use Illuminate\Database\Eloquent\Builder;

class ListConsentTemplates extends BaseListRecords
{
    protected static string $resource = ConsentTemplateResource::class;

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
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', true)),
            'inactive' => Tab::make('Inactive')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', false)),
        ];
    }
}
