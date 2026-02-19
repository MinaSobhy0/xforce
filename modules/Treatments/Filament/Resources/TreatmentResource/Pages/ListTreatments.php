<?php

namespace Modules\Treatments\Filament\Resources\TreatmentResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Modules\Treatments\Filament\Resources\TreatmentResource;
use Illuminate\Database\Eloquent\Builder;

class ListTreatments extends ListRecords
{
    protected static string $resource = TreatmentResource::class;

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
            'online' => Tab::make('Online Booking')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_bookable_online', true)->where('is_active', true)),
            'requires_consent' => Tab::make('Requires Consent')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('requires_consent', true)),
            'inactive' => Tab::make('Inactive')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', false)),
        ];
    }
}
