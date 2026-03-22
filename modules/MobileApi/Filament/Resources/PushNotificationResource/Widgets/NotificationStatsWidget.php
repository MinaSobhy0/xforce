<?php

namespace Modules\MobileApi\Filament\Resources\PushNotificationResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\MobileApi\Models\DeviceToken;
use Modules\MobileApi\Models\PushNotification;

class NotificationStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $today = now()->startOfDay();

        return [
            Stat::make(__('Total Sent'), PushNotification::where('status', 'sent')->count())
                ->description(__('Successfully sent'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make(__('Failed'), PushNotification::where('status', 'failed')->count())
                ->description(__('Delivery failed'))
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),

            Stat::make(__('Sent Today'), PushNotification::where('created_at', '>=', $today)->count())
                ->description(__('Notifications today'))
                ->descriptionIcon('heroicon-m-paper-airplane')
                ->color('info'),

            Stat::make(__('Active Devices'), DeviceToken::where('is_active', true)->count())
                ->description(__('Registered devices'))
                ->descriptionIcon('heroicon-m-device-phone-mobile')
                ->color('warning'),
        ];
    }
}
