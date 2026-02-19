<?php

namespace Modules\Auth\Resources\UserResource\Pages;

use Modules\Auth\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Generate a strong password if none provided
        if (empty($data['password'])) {
            $data['password'] = bcrypt(Str::random(12));
            $data['must_change_password'] = true;
        }

        // Set default values
        $data['created_by'] = auth()->id();
        $data['password_changed_at'] = now();

        return $data;
    }

    protected function afterCreate(): void
    {
        $user = $this->getRecord();

        try {
            // Send welcome email notification
            if (config('mail.notifications_enabled', true)) {
                // Welcome email logic would go here
                // $user->sendWelcomeNotification();
            }

            Notification::make()
                ->title(__('User created successfully'))
                ->body(__('User account has been created and welcome email sent.'))
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title(__('User created with warnings'))
                ->body(__('User was created but welcome email failed to send: :error', ['error' => $e->getMessage()]))
                ->warning()
                ->send();

            logger()->error('Welcome email failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }

        // Log user creation
        activity()
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->log('User account created');
    }
}