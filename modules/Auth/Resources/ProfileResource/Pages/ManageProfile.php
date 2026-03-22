<?php

namespace Modules\Auth\Resources\ProfileResource\Pages;

use Modules\Auth\Resources\ProfileResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Registered;
use Modules\Auth\Models\User;

class ManageProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = ProfileResource::class;

    protected static string $view = 'filament.pages.manage-profile';

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    public function getTitle(): string
    {
        return __('My Profile');
    }

    public function getHeading(): string
    {
        return __('My Profile');
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->fillForms();
    }

    protected function fillForms(): void
    {
        $user = auth()->user();

        $this->data = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'date_of_birth' => $user->date_of_birth,
            'gender' => $user->gender,
            'address' => $user->address,
            'avatar_url' => $user->avatar_url,
            'job_title' => $user->job_title,
            'department' => $user->department,
            'bio' => $user->bio,
            'timezone' => $user->timezone,
            'locale' => $user->locale,
        ];

        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Profile')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Basic Information')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Forms\Components\FileUpload::make('avatar_url')
                                    ->label(__('Profile Picture'))
                                    ->image()
                                    ->directory('users/avatars')
                                    ->visibility('public')
                                    ->imageEditor()
                                    ->circleCropper()
                                    ->nullable()
                                    ->columnSpanFull(),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label(__('Full Name'))
                                            ->required()
                                            ->maxLength(255),

                                        // SECURITY: Email changes require password verification
                                        Forms\Components\TextInput::make('email')
                                            ->label(__('Email Address'))
                                            ->email()
                                            ->required()
                                            ->unique(User::class, 'email', ignoreRecord: fn () => auth()->user())
                                            ->maxLength(255)
                                            ->helperText(fn () => __('Changing email requires password verification')),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('phone')
                                            ->label(__('Phone Number'))
                                            ->tel()
                                            ->nullable()
                                            ->maxLength(20),

                                        Forms\Components\DatePicker::make('date_of_birth')
                                            ->label(__('Date of Birth'))
                                            ->nullable()
                                            ->maxDate(now()->subYears(16)),
                                    ]),

                                Forms\Components\Select::make('gender')
                                    ->label(__('Gender'))
                                    ->options([
                                        'male' => __('Male'),
                                        'female' => __('Female'),
                                        'other' => __('Other'),
                                    ])
                                    ->nullable(),

                                Forms\Components\Textarea::make('address')
                                    ->label(__('Address'))
                                    ->nullable()
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Professional')
                            ->icon('heroicon-o-briefcase')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('job_title')
                                            ->label(__('Job Title'))
                                            ->nullable()
                                            ->maxLength(100),

                                        Forms\Components\TextInput::make('department')
                                            ->label(__('Department'))
                                            ->nullable()
                                            ->maxLength(100),
                                    ]),

                                Forms\Components\Textarea::make('bio')
                                    ->label(__('Biography'))
                                    ->nullable()
                                    ->rows(4)
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Preferences')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('timezone')
                                            ->label(__('Timezone'))
                                            ->options([
                                                'Africa/Cairo' => 'Cairo (GMT+2)',
                                                'Asia/Riyadh' => 'Riyadh (GMT+3)',
                                                'Asia/Dubai' => 'Dubai (GMT+4)',
                                                'Asia/Kuwait' => 'Kuwait (GMT+3)',
                                                'Asia/Qatar' => 'Qatar (GMT+3)',
                                            ])
                                            ->default('Africa/Cairo')
                                            ->searchable(),

                                        Forms\Components\Select::make('locale')
                                            ->label(__('Language'))
                                            ->options([
                                                'ar' => __('Arabic'),
                                                'en' => __('English'),
                                            ])
                                            ->default('en'),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Security')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        // SECURITY: Current password required for email or password changes
                                        Forms\Components\TextInput::make('current_password')
                                            ->label(__('Current Password'))
                                            ->password()
                                            ->revealable()
                                            ->dehydrated(false)
                                            ->rules(['current_password'])
                                            ->requiredWith('new_password')
                                            ->helperText(__('Required to change email or password')),

                                        Forms\Components\TextInput::make('new_password')
                                            ->label(__('New Password'))
                                            ->password()
                                            ->revealable()
                                            ->nullable()
                                            ->confirmed()
                                            ->minLength(8)
                                            ->dehydrated(false)
                                            ->live(),
                                    ]),

                                Forms\Components\TextInput::make('new_password_confirmation')
                                    ->label(__('Confirm New Password'))
                                    ->password()
                                    ->revealable()
                                    ->nullable()
                                    ->dehydrated(false)
                                    ->visible(fn (Forms\Get $get) => filled($get('new_password'))),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label(__('Save Changes'))
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $user = auth()->user();

        // Check if email is being changed
        $emailChanged = $user->email !== ($data['email'] ?? $user->email);

        // SECURITY: Require current password for email changes (account takeover prevention)
        if ($emailChanged) {
            $currentPassword = $data['current_password'] ?? null;

            if (empty($currentPassword)) {
                Notification::make()
                    ->title(__('Password required'))
                    ->body(__('You must enter your current password to change your email address.'))
                    ->danger()
                    ->send();
                return;
            }

            if (!Hash::check($currentPassword, $user->password)) {
                Notification::make()
                    ->title(__('Invalid password'))
                    ->body(__('The current password you entered is incorrect.'))
                    ->danger()
                    ->send();
                return;
            }
        }

        // Handle password change
        if (filled($data['new_password'] ?? null)) {
            $data['password'] = Hash::make($data['new_password']);
            $data['password_changed_at'] = now();
        }

        // Remove password fields from data
        unset($data['current_password'], $data['new_password'], $data['new_password_confirmation']);

        // Update user
        $user->update($data);

        // SECURITY: Require email re-verification after email change
        if ($emailChanged) {
            $user->update(['email_verified_at' => null]);

            // Send verification email via Laravel's built-in mechanism
            event(new Registered($user));

            Notification::make()
                ->title(__('Email verification required'))
                ->body(__('Your email has been changed. Please check your inbox for a verification link.'))
                ->warning()
                ->persistent()
                ->send();
        }

        // Log the profile update
        if (function_exists('activity')) {
            activity()
                ->causedBy($user)
                ->performedOn($user)
                ->withProperties([
                    'email_changed' => $emailChanged,
                ])
                ->log('Profile updated');
        }

        Notification::make()
            ->title(__('Profile updated'))
            ->body(__('Your profile has been updated successfully.'))
            ->success()
            ->send();

        $this->fillForms();
    }
}
