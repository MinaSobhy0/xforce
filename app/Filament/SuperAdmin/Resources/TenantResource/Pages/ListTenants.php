<?php

namespace App\Filament\SuperAdmin\Resources\TenantResource\Pages;

use App\Filament\SuperAdmin\Resources\TenantResource;
use App\Filament\SuperAdmin\Resources\TenantResource\Widgets\TenantsOverviewWidget;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Models\Tenant;
use Modules\Core\Models\TenantStatus;

class ListTenants extends BaseListRecords
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make()
                ->label('Add Clinic')
                ->icon('heroicon-o-plus'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TenantsOverviewWidget::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Active')
                ->badge(Tenant::where('status', TenantStatus::ACTIVE)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', TenantStatus::ACTIVE)),

            'pending' => Tab::make('Pending')
                ->badge(Tenant::where('status', TenantStatus::PENDING)->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', TenantStatus::PENDING)),

            'suspended' => Tab::make('Suspended')
                ->badge(Tenant::where('status', TenantStatus::SUSPENDED)->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', TenantStatus::SUSPENDED)),

            'expired' => Tab::make('Expired')
                ->badge(Tenant::where('status', TenantStatus::EXPIRED)->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', TenantStatus::EXPIRED)),

            'all' => Tab::make('All')
                ->badge(Tenant::count()),
        ];
    }
}
