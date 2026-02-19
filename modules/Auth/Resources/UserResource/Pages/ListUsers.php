<?php

namespace Modules\Auth\Resources\UserResource\Pages;

use Modules\Auth\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('New User')),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('All'))
                ->badge($this->getModel()::count()),

            'active' => Tab::make(__('Active'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', true))
                ->badge($this->getModel()::where('is_active', true)->count()),

            'inactive' => Tab::make(__('Inactive'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', false))
                ->badge($this->getModel()::where('is_active', false)->count()),

            'unverified' => Tab::make(__('Unverified'))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('email_verified_at'))
                ->badge($this->getModel()::whereNull('email_verified_at')->count()),

            'admins' => Tab::make(__('Administrators'))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('roles', fn ($q) => $q->whereIn('name', ['super_admin', 'admin'])))
                ->badge($this->getModel()::whereHas('roles', fn ($q) => $q->whereIn('name', ['super_admin', 'admin']))->count()),

            'inactive_30days' => Tab::make(__('Inactive 30+ Days'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where(function ($q) {
                    $q->where('last_login_at', '<', now()->subDays(30))
                      ->orWhereNull('last_login_at');
                }))
                ->badge($this->getModel()::where(function ($q) {
                    $q->where('last_login_at', '<', now()->subDays(30))
                      ->orWhereNull('last_login_at');
                })->count()),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            UserResource\Widgets\UserStatsWidget::class,
        ];
    }
}