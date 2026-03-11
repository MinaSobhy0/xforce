<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Models\PlatformSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class PlatformSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Platform Settings';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.super-admin.pages.platform-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            // General
            'platform_name' => PlatformSetting::get('platform_name', 'XLinic'),
            'platform_url' => PlatformSetting::get('platform_url', 'https://xlinic.com'),
            'support_email' => PlatformSetting::get('support_email', 'support@xlinic.com'),
            'default_locale' => PlatformSetting::get('default_locale', 'ar'),
            'default_timezone' => PlatformSetting::get('default_timezone', 'Africa/Cairo'),
            'default_currency' => PlatformSetting::get('default_currency', 'EGP'),

            // Trial & Onboarding
            'trial_duration' => PlatformSetting::get('trial_duration', 14),
            'default_trial_plan' => PlatformSetting::get('default_trial_plan', 'professional'),
            'welcome_email_template' => PlatformSetting::get('welcome_email_template', 'welcome'),
            'auto_provision' => PlatformSetting::get('auto_provision', true),
            'require_approval' => PlatformSetting::get('require_approval', false),
            'trial_expiry_warning' => PlatformSetting::get('trial_expiry_warning', 3),
            'auto_suspend_after' => PlatformSetting::get('auto_suspend_after', 7),
            'auto_delete_after' => PlatformSetting::get('auto_delete_after', 90),

            // Payment
            'payment_gateway' => PlatformSetting::get('payment_gateway', 'paymob'),
            'payment_api_key' => PlatformSetting::getEncrypted('payment_api_key'),
            'payment_merchant_id' => PlatformSetting::get('payment_merchant_id'),
            'payment_integration_id' => PlatformSetting::get('payment_integration_id'),
            'payment_iframe_id' => PlatformSetting::get('payment_iframe_id'),
            'auto_charge' => PlatformSetting::get('auto_charge', true),
            'grace_period' => PlatformSetting::get('grace_period', 7),
            'retry_attempts' => PlatformSetting::get('retry_attempts', 3),
            'invoice_prefix' => PlatformSetting::get('invoice_prefix', 'PLT-'),

            // Branding
            'primary_color' => PlatformSetting::get('primary_color', '#2563EB'),
            'footer_text' => PlatformSetting::get('footer_text', '© 2025 XLinic. All rights reserved.'),
            'platform_logo' => PlatformSetting::get('platform_logo'),
            'platform_logo_dark' => PlatformSetting::get('platform_logo_dark'),
            'website_logo' => PlatformSetting::get('website_logo'),
            'favicon' => PlatformSetting::get('favicon'),
            'login_page_image' => PlatformSetting::get('login_page_image'),

            // Backup
            'auto_backup' => PlatformSetting::get('auto_backup', true),
            'backup_frequency' => PlatformSetting::get('backup_frequency', 'daily'),
            'backup_time' => PlatformSetting::get('backup_time', '02:00'),
            'backup_retention' => PlatformSetting::get('backup_retention', 30),
            'maintenance_mode' => PlatformSetting::get('maintenance_mode', false),
            'maintenance_message' => PlatformSetting::get('maintenance_message', "We'll be back shortly..."),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Settings')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('General')
                            ->icon('heroicon-o-cog')
                            ->schema([
                                Forms\Components\TextInput::make('platform_name')
                                    ->label('Platform Name')
                                    ->required()
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('platform_url')
                                    ->label('Platform URL')
                                    ->url()
                                    ->required(),

                                Forms\Components\TextInput::make('support_email')
                                    ->label('Support Email')
                                    ->email()
                                    ->required(),

                                Forms\Components\Select::make('default_locale')
                                    ->label('Default Locale')
                                    ->options([
                                        'en' => 'English',
                                        'ar' => 'Arabic',
                                    ])
                                    ->required(),

                                Forms\Components\Select::make('default_timezone')
                                    ->label('Default Timezone')
                                    ->options([
                                        'Africa/Cairo' => 'Cairo (EET)',
                                        'Asia/Riyadh' => 'Riyadh (AST)',
                                        'Asia/Dubai' => 'Dubai (GST)',
                                    ])
                                    ->required(),

                                Forms\Components\Select::make('default_currency')
                                    ->label('Default Currency')
                                    ->options([
                                        'EGP' => 'Egyptian Pound (EGP)',
                                        'SAR' => 'Saudi Riyal (SAR)',
                                        'AED' => 'UAE Dirham (AED)',
                                        'USD' => 'US Dollar (USD)',
                                    ])
                                    ->required(),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Trial & Onboarding')
                            ->icon('heroicon-o-rocket-launch')
                            ->schema([
                                Forms\Components\TextInput::make('trial_duration')
                                    ->label('Trial Duration (days)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(90)
                                    ->required(),

                                Forms\Components\Select::make('default_trial_plan')
                                    ->label('Default Trial Plan')
                                    ->options([
                                        'starter' => 'Starter',
                                        'professional' => 'Professional',
                                        'enterprise' => 'Enterprise',
                                    ])
                                    ->required(),

                                Forms\Components\Select::make('welcome_email_template')
                                    ->label('Welcome Email Template')
                                    ->options(fn () => \App\Models\EmailTemplate::whereRaw('is_active = true')
                                        ->whereIn('trigger', ['welcome', 'onboarding', 'tenant_created'])
                                        ->pluck('name', 'code')
                                        ->toArray() ?: ['welcome' => 'Default Welcome Email'])
                                    ->helperText('Email template sent to new tenants on provisioning')
                                    ->required(),

                                Forms\Components\Toggle::make('auto_provision')
                                    ->label('Auto-Provision')
                                    ->helperText('Auto-create tenant schema on signup'),

                                Forms\Components\Toggle::make('require_approval')
                                    ->label('Require Approval')
                                    ->helperText('Manual approval before access'),

                                Forms\Components\TextInput::make('trial_expiry_warning')
                                    ->label('Trial Expiry Warning (days before)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required(),

                                Forms\Components\TextInput::make('auto_suspend_after')
                                    ->label('Auto-Suspend After (days past due)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required(),

                                Forms\Components\TextInput::make('auto_delete_after')
                                    ->label('Auto-Delete After (days after cancellation)')
                                    ->numeric()
                                    ->minValue(30)
                                    ->required(),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Payment')
                            ->icon('heroicon-o-credit-card')
                            ->schema([
                                Forms\Components\Section::make('Gateway Selection')
                                    ->schema([
                                        Forms\Components\Select::make('payment_gateway')
                                            ->label('Payment Gateway')
                                            ->options([
                                                'paymob' => 'Paymob',
                                                'stripe' => 'Stripe',
                                                'fawry' => 'Fawry',
                                            ])
                                            ->required()
                                            ->live(),
                                    ]),

                                Forms\Components\Section::make('Gateway Credentials')
                                    ->description('API credentials are encrypted at rest')
                                    ->schema([
                                        Forms\Components\TextInput::make('payment_api_key')
                                            ->label('API Key')
                                            ->password()
                                            ->revealable()
                                            ->helperText('Your payment gateway API key (encrypted)')
                                            ->dehydrateStateUsing(fn ($state) => $state ? $state : null),

                                        Forms\Components\TextInput::make('payment_merchant_id')
                                            ->label('Merchant ID')
                                            ->helperText('Your merchant/account ID'),

                                        Forms\Components\TextInput::make('payment_integration_id')
                                            ->label('Integration ID')
                                            ->helperText('For Paymob: Card integration ID')
                                            ->visible(fn (Forms\Get $get) => $get('payment_gateway') === 'paymob'),

                                        Forms\Components\TextInput::make('payment_iframe_id')
                                            ->label('iFrame ID')
                                            ->helperText('For Paymob: Payment iFrame ID')
                                            ->visible(fn (Forms\Get $get) => $get('payment_gateway') === 'paymob'),
                                    ])
                                    ->columns(2),

                                Forms\Components\Section::make('Billing Settings')
                                    ->schema([
                                        Forms\Components\Toggle::make('auto_charge')
                                            ->label('Auto-Charge')
                                            ->helperText('Auto-charge on renewal date'),

                                        Forms\Components\TextInput::make('grace_period')
                                            ->label('Grace Period (days after failed payment)')
                                            ->numeric()
                                            ->minValue(1)
                                            ->required(),

                                        Forms\Components\TextInput::make('retry_attempts')
                                            ->label('Retry Attempts')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(10)
                                            ->required(),

                                        Forms\Components\TextInput::make('invoice_prefix')
                                            ->label('Invoice Prefix')
                                            ->required()
                                            ->maxLength(10),
                                    ])
                                    ->columns(2),
                            ]),

                        Forms\Components\Tabs\Tab::make('Branding')
                            ->icon('heroicon-o-paint-brush')
                            ->schema([
                                Forms\Components\ColorPicker::make('primary_color')
                                    ->label('Primary Color'),

                                Forms\Components\TextInput::make('footer_text')
                                    ->label('Footer Text')
                                    ->maxLength(255),

                                Forms\Components\FileUpload::make('platform_logo')
                                    ->label('Platform Logo (Light Mode)')
                                    ->image()
                                    ->directory('platform/branding')
                                    ->disk('public')
                                    ->helperText('Used in admin panels for light mode.'),

                                Forms\Components\FileUpload::make('platform_logo_dark')
                                    ->label('Platform Logo (Dark Mode)')
                                    ->image()
                                    ->directory('platform/branding')
                                    ->disk('public')
                                    ->helperText('Used in admin panels for dark mode. Use a light-colored logo.'),

                                Forms\Components\FileUpload::make('website_logo')
                                    ->label('Website Logo')
                                    ->image()
                                    ->directory('platform/branding')
                                    ->disk('public')
                                    ->imageEditor()
                                    ->helperText('Used on landing page. PNG or SVG recommended.'),

                                Forms\Components\FileUpload::make('favicon')
                                    ->label('Favicon')
                                    ->image()
                                    ->directory('platform/branding')
                                    ->disk('public')
                                    ->imageEditor()
                                    ->imageResizeMode('cover')
                                    ->imageCropAspectRatio('1:1')
                                    ->imageResizeTargetWidth('64')
                                    ->imageResizeTargetHeight('64')
                                    ->acceptedFileTypes(['image/png', 'image/x-icon', 'image/svg+xml'])
                                    ->helperText('Recommended: 64x64px, PNG or ICO'),

                                Forms\Components\FileUpload::make('login_page_image')
                                    ->label('Login Page Image')
                                    ->image()
                                    ->directory('platform/branding')
                                    ->disk('public')
                                    ->imageEditor()
                                    ->imageResizeMode('cover')
                                    ->imageResizeTargetWidth('1920')
                                    ->imageResizeTargetHeight('1080')
                                    ->helperText('Recommended: 1920x1080px, JPG or PNG'),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Backup & Maintenance')
                            ->icon('heroicon-o-server')
                            ->schema([
                                Forms\Components\Toggle::make('auto_backup')
                                    ->label('Auto Backup'),

                                Forms\Components\Select::make('backup_frequency')
                                    ->label('Backup Frequency')
                                    ->options([
                                        'hourly' => 'Hourly',
                                        'daily' => 'Daily',
                                        'weekly' => 'Weekly',
                                    ])
                                    ->required(),

                                Forms\Components\TimePicker::make('backup_time')
                                    ->label('Backup Time')
                                    ->seconds(false),

                                Forms\Components\TextInput::make('backup_retention')
                                    ->label('Backup Retention (days)')
                                    ->numeric()
                                    ->minValue(7)
                                    ->required(),

                                Forms\Components\Toggle::make('maintenance_mode')
                                    ->label('Maintenance Mode')
                                    ->helperText('WARNING: This will make the platform inaccessible'),

                                Forms\Components\Textarea::make('maintenance_message')
                                    ->label('Maintenance Message')
                                    ->rows(2),

                                Forms\Components\Placeholder::make('last_backup_status')
                                    ->label('Last Backup')
                                    ->content(function () {
                                        $lastBackup = \App\Models\Backup::latest()->first();
                                        if (!$lastBackup) {
                                            return 'No backups yet';
                                        }
                                        $status = $lastBackup->status === 'completed' ? '✅' : ($lastBackup->status === 'failed' ? '❌' : '⏳');
                                        return "{$status} {$lastBackup->created_at->diffForHumans()} - {$lastBackup->filename}";
                                    }),

                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('view_backup_history')
                                        ->label('View Backup History')
                                        ->icon('heroicon-o-clock')
                                        ->url(route('filament.super-admin.resources.backups.index'))
                                        ->openUrlInNewTab(false),
                                ]),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save All Settings')
                ->action('save')
                ->color('primary'),

            Action::make('run_backup')
                ->label('Run Backup Now')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('This will start backing up all active tenants. This may take several minutes depending on the number of tenants.')
                ->action(function () {
                    try {
                        \Illuminate\Support\Facades\Artisan::call('tenants:backup', ['--force' => true]);

                        Notification::make()
                            ->title('Backup started')
                            ->body('All tenant backups have been queued. Check the Backup History for progress.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Backup failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // General
        PlatformSetting::set('platform_name', $data['platform_name'], 'general');
        PlatformSetting::set('platform_url', $data['platform_url'], 'general');
        PlatformSetting::set('support_email', $data['support_email'], 'general');
        PlatformSetting::set('default_locale', $data['default_locale'], 'general');
        PlatformSetting::set('default_timezone', $data['default_timezone'], 'general');
        PlatformSetting::set('default_currency', $data['default_currency'], 'general');

        // Trial & Onboarding
        PlatformSetting::set('trial_duration', $data['trial_duration'], 'trial', 'integer');
        PlatformSetting::set('default_trial_plan', $data['default_trial_plan'], 'trial');
        PlatformSetting::set('welcome_email_template', $data['welcome_email_template'], 'trial');
        PlatformSetting::set('auto_provision', $data['auto_provision'], 'trial', 'boolean');
        PlatformSetting::set('require_approval', $data['require_approval'], 'trial', 'boolean');
        PlatformSetting::set('trial_expiry_warning', $data['trial_expiry_warning'], 'trial', 'integer');
        PlatformSetting::set('auto_suspend_after', $data['auto_suspend_after'], 'trial', 'integer');
        PlatformSetting::set('auto_delete_after', $data['auto_delete_after'], 'trial', 'integer');

        // Payment
        PlatformSetting::set('payment_gateway', $data['payment_gateway'], 'payment');

        // Save API key encrypted (only if a new value is provided)
        if (!empty($data['payment_api_key'])) {
            PlatformSetting::setEncrypted('payment_api_key', $data['payment_api_key'], 'payment');
        }

        if (!empty($data['payment_merchant_id'])) {
            PlatformSetting::set('payment_merchant_id', $data['payment_merchant_id'], 'payment');
        }

        if (!empty($data['payment_integration_id'])) {
            PlatformSetting::set('payment_integration_id', $data['payment_integration_id'], 'payment');
        }

        if (!empty($data['payment_iframe_id'])) {
            PlatformSetting::set('payment_iframe_id', $data['payment_iframe_id'], 'payment');
        }

        PlatformSetting::set('auto_charge', $data['auto_charge'], 'payment', 'boolean');
        PlatformSetting::set('grace_period', $data['grace_period'], 'payment', 'integer');
        PlatformSetting::set('retry_attempts', $data['retry_attempts'], 'payment', 'integer');
        PlatformSetting::set('invoice_prefix', $data['invoice_prefix'], 'payment');

        // Branding
        PlatformSetting::set('primary_color', $data['primary_color'], 'branding');
        PlatformSetting::set('footer_text', $data['footer_text'], 'branding');

        // Handle file uploads
        if (!empty($data['platform_logo'])) {
            $logo = is_array($data['platform_logo']) ? reset($data['platform_logo']) : $data['platform_logo'];
            PlatformSetting::set('platform_logo', $logo, 'branding');
        }

        if (!empty($data['platform_logo_dark'])) {
            $logoDark = is_array($data['platform_logo_dark']) ? reset($data['platform_logo_dark']) : $data['platform_logo_dark'];
            PlatformSetting::set('platform_logo_dark', $logoDark, 'branding');
        }

        if (!empty($data['website_logo'])) {
            $websiteLogo = is_array($data['website_logo']) ? reset($data['website_logo']) : $data['website_logo'];
            PlatformSetting::set('website_logo', $websiteLogo, 'branding');
        }

        if (!empty($data['favicon'])) {
            $favicon = is_array($data['favicon']) ? reset($data['favicon']) : $data['favicon'];
            PlatformSetting::set('favicon', $favicon, 'branding');
        }

        if (!empty($data['login_page_image'])) {
            $loginImage = is_array($data['login_page_image']) ? reset($data['login_page_image']) : $data['login_page_image'];
            PlatformSetting::set('login_page_image', $loginImage, 'branding');
        }

        // Backup
        PlatformSetting::set('auto_backup', $data['auto_backup'], 'backup', 'boolean');
        PlatformSetting::set('backup_frequency', $data['backup_frequency'], 'backup');
        PlatformSetting::set('backup_time', $data['backup_time'], 'backup');
        PlatformSetting::set('backup_retention', $data['backup_retention'], 'backup', 'integer');
        PlatformSetting::set('maintenance_mode', $data['maintenance_mode'], 'backup', 'boolean');
        PlatformSetting::set('maintenance_message', $data['maintenance_message'], 'backup');

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
