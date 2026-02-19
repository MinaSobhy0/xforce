<?php

namespace Modules\Auth\Resources\UserResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Auth\Models\User;

class UserActivityWidget extends BaseWidget
{
    public ?User $record = null;

    protected function getStats(): array
    {
        if (!$this->record) {
            return [];
        }

        // Mock data - in real implementation, these would come from actual tracking
        $loginCount = rand(5, 50);
        $lastLoginDays = $this->record->last_login_at ? $this->record->last_login_at->diffInDays(now()) : null;
        $sessionCount = rand(1, 5);
        $activityCount = rand(10, 100);

        return [
            Stat::make(__('Total Logins'), number_format($loginCount))
                ->description(__('All time'))
                ->descriptionIcon('heroicon-m-arrow-right-on-rectangle')
                ->color('info'),

            Stat::make(__('Last Login'), $lastLoginDays !== null ? $lastLoginDays . ' ' . __('days ago') : __('Never'))
                ->description($this->record->last_login_at?->format('M d, Y H:i') ?? __('No login recorded'))
                ->descriptionIcon('heroicon-m-clock')
                ->color($lastLoginDays > 30 ? 'danger' : ($lastLoginDays > 7 ? 'warning' : 'success')),

            Stat::make(__('Active Sessions'), $sessionCount)
                ->description(__('Current sessions'))
                ->descriptionIcon('heroicon-m-device-phone-mobile')
                ->color('warning'),

            Stat::make(__('Activities'), number_format($activityCount))
                ->description(__('Recorded actions'))
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('primary'),
        ];
    }

    protected static ?string $pollingInterval = '30s';
}