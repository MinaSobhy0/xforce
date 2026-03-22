<?php

namespace Modules\MobileApi\Filament\Resources\PushNotificationResource\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Modules\Auth\Models\User;
use Modules\MobileApi\Filament\Resources\PushNotificationResource;
use Modules\MobileApi\Services\PushNotificationService;

class CreatePushNotification extends CreateRecord
{
    protected static string $resource = PushNotificationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // We'll handle creation manually in handleRecordCreation
        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $user = User::find($data['user_id']);

        if (! $user) {
            Notification::make()
                ->title(__('User not found'))
                ->danger()
                ->send();

            $this->halt();
        }

        $service = app(PushNotificationService::class);

        $notification = $service->sendToUser(
            user: $user,
            type: $data['type'],
            title: $data['title'],
            body: $data['body'],
            data: $data['data'] ?? [],
            checkPreferences: false, // Admin override
        );

        if (! $notification) {
            Notification::make()
                ->title(__('Failed to send notification'))
                ->body(__('User has no registered devices.'))
                ->danger()
                ->send();

            $this->halt();
        }

        if ($notification->status === 'failed') {
            Notification::make()
                ->title(__('Notification failed'))
                ->body($notification->error_message)
                ->danger()
                ->send();
        } else {
            Notification::make()
                ->title(__('Notification sent'))
                ->body(__('Push notification sent to :name', ['name' => $user->full_name]))
                ->success()
                ->send();
        }

        return $notification;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null; // We handle notifications manually
    }
}
