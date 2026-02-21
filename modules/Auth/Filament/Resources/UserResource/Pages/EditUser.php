<?php

namespace Modules\Auth\Filament\Resources\UserResource\Pages;

use Modules\Auth\Filament\Resources\UserResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Notifications\Notification;

class EditUser extends BaseEditRecord
{
    protected static string $resource = UserResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Update password change timestamp if password was changed
        if (!empty($data['password'])) {
            $data['password_changed_at'] = now();
        }

        // Update the updated_by field
        $data['updated_by'] = auth()->id();

        return $data;
    }

    protected function afterSave(): void
    {
        $changes = $this->getRecord()->getChanges();

        // Log significant changes
        $significantChanges = array_intersect_key($changes, array_flip([
            'first_name', 'last_name', 'email', 'status', 'email_verified_at', 'two_factor_enabled'
        ]));

        if (!empty($significantChanges)) {
            activity()
                ->causedBy(auth()->user())
                ->performedOn($this->getRecord())
                ->withProperties(['changes' => $significantChanges])
                ->log('User profile updated');
        }

        // Send notifications for specific changes
        if (array_key_exists('status', $changes)) {
            $status = $changes['status'] === 'active' ? 'activated' : 'deactivated';

            Notification::make()
                ->title(__('User account :status', ['status' => $status]))
                ->success()
                ->send();
        }

        if (array_key_exists('email', $changes)) {
            // If email changed, mark as unverified
            $this->getRecord()->update([
                'email_verified_at' => null,
            ]);

            Notification::make()
                ->title(__('Email changed'))
                ->body(__('User email has been changed. Email verification reset.'))
                ->warning()
                ->send();
        }

        if (array_key_exists('password', $changes)) {
            Notification::make()
                ->title(__('Password updated'))
                ->body(__('User password has been changed successfully.'))
                ->success()
                ->send();
        }
    }
}