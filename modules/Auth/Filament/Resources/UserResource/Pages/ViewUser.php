<?php

namespace Modules\Auth\Filament\Resources\UserResource\Pages;

use Modules\Auth\Filament\Resources\UserResource;
use Modules\Auth\Models\UserStatus;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Hash;

class ViewUser extends BaseViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make('impersonate')
                ->label(__('Login as User'))
                ->icon('heroicon-o-user')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription(__('You will be logged in as this user. You can return to your account anytime.'))
                ->action(function () {
                    // Impersonation logic would go here
                    // session(['impersonator_id' => auth()->id()]);
                    // auth()->login($this->getRecord());

                    Notification::make()
                        ->title(__('Now impersonating user'))
                        ->body(__('You are now logged in as :name', ['name' => $this->getRecord()->name]))
                        ->success()
                        ->send();
                })
                ->visible(fn () => $this->getRecord()->status === UserStatus::ACTIVE && !$this->getRecord()->hasRole('super_admin')),

            Actions\Action::make('resetPassword')
                ->label(__('Reset Password'))
                ->icon('heroicon-o-key')
                ->color('danger')
                ->form([
                    \Filament\Forms\Components\TextInput::make('new_password')
                        ->label(__('New Password'))
                        ->password()
                        ->required()
                        ->minLength(8)
                        ->confirmed(),

                    \Filament\Forms\Components\TextInput::make('new_password_confirmation')
                        ->label(__('Confirm New Password'))
                        ->password()
                        ->required()
                        ->dehydrated(false),

                    \Filament\Forms\Components\Toggle::make('notify_user')
                        ->label(__('Notify User'))
                        ->helperText(__('Send email notification to user'))
                        ->default(true),

                    \Filament\Forms\Components\Toggle::make('force_change')
                        ->label(__('Force Password Change'))
                        ->helperText(__('User must change password on next login'))
                        ->default(true),
                ])
                ->action(function (array $data) {
                    $this->getRecord()->update([
                        'password' => Hash::make($data['new_password']),
                        'must_change_password' => $data['force_change'],
                        'password_changed_at' => now(),
                    ]);

                    if ($data['notify_user']) {
                        // Send password reset notification
                        // $this->getRecord()->sendPasswordResetNotification();
                    }

                    activity()
                        ->causedBy(auth()->user())
                        ->performedOn($this->getRecord())
                        ->log('Password reset by administrator');

                    Notification::make()
                        ->title(__('Password reset'))
                        ->body(__('User password has been reset successfully.'))
                        ->success()
                        ->send();
                }),

            Actions\Action::make('toggleActivation')
                ->label(fn () => $this->getRecord()->status === UserStatus::ACTIVE ? __('Deactivate') : __('Activate'))
                ->icon(fn () => $this->getRecord()->status === UserStatus::ACTIVE ? 'heroicon-o-pause' : 'heroicon-o-play')
                ->color(fn () => $this->getRecord()->status === UserStatus::ACTIVE ? 'warning' : 'success')
                ->requiresConfirmation()
                ->modalDescription(fn () => $this->getRecord()->status === UserStatus::ACTIVE
                    ? __('This will deactivate the user account and prevent login.')
                    : __('This will activate the user account and allow login.'))
                ->action(function () {
                    $newStatus = $this->getRecord()->status === UserStatus::ACTIVE ? UserStatus::INACTIVE : UserStatus::ACTIVE;

                    $this->getRecord()->update(['status' => $newStatus]);

                    activity()
                        ->causedBy(auth()->user())
                        ->performedOn($this->getRecord())
                        ->log($newStatus === UserStatus::ACTIVE ? 'User account activated' : 'User account deactivated');

                    Notification::make()
                        ->title($newStatus === UserStatus::ACTIVE ? __('User activated') : __('User deactivated'))
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),

            Actions\Action::make('verifyEmail')
                ->label(__('Verify Email'))
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->action(function () {
                    $this->getRecord()->update([
                        'email_verified_at' => now(),
                    ]);

                    activity()
                        ->causedBy(auth()->user())
                        ->performedOn($this->getRecord())
                        ->log('Email verified by administrator');

                    Notification::make()
                        ->title(__('Email verified'))
                        ->success()
                        ->send();

                    $this->refreshFormData(['email_verified_at']);
                })
                ->visible(fn () => !$this->getRecord()->email_verified_at),

            Actions\Action::make('disable2FA')
                ->label(__('Disable 2FA'))
                ->icon('heroicon-o-shield-exclamation')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(__('This will disable two-factor authentication for this user.'))
                ->action(function () {
                    $this->getRecord()->update([
                        'two_factor_enabled' => false,
                        'two_factor_secret' => null,
                        'two_factor_backup_codes' => null,
                        'two_factor_confirmed_at' => null,
                    ]);

                    activity()
                        ->causedBy(auth()->user())
                        ->performedOn($this->getRecord())
                        ->log('Two-factor authentication disabled by administrator');

                    Notification::make()
                        ->title(__('Two-factor authentication disabled'))
                        ->success()
                        ->send();

                    $this->refreshFormData(['two_factor_enabled']);
                })
                ->visible(fn () => $this->getRecord()->two_factor_enabled),

            Actions\DeleteAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            UserResource\Widgets\UserActivityWidget::class,
        ];
    }
}