<?php

namespace Modules\Core\Resources\SystemSettingsResource\Pages;

use Modules\Core\Resources\SystemSettingsResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class ManageSystemSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = SystemSettingsResource::class;

    protected static string $view = 'filament.pages.manage-system-settings';

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    public function getTitle(): string
    {
        return __('System Settings');
    }

    public function getHeading(): string
    {
        return __('System Settings');
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->fillForm();
    }

    protected function fillForm(): void
    {
        // Load settings from cache or database
        $settings = Cache::get('system_settings', []);

        $this->data = array_merge([
            'app_name' => config('app.name'),
            'app_url' => config('app.url'),
            'app_timezone' => config('app.timezone', 'Africa/Cairo'),
            'app_locale' => config('app.locale', 'en'),
            'allow_tenant_registration' => true,
            'require_domain_verification' => true,
            'default_max_users' => 10,
            'default_max_patients' => 1000,
            'default_max_storage_mb' => 1024,
            'trial_duration_days' => 30,
            'default_features' => ['users', 'patients', 'appointments', 'treatments'],
            'mail_from_name' => config('mail.from.name'),
            'mail_from_address' => config('mail.from.address'),
            'mail_notifications_enabled' => true,
            'send_welcome_email' => true,
            'session_lifetime' => 120,
            'password_min_length' => 8,
            'force_https' => true,
            'enable_two_factor' => true,
            'require_email_verification' => true,
            'max_login_attempts' => 5,
            'enable_backups' => true,
            'backup_frequency' => 'daily',
            'backup_retention_days' => 30,
            'maintenance_mode' => false,
        ], $settings);

        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('System Settings')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('General')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('app_name')
                                            ->label(__('Application Name'))
                                            ->required()
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('app_url')
                                            ->label(__('Application URL'))
                                            ->url()
                                            ->required(),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('app_timezone')
                                            ->label(__('Default Timezone'))
                                            ->options([
                                                'Africa/Cairo' => 'Cairo (GMT+2)',
                                                'Asia/Riyadh' => 'Riyadh (GMT+3)',
                                                'Asia/Dubai' => 'Dubai (GMT+4)',
                                                'Asia/Kuwait' => 'Kuwait (GMT+3)',
                                                'Asia/Qatar' => 'Qatar (GMT+3)',
                                            ])
                                            ->searchable(),

                                        Forms\Components\Select::make('app_locale')
                                            ->label(__('Default Language'))
                                            ->options([
                                                'ar' => __('Arabic'),
                                                'en' => __('English'),
                                            ]),
                                    ]),

                                Forms\Components\FileUpload::make('app_logo')
                                    ->label(__('Application Logo'))
                                    ->image()
                                    ->directory('system')
                                    ->visibility('public')
                                    ->nullable(),

                                Forms\Components\Textarea::make('app_description')
                                    ->label(__('Application Description'))
                                    ->nullable()
                                    ->rows(3),
                            ]),

                        Forms\Components\Tabs\Tab::make('Tenancy')
                            ->icon('heroicon-o-building-office-2')
                            ->schema([
                                Forms\Components\Toggle::make('allow_tenant_registration')
                                    ->label(__('Allow Tenant Registration'))
                                    ->helperText(__('Enable self-service tenant registration')),

                                Forms\Components\Toggle::make('require_domain_verification')
                                    ->label(__('Require Domain Verification'))
                                    ->helperText(__('Require domain ownership verification for custom domains')),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('default_max_users')
                                            ->label(__('Default Max Users'))
                                            ->numeric()
                                            ->minValue(1),

                                        Forms\Components\TextInput::make('default_max_patients')
                                            ->label(__('Default Max Patients'))
                                            ->numeric()
                                            ->minValue(10),

                                        Forms\Components\TextInput::make('default_max_storage_mb')
                                            ->label(__('Default Storage Limit (MB)'))
                                            ->numeric()
                                            ->minValue(100),

                                        Forms\Components\TextInput::make('trial_duration_days')
                                            ->label(__('Trial Duration (Days)'))
                                            ->numeric()
                                            ->minValue(0),
                                    ]),

                                Forms\Components\CheckboxList::make('default_features')
                                    ->label(__('Default Features'))
                                    ->options([
                                        'users' => __('User Management'),
                                        'patients' => __('Patient Management'),
                                        'appointments' => __('Appointment Scheduling'),
                                        'treatments' => __('Treatment Catalog'),
                                        'inventory' => __('Inventory Management'),
                                        'billing' => __('Billing & Invoicing'),
                                        'reports' => __('Reports & Analytics'),
                                        'marketing' => __('Marketing Tools'),
                                        'portal' => __('Patient Portal'),
                                        'api' => __('API Access'),
                                    ])
                                    ->columns(2),
                            ]),

                        Forms\Components\Tabs\Tab::make('Email')
                            ->icon('heroicon-o-envelope')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('mail_from_name')
                                            ->label(__('From Name')),

                                        Forms\Components\TextInput::make('mail_from_address')
                                            ->label(__('From Address'))
                                            ->email(),
                                    ]),

                                Forms\Components\Toggle::make('mail_notifications_enabled')
                                    ->label(__('Enable Email Notifications')),

                                Forms\Components\Toggle::make('send_welcome_email')
                                    ->label(__('Send Welcome Email to New Tenants')),

                                Forms\Components\Textarea::make('welcome_email_template')
                                    ->label(__('Welcome Email Template'))
                                    ->nullable()
                                    ->rows(5)
                                    ->helperText(__('Use {{tenant_name}}, {{tenant_url}} placeholders')),
                            ]),

                        Forms\Components\Tabs\Tab::make('Security')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('session_lifetime')
                                            ->label(__('Session Lifetime (minutes)'))
                                            ->numeric()
                                            ->minValue(5),

                                        Forms\Components\TextInput::make('password_min_length')
                                            ->label(__('Minimum Password Length'))
                                            ->numeric()
                                            ->minValue(6),
                                    ]),

                                Forms\Components\Toggle::make('force_https')
                                    ->label(__('Force HTTPS'))
                                    ->helperText(__('Redirect all HTTP requests to HTTPS')),

                                Forms\Components\Toggle::make('enable_two_factor')
                                    ->label(__('Enable Two-Factor Authentication')),

                                Forms\Components\Toggle::make('require_email_verification')
                                    ->label(__('Require Email Verification')),

                                Forms\Components\TextInput::make('max_login_attempts')
                                    ->label(__('Max Login Attempts'))
                                    ->numeric()
                                    ->minValue(3),
                            ]),

                        Forms\Components\Tabs\Tab::make('Backup & Maintenance')
                            ->icon('heroicon-o-server-stack')
                            ->schema([
                                Forms\Components\Toggle::make('enable_backups')
                                    ->label(__('Enable Automated Backups')),

                                Forms\Components\Select::make('backup_frequency')
                                    ->label(__('Backup Frequency'))
                                    ->options([
                                        'hourly' => __('Hourly'),
                                        'daily' => __('Daily'),
                                        'weekly' => __('Weekly'),
                                        'monthly' => __('Monthly'),
                                    ]),

                                Forms\Components\TextInput::make('backup_retention_days')
                                    ->label(__('Backup Retention (Days)'))
                                    ->numeric()
                                    ->minValue(1),

                                Forms\Components\Toggle::make('maintenance_mode')
                                    ->label(__('Maintenance Mode'))
                                    ->helperText(__('Enable to prevent tenant access during maintenance'))
                                    ->live(),

                                Forms\Components\Textarea::make('maintenance_message')
                                    ->label(__('Maintenance Message'))
                                    ->visible(fn (Forms\Get $get) => $get('maintenance_mode'))
                                    ->rows(3),
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
                ->label(__('Save Settings'))
                ->icon('heroicon-o-check')
                ->action('save'),

            Actions\Action::make('clearCache')
                ->label(__('Clear Cache'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    Artisan::call('cache:clear');
                    Artisan::call('config:clear');
                    Artisan::call('route:clear');
                    Artisan::call('view:clear');

                    Notification::make()
                        ->title(__('Cache cleared'))
                        ->body(__('All system caches have been cleared successfully.'))
                        ->success()
                        ->send();
                }),

            Actions\Action::make('optimizeSystem')
                ->label(__('Optimize System'))
                ->icon('heroicon-o-bolt')
                ->color('success')
                ->action(function () {
                    Artisan::call('optimize');
                    Artisan::call('config:cache');
                    Artisan::call('route:cache');
                    Artisan::call('view:cache');

                    Notification::make()
                        ->title(__('System optimized'))
                        ->body(__('System has been optimized for better performance.'))
                        ->success()
                        ->send();
                }),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Save settings to cache
        Cache::forever('system_settings', $data);

        Notification::make()
            ->title(__('Settings saved'))
            ->body(__('System settings have been updated successfully.'))
            ->success()
            ->send();

        // Clear relevant caches after saving settings
        Artisan::call('config:clear');
    }
}
