<?php

namespace Modules\Core\Resources\TenantResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Core\Models\Tenant;

class TenantUsageWidget extends BaseWidget
{
    public ?Tenant $record = null;

    protected function getStats(): array
    {
        if (!$this->record) {
            return [];
        }

        $userCount = $this->record->users()->count();
        $userPercentage = $this->record->max_users > 0
            ? round(($userCount / $this->record->max_users) * 100, 1)
            : 0;

        // Mock storage usage - in real implementation, this would come from actual usage tracking
        $storageUsed = rand(100, $this->record->max_storage_mb);
        $storagePercentage = $this->record->max_storage_mb > 0
            ? round(($storageUsed / $this->record->max_storage_mb) * 100, 1)
            : 0;

        // Mock patient count - in real implementation, this would come from tenant database
        $patientCount = rand(50, min($this->record->max_patients, 500));
        $patientPercentage = $this->record->max_patients > 0
            ? round(($patientCount / $this->record->max_patients) * 100, 1)
            : 0;

        return [
            Stat::make(__('Users'), $userCount . ' / ' . $this->record->max_users)
                ->description($userPercentage . '% ' . __('of limit'))
                ->descriptionIcon('heroicon-m-users')
                ->color($userPercentage > 90 ? 'danger' : ($userPercentage > 75 ? 'warning' : 'success')),

            Stat::make(__('Patients'), number_format($patientCount) . ' / ' . number_format($this->record->max_patients))
                ->description($patientPercentage . '% ' . __('of limit'))
                ->descriptionIcon('heroicon-m-user-group')
                ->color($patientPercentage > 90 ? 'danger' : ($patientPercentage > 75 ? 'warning' : 'success')),

            Stat::make(__('Storage'), number_format($storageUsed) . ' MB / ' . number_format($this->record->max_storage_mb) . ' MB')
                ->description($storagePercentage . '% ' . __('of limit'))
                ->descriptionIcon('heroicon-m-server-stack')
                ->color($storagePercentage > 90 ? 'danger' : ($storagePercentage > 75 ? 'warning' : 'success')),

            Stat::make(__('Subscription'), $this->record->subscription_expires_at?->format('M d, Y') ?? __('No expiry'))
                ->description($this->record->subscription_expires_at
                    ? ($this->record->subscription_expires_at->isPast()
                        ? __('Expired')
                        : $this->record->subscription_expires_at->diffForHumans())
                    : __('Lifetime'))
                ->descriptionIcon('heroicon-m-calendar')
                ->color($this->record->subscription_expires_at?->isPast() ? 'danger' : 'success'),
        ];
    }

    protected static ?string $pollingInterval = '30s';
}