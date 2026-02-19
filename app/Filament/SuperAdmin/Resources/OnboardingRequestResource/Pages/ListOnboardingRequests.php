<?php

namespace App\Filament\SuperAdmin\Resources\OnboardingRequestResource\Pages;

use App\Filament\SuperAdmin\Resources\OnboardingRequestResource;
use App\Models\OnboardingRequest;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOnboardingRequests extends ListRecords
{
    protected static string $resource = OnboardingRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Manual Signup'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('Pending')
                ->badge(OnboardingRequest::pending()->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'pending')),

            'in_progress' => Tab::make('In Progress')
                ->badge(OnboardingRequest::inProgress()->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'in_progress')),

            'completed' => Tab::make('Completed')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereIn('status', ['approved', 'provisioned'])),

            'rejected' => Tab::make('Rejected')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'rejected')),

            'all' => Tab::make('All'),
        ];
    }
}
