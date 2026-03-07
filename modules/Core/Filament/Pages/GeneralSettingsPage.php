<?php

namespace Modules\Core\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Core\Models\Setting;
use Illuminate\Support\Facades\DB;

class GeneralSettingsPage extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'core::filament.pages.general-settings';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 60;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Super admin and key roles have full access
        if (method_exists($user, 'hasRole') && $user->hasRole(['super-admin', 'super_admin', 'tenant-owner', 'tenant_owner', 'owner', 'admin'])) {
            return true;
        }

        // Check settings.view permission
        if ($user->can('settings.view')) {
            return true;
        }

        // If permission doesn't exist, allow access (fallback)
        $permissionExists = \Spatie\Permission\Models\Permission::where('name', 'settings.view')
            ->where('guard_name', 'web')
            ->exists();

        return !$permissionExists;
    }

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('core::core.general_settings');
    }

    public function getTitle(): string
    {
        return __('core::core.general_settings');
    }

    public function mount(): void
    {
        $this->form->fill($this->loadSettings());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Settings')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make(__('core::core.general'))
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Forms\Components\Section::make(__('core::core.clinic_information'))
                                    ->schema([
                                        Forms\Components\TextInput::make('clinic_name')
                                            ->label(__('core::core.clinic_name'))
                                            ->required()
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('clinic_tagline')
                                            ->label(__('core::core.tagline'))
                                            ->maxLength(255),

                                        Forms\Components\Textarea::make('clinic_address')
                                            ->label(__('core::core.address'))
                                            ->rows(2),

                                        Forms\Components\TextInput::make('clinic_phone')
                                            ->label(__('core::core.phone'))
                                            ->tel(),

                                        Forms\Components\TextInput::make('clinic_email')
                                            ->label(__('core::core.email'))
                                            ->email(),

                                        Forms\Components\TextInput::make('clinic_website')
                                            ->label(__('core::core.website'))
                                            ->url(),
                                    ])
                                    ->columns(2),

                                Forms\Components\Section::make(__('core::core.localization'))
                                    ->schema([
                                        Forms\Components\Select::make('default_language')
                                            ->label(__('core::core.default_language'))
                                            ->options([
                                                'en' => 'English',
                                                'ar' => 'Arabic (العربية)',
                                            ])
                                            ->default('en'),

                                        Forms\Components\Select::make('timezone')
                                            ->label(__('core::core.timezone'))
                                            ->options(static::getTimezoneOptions())
                                            ->searchable()
                                            ->default('Africa/Cairo'),

                                        Forms\Components\Select::make('currency_code')
                                            ->label(__('core::core.currency'))
                                            ->options([
                                                'EGP' => 'Egyptian Pound (EGP)',
                                                'USD' => 'US Dollar (USD)',
                                                'EUR' => 'Euro (EUR)',
                                                'SAR' => 'Saudi Riyal (SAR)',
                                                'AED' => 'UAE Dirham (AED)',
                                            ])
                                            ->default('EGP'),

                                        Forms\Components\Select::make('date_format')
                                            ->label(__('core::core.date_format'))
                                            ->options([
                                                'd/m/Y' => '31/12/2024',
                                                'm/d/Y' => '12/31/2024',
                                                'Y-m-d' => '2024-12-31',
                                                'd-M-Y' => '31-Dec-2024',
                                            ])
                                            ->default('d/m/Y'),

                                        Forms\Components\Select::make('time_format')
                                            ->label(__('core::core.time_format'))
                                            ->options([
                                                'H:i' => '24-hour (14:30)',
                                                'h:i A' => '12-hour (02:30 PM)',
                                            ])
                                            ->default('h:i A'),
                                    ])
                                    ->columns(2),
                            ]),

                        Forms\Components\Tabs\Tab::make(__('core::core.booking_settings'))
                            ->icon('heroicon-o-calendar')
                            ->schema([
                                Forms\Components\Section::make(__('core::core.appointment_settings'))
                                    ->schema([
                                        Forms\Components\TextInput::make('default_slot_duration')
                                            ->label(__('core::core.default_slot_duration'))
                                            ->numeric()
                                            ->suffix(__('core::core.minutes'))
                                            ->default(30),

                                        Forms\Components\TextInput::make('buffer_minutes')
                                            ->label(__('core::core.buffer_minutes'))
                                            ->numeric()
                                            ->suffix(__('core::core.minutes'))
                                            ->default(15)
                                            ->helperText(__('core::core.buffer_minutes_help')),

                                        Forms\Components\TextInput::make('max_advance_booking_days')
                                            ->label(__('core::core.max_advance_booking_days'))
                                            ->numeric()
                                            ->suffix(__('core::core.days'))
                                            ->default(90),

                                        Forms\Components\TextInput::make('cancellation_policy_hours')
                                            ->label(__('core::core.cancellation_policy_hours'))
                                            ->numeric()
                                            ->suffix(__('core::core.hours'))
                                            ->default(24)
                                            ->helperText(__('core::core.cancellation_policy_help')),
                                    ])
                                    ->columns(2),

                                Forms\Components\Section::make(__('core::core.confirmation_settings'))
                                    ->schema([
                                        Forms\Components\Toggle::make('auto_confirm_appointments')
                                            ->label(__('core::core.auto_confirm_appointments'))
                                            ->default(false),

                                        Forms\Components\Toggle::make('allow_online_booking')
                                            ->label(__('core::core.allow_online_booking'))
                                            ->default(true),

                                        Forms\Components\Toggle::make('require_deposit')
                                            ->label(__('core::core.require_deposit'))
                                            ->default(false),

                                        Forms\Components\TextInput::make('deposit_percentage')
                                            ->label(__('core::core.deposit_percentage'))
                                            ->numeric()
                                            ->suffix('%')
                                            ->default(25)
                                            ->visible(fn (Forms\Get $get) => $get('require_deposit')),
                                    ])
                                    ->columns(2),

                                Forms\Components\Section::make(__('core::core.reminder_settings'))
                                    ->schema([
                                        Forms\Components\TextInput::make('reminder_hours_before')
                                            ->label(__('core::core.reminder_hours_before'))
                                            ->numeric()
                                            ->suffix(__('core::core.hours'))
                                            ->default(24),

                                        Forms\Components\Toggle::make('send_whatsapp_reminders')
                                            ->label(__('core::core.send_whatsapp_reminders'))
                                            ->default(true),

                                        Forms\Components\Toggle::make('send_sms_reminders')
                                            ->label(__('core::core.send_sms_reminders'))
                                            ->default(false),

                                        Forms\Components\Toggle::make('send_email_reminders')
                                            ->label(__('core::core.send_email_reminders'))
                                            ->default(true),
                                    ])
                                    ->columns(2),
                            ]),

                        Forms\Components\Tabs\Tab::make(__('core::core.billing_settings'))
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Forms\Components\Section::make(__('core::core.tax_settings'))
                                    ->schema([
                                        Forms\Components\TextInput::make('tax_rate')
                                            ->label(__('core::core.tax_rate'))
                                            ->numeric()
                                            ->suffix('%')
                                            ->default(14),

                                        Forms\Components\Toggle::make('tax_inclusive')
                                            ->label(__('core::core.tax_inclusive'))
                                            ->helperText(__('core::core.tax_inclusive_help'))
                                            ->default(true),

                                        Forms\Components\TextInput::make('tax_registration_number')
                                            ->label(__('core::core.tax_registration_number'))
                                            ->maxLength(50),
                                    ])
                                    ->columns(2),

                                Forms\Components\Section::make(__('core::core.invoice_settings'))
                                    ->schema([
                                        Forms\Components\Toggle::make('auto_invoice_on_complete')
                                            ->label(__('core::core.auto_invoice_on_complete'))
                                            ->helperText(__('core::core.auto_invoice_help'))
                                            ->default(true),

                                        Forms\Components\TextInput::make('default_payment_terms_days')
                                            ->label(__('core::core.default_payment_terms_days'))
                                            ->numeric()
                                            ->suffix(__('core::core.days'))
                                            ->default(0),

                                        Forms\Components\Toggle::make('enable_installments')
                                            ->label(__('core::core.enable_installments'))
                                            ->default(false),

                                        Forms\Components\Textarea::make('invoice_footer_note')
                                            ->label(__('core::core.invoice_footer_note'))
                                            ->rows(2),
                                    ])
                                    ->columns(2),

                                Forms\Components\Section::make(__('core::core.payment_methods'))
                                    ->schema([
                                        Forms\Components\CheckboxList::make('enabled_payment_methods')
                                            ->label(__('core::core.enabled_payment_methods'))
                                            ->options([
                                                'cash' => __('core::core.payment_methods.cash'),
                                                'card' => __('core::core.payment_methods.card'),
                                                'bank_transfer' => __('core::core.payment_methods.bank_transfer'),
                                                'wallet' => __('core::core.payment_methods.wallet'),
                                                'installment' => __('core::core.payment_methods.installment'),
                                            ])
                                            ->default(['cash', 'card'])
                                            ->columns(3),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make(__('core::core.marketing_settings'))
                            ->icon('heroicon-o-megaphone')
                            ->schema([
                                Forms\Components\Section::make(__('core::core.notification_settings'))
                                    ->schema([
                                        Forms\Components\Toggle::make('send_birthday_greetings')
                                            ->label(__('core::core.send_birthday_greetings'))
                                            ->default(true),

                                        Forms\Components\Toggle::make('send_followup_messages')
                                            ->label(__('core::core.send_followup_messages'))
                                            ->default(true),

                                        Forms\Components\TextInput::make('followup_days_after')
                                            ->label(__('core::core.followup_days_after'))
                                            ->numeric()
                                            ->suffix(__('core::core.days'))
                                            ->default(7),
                                    ])
                                    ->columns(2),

                                Forms\Components\Section::make(__('core::core.loyalty_settings'))
                                    ->schema([
                                        Forms\Components\Toggle::make('enable_loyalty_program')
                                            ->label(__('core::core.enable_loyalty_program'))
                                            ->default(false),

                                        Forms\Components\TextInput::make('points_per_currency')
                                            ->label(__('core::core.points_per_currency'))
                                            ->numeric()
                                            ->default(1)
                                            ->helperText(__('core::core.points_per_currency_help')),

                                        Forms\Components\TextInput::make('currency_per_point')
                                            ->label(__('core::core.currency_per_point'))
                                            ->numeric()
                                            ->default(0.1)
                                            ->helperText(__('core::core.currency_per_point_help')),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                Setting::updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => is_array($value) ? json_encode($value) : $value,
                        'group' => $this->getSettingGroup($key),
                    ]
                );
            }
        });

        Notification::make()
            ->title(__('core::core.settings_saved'))
            ->success()
            ->send();
    }

    protected function loadSettings(): array
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();

        // Decode JSON values
        foreach ($settings as $key => $value) {
            if (is_string($value) && str_starts_with($value, '[')) {
                $settings[$key] = json_decode($value, true) ?? $value;
            }
        }

        return $settings;
    }

    protected function getSettingGroup(string $key): string
    {
        $groups = [
            'clinic_' => 'general',
            'default_' => 'general',
            'timezone' => 'general',
            'currency_' => 'general',
            'date_' => 'general',
            'time_' => 'general',
            'slot_' => 'booking',
            'buffer_' => 'booking',
            'max_advance_' => 'booking',
            'cancellation_' => 'booking',
            'auto_confirm_' => 'booking',
            'allow_online_' => 'booking',
            'require_deposit' => 'booking',
            'deposit_' => 'booking',
            'reminder_' => 'booking',
            'send_whatsapp_' => 'booking',
            'send_sms_' => 'booking',
            'send_email_' => 'booking',
            'tax_' => 'billing',
            'auto_invoice_' => 'billing',
            'payment_' => 'billing',
            'enable_installments' => 'billing',
            'invoice_' => 'billing',
            'enabled_payment_' => 'billing',
            'send_birthday_' => 'marketing',
            'send_followup_' => 'marketing',
            'followup_' => 'marketing',
            'enable_loyalty_' => 'marketing',
            'points_' => 'marketing',
            'currency_per_' => 'marketing',
        ];

        foreach ($groups as $prefix => $group) {
            if (str_starts_with($key, $prefix)) {
                return $group;
            }
        }

        return 'general';
    }

    protected static function getTimezoneOptions(): array
    {
        $timezones = [];
        foreach (timezone_identifiers_list() as $timezone) {
            $timezones[$timezone] = $timezone;
        }
        return $timezones;
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label(__('core::core.save_settings'))
                ->submit('save'),
        ];
    }
}
