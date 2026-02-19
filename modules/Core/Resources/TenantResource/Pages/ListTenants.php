<?php

namespace Modules\Core\Resources\TenantResource\Pages;

use Modules\Core\Resources\TenantResource;
use XLinic\Framework\Core\Filament\BasePage;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Models\TenantStatus;

class ListTenants extends ListRecords
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('New Tenant')),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('All'))
                ->badge($this->getModel()::count()),

            'active' => Tab::make(__('Active'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', TenantStatus::ACTIVE))
                ->badge($this->getModel()::where('status', TenantStatus::ACTIVE)->count()),

            'suspended' => Tab::make(__('Suspended'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', TenantStatus::SUSPENDED))
                ->badge($this->getModel()::where('status', TenantStatus::SUSPENDED)->count()),

            'expired' => Tab::make(__('Expired'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('subscription_expires_at', '<', now()))
                ->badge($this->getModel()::where('subscription_expires_at', '<', now())->count()),

            'pending' => Tab::make(__('Pending'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', TenantStatus::PENDING))
                ->badge($this->getModel()::where('status', TenantStatus::PENDING)->count()),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return TenantResource\Widgets\TenantStatsWidget::class;
    }
}