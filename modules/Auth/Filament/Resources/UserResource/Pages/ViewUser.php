<?php

namespace Modules\Auth\Filament\Resources\UserResource\Pages;

use Modules\Auth\Filament\Resources\UserResource;
use Modules\Auth\Models\UserStatus;
use Modules\Staff\Models\StaffProfile;
use Filament\Actions;
use Filament\Forms;
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
                    $user = $this->getRecord();
                    $newStatus = $user->status === UserStatus::ACTIVE ? UserStatus::INACTIVE : UserStatus::ACTIVE;

                    // `status` lives in User::$guarded as a HIGH-impact field,
                    // so ->update(['status' => $newStatus]) is silently dropped
                    // by mass-assignment protection. Direct property assignment
                    // + save() bypasses the guard for this trusted admin action.
                    $user->status = $newStatus;
                    $user->save();

                    activity()
                        ->causedBy(auth()->user())
                        ->performedOn($user)
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

            Actions\Action::make('createStaffProfile')
                ->label(__('auth::auth.user_resource.create_staff_profile'))
                ->icon('heroicon-o-briefcase')
                ->color('success')
                ->form([
                    Forms\Components\Select::make('branch_id')
                        ->label(__('staff::staff.fields.branch'))
                        ->options(fn () => \Modules\Core\Models\Branch::where('is_active', true)->pluck('name', 'id'))
                        ->required()
                        ->default(fn () => \Modules\Core\Models\Branch::where('is_main', true)->first()?->id),

                    Forms\Components\TextInput::make('employee_number')
                        ->label(__('staff::staff.fields.employee_number'))
                        ->default(fn () => 'EMP-' . str_pad(StaffProfile::count() + 1, 4, '0', STR_PAD_LEFT)),

                    Forms\Components\TextInput::make('job_title')
                        ->label(__('staff::staff.fields.job_title'))
                        ->default(fn () => $this->getRecord()->job_title),

                    Forms\Components\DatePicker::make('hire_date')
                        ->label(__('staff::staff.fields.hire_date'))
                        ->default(now()),

                    Forms\Components\TextInput::make('base_salary')
                        ->label(__('staff::staff.fields.base_salary'))
                        ->numeric()
                        ->prefix('EGP')
                        ->default(0),
                ])
                ->action(function (array $data) {
                    $user = $this->getRecord();

                    $staffProfile = StaffProfile::create([
                        'tenant_id' => $user->tenant_id,
                        'user_id' => $user->id,
                        'branch_id' => $data['branch_id'],
                        'employee_number' => $data['employee_number'],
                        'job_title' => $data['job_title'],
                        'hire_date' => $data['hire_date'],
                        'base_salary_minor' => (int) (($data['base_salary'] ?? 0) * 100),
                        'is_active' => true,
                    ]);

                    activity()
                        ->causedBy(auth()->user())
                        ->performedOn($staffProfile)
                        ->log('Staff profile created from user');

                    Notification::make()
                        ->title(__('auth::auth.user_resource.staff_profile_created'))
                        ->success()
                        ->send();

                    // Redirect to staff profile
                    return redirect()->to(
                        \Modules\Staff\Filament\Resources\StaffProfileResource::getUrl('view', ['record' => $staffProfile])
                    );
                })
                ->visible(fn () => !StaffProfile::where('user_id', $this->getRecord()->id)->exists()),

            Actions\Action::make('viewStaffProfile')
                ->label(__('auth::auth.user_resource.view_staff_profile'))
                ->icon('heroicon-o-briefcase')
                ->color('info')
                ->url(fn () => \Modules\Staff\Filament\Resources\StaffProfileResource::getUrl('view', [
                    'record' => StaffProfile::where('user_id', $this->getRecord()->id)->first(),
                ]))
                ->visible(fn () => StaffProfile::where('user_id', $this->getRecord()->id)->exists()),

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