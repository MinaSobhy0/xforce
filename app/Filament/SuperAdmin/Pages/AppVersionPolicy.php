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
 * Platform-wide mobile app version policy.
 *
 * Drives the GET /api/v2/app/version-check endpoint:
 *   - minimum_version (forced update below this)
 *   - latest_version (soft prompt up to this)
 *   - force_update + message + store URL
 *
 * Stored as platform_settings under group=mobile_version with per-platform keys
 * (ios_*, android_*). Empty min/latest values disable the corresponding gate.
 */
class AppVersionPolicy extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Apps & Modules';

    public static function getNavigationParentItem(): ?string
    {
        return __('core::core.nav_folders.mobile_app');
    }

    protected static ?string $navigationLabel = 'App Version Policy';

    protected static ?string $title = 'App Version Policy';

    protected static ?int $navigationSort = 60;

    protected static string $view = 'filament.super-admin.pages.platform-settings-form';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'ios_minimum_version' => PlatformSetting::get('mobile_version.ios_minimum_version', ''),
            'ios_latest_version' => PlatformSetting::get('mobile_version.ios_latest_version', ''),
            'ios_force_update' => (bool) PlatformSetting::get('mobile_version.ios_force_update', false),
            'ios_store_url' => PlatformSetting::get('mobile_version.ios_store_url', ''),
            'ios_message' => PlatformSetting::get('mobile_version.ios_message', ''),

            'android_minimum_version' => PlatformSetting::get('mobile_version.android_minimum_version', ''),
            'android_latest_version' => PlatformSetting::get('mobile_version.android_latest_version', ''),
            'android_force_update' => (bool) PlatformSetting::get('mobile_version.android_force_update', false),
            'android_store_url' => PlatformSetting::get('mobile_version.android_store_url', ''),
            'android_message' => PlatformSetting::get('mobile_version.android_message', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('App Version Policy')
                    ->columnSpanFull()
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('iOS')
                            ->icon('heroicon-o-device-phone-mobile')
                            ->schema($this->platformSection('ios')),

                        Forms\Components\Tabs\Tab::make('Android')
                            ->icon('heroicon-o-device-phone-mobile')
                            ->schema($this->platformSection('android')),
                    ]),
            ])
            ->statePath('data');
    }

    protected function platformSection(string $platform): array
    {
        $label = ucfirst($platform);

        return [
            Forms\Components\Section::make("{$label} version policy")
                ->description('Leave minimum/latest blank to disable the gate. Force-update routes the user to the store URL until they update.')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make("{$platform}_minimum_version")
                        ->label('Minimum supported version')
                        ->placeholder('e.g. 1.0.0')
                        ->helperText('Below this, the app shows the force-update screen.'),
                    Forms\Components\TextInput::make("{$platform}_latest_version")
                        ->label('Latest available version')
                        ->placeholder('e.g. 1.2.0')
                        ->helperText('Below this (but above minimum), the app shows a soft-prompt.'),
                    Forms\Components\Toggle::make("{$platform}_force_update")
                        ->label('Force update active')
                        ->helperText('Master switch — when off, the minimum check is skipped even if set.'),
                    Forms\Components\TextInput::make("{$platform}_store_url")
                        ->label('Store URL')
                        ->url()
                        ->placeholder($platform === 'ios' ? 'https://apps.apple.com/...' : 'https://play.google.com/store/apps/details?id=...'),
                    Forms\Components\Textarea::make("{$platform}_message")
                        ->label('Update message')
                        ->rows(2)
                        ->columnSpanFull()
                        ->placeholder('Shown on the force-update / soft-prompt screen. Leave empty for a generic message.'),
                ]),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (['ios', 'android'] as $platform) {
            PlatformSetting::set("mobile_version.{$platform}_minimum_version", $data["{$platform}_minimum_version"] ?? '', 'mobile_version', 'string');
            PlatformSetting::set("mobile_version.{$platform}_latest_version", $data["{$platform}_latest_version"] ?? '', 'mobile_version', 'string');
            PlatformSetting::set("mobile_version.{$platform}_force_update", $data["{$platform}_force_update"] ? '1' : '0', 'mobile_version', 'boolean');
            PlatformSetting::set("mobile_version.{$platform}_store_url", $data["{$platform}_store_url"] ?? '', 'mobile_version', 'string');
            PlatformSetting::set("mobile_version.{$platform}_message", $data["{$platform}_message"] ?? '', 'mobile_version', 'string');
        }

        Notification::make()
            ->title('App version policy saved')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label('Save Policy')
                ->submit('save'),
        ];
    }
}
