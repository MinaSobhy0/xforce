<?php

namespace Modules\MobileApi\Filament\Resources\PushNotificationResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Modules\MobileApi\Filament\Resources\PushNotificationResource;

class ListPushNotifications extends ListRecords
{
    protected static string $resource = PushNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('Send Notification')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PushNotificationResource\Widgets\NotificationStatsWidget::class,
        ];
    }
}
