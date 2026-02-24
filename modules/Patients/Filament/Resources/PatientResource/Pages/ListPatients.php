<?php

namespace Modules\Patients\Filament\Resources\PatientResource\Pages;

use Modules\Patients\Filament\Resources\PatientResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPatients extends BaseListRecords
{
    protected static string $resource = PatientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PatientResource\Widgets\PatientStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('patients::patients.filters.all'))
                ->badge($this->getModel()::count()),

            'active' => Tab::make(__('patients::patients.filters.active'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'active'))
                ->badge($this->getModel()::where('status', 'active')->count())
                ->badgeColor('success'),

            'new_this_month' => Tab::make(__('patients::patients.filters.new_this_month'))
                ->modifyQueryUsing(fn (Builder $query) => $query->newThisMonth())
                ->badge($this->getModel()::newThisMonth()->count())
                ->badgeColor('info'),

            'inactive' => Tab::make(__('patients::patients.filters.inactive_days', ['days' => 90]))
                ->modifyQueryUsing(fn (Builder $query) => $query->inactiveVisitors(90))
                ->badge($this->getModel()::inactiveVisitors(90)->count())
                ->badgeColor('warning'),
        ];
    }
}
