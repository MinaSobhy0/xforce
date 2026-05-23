<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Models\PlatformSetting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Platform-wide mobile app defaults.
 *
 * Edits the values Tenant::getDefaultMobileConfig() will return when a tenant
 * hasn't overridden its own mobile_config. Stored under the `mobile_defaults`
 * group in platform_settings; the model layer reads from here first and
 * falls back to its hardcoded values only when a key is unset.
 *
 * Per-tenant overrides (set in ManageTenantMobileApp) still win — this only
 * shifts the *floor* for every tenant.
 */
class MobileAppDefaultsSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Mobile App Defaults';

    protected static ?string $title = 'Mobile App Defaults';

    protected static ?int $navigationSort = 50;

    protected static string $view = 'filament.super-admin.pages.platform-settings-form';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'primary_color' => PlatformSetting::get('mobile_defaults.primary_color', '#3B82F6'),
            'secondary_color' => PlatformSetting::get('mobile_defaults.secondary_color', '#1E40AF'),
            'accent_color' => PlatformSetting::get('mobile_defaults.accent_color', '#F59E0B'),
            'dark_mode_enabled' => (bool) PlatformSetting::get('mobile_defaults.dark_mode_enabled', true),
            'app_name_template' => PlatformSetting::get('mobile_defaults.app_name_template', ''),
            'default_tab_dashboard' => (bool) PlatformSetting::get('mobile_defaults.tab_dashboard', true),
            'default_tab_appointments' => (bool) PlatformSetting::get('mobile_defaults.tab_appointments', true),
            'default_tab_attendance' => (bool) PlatformSetting::get('mobile_defaults.tab_attendance', true),
            'default_tab_schedule' => (bool) PlatformSetting::get('mobile_defaults.tab_schedule', true),
            'default_tab_more' => (bool) PlatformSetting::get('mobile_defaults.tab_more', true),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Mobile App Defaults')
                    ->columnSpanFull()
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Branding')
                            ->icon('heroicon-o-paint-brush')
                            ->schema([
                                Forms\Components\Section::make('Default Colors')
                                    ->description('Every tenant inherits these as their starting palette. They can override per-tenant in the Mobile App Configuration page.')
                                    ->columns(3)
                                    ->schema([
                                        Forms\Components\ColorPicker::make('primary_color')
                                            ->label('Primary')
                                            ->required(),
                                        Forms\Components\ColorPicker::make('secondary_color')
                                            ->label('Secondary')
                                            ->required(),
                                        Forms\Components\ColorPicker::make('accent_color')
                                            ->label('Accent')
                                            ->required(),
                                    ]),

                                Forms\Components\Section::make('Display')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\Toggle::make('dark_mode_enabled')
                                            ->label('Dark mode available by default')
                                            ->helperText('When on, the app exposes a light/dark toggle for end users.'),
                                        Forms\Components\TextInput::make('app_name_template')
                                            ->label('App name template')
                                            ->placeholder('Leave empty to use clinic name')
                                            ->helperText('Optional prefix/suffix applied when a tenant has no custom app_name. Use {tenant} to interpolate the clinic name.'),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Default Tabs')
                            ->icon('heroicon-o-bars-3-bottom-left')
                            ->schema([
                                Forms\Components\Section::make('Tab Visibility')
                                    ->description('Which bottom-nav tabs show up by default for new tenants. Tenant-level overrides still apply.')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\Toggle::make('default_tab_dashboard')->label('Dashboard'),
                                        Forms\Components\Toggle::make('default_tab_appointments')->label('Appointments'),
                                        Forms\Components\Toggle::make('default_tab_attendance')->label('Attendance'),
                                        Forms\Components\Toggle::make('default_tab_schedule')->label('Schedule'),
                                        Forms\Components\Toggle::make('default_tab_more')->label('More menu'),
                                    ]),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $map = [
            'mobile_defaults.primary_color' => $data['primary_color'],
            'mobile_defaults.secondary_color' => $data['secondary_color'],
            'mobile_defaults.accent_color' => $data['accent_color'],
            'mobile_defaults.dark_mode_enabled' => $data['dark_mode_enabled'] ? '1' : '0',
            'mobile_defaults.app_name_template' => $data['app_name_template'] ?? '',
            'mobile_defaults.tab_dashboard' => $data['default_tab_dashboard'] ? '1' : '0',
            'mobile_defaults.tab_appointments' => $data['default_tab_appointments'] ? '1' : '0',
            'mobile_defaults.tab_attendance' => $data['default_tab_attendance'] ? '1' : '0',
            'mobile_defaults.tab_schedule' => $data['default_tab_schedule'] ? '1' : '0',
            'mobile_defaults.tab_more' => $data['default_tab_more'] ? '1' : '0',
        ];

        foreach ($map as $key => $value) {
            $type = is_bool($value) || in_array($value, ['0', '1'], true) ? 'boolean' : 'string';
            PlatformSetting::set($key, $value, 'mobile_defaults', $type);
        }

        Notification::make()
            ->title('Mobile app defaults saved')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label('Save Defaults')
                ->submit('save'),
        ];
    }
}
