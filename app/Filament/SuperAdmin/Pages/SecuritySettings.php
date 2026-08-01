<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Models\PlatformSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SecuritySettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Security';

    protected static ?string $navigationGroup = 'System';

    public static function getNavigationParentItem(): ?string
    {
        return __('core::core.nav_folders.security_audit');
    }

    protected static ?int $navigationSort = 7;

    protected static string $view = 'filament.super-admin.pages.security-settings';

    public ?array $recaptchaData = [];

    public function mount(): void
    {
        $this->recaptchaData = [
            'recaptcha_enabled' => PlatformSetting::get('recaptcha_enabled', false),
            'recaptcha_site_key' => PlatformSetting::get('recaptcha_site_key', ''),
            'recaptcha_secret_key' => PlatformSetting::get('recaptcha_secret_key', ''),
            'recaptcha_min_score' => PlatformSetting::get('recaptcha_min_score', 0.5),
        ];
    }

    public function recaptchaForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Toggle::make('recaptcha_enabled')
                    ->label('Enable reCAPTCHA v3')
                    ->helperText('Invisible spam protection for forms')
                    ->live(),

                Forms\Components\TextInput::make('recaptcha_site_key')
                    ->label('Site Key')
                    ->placeholder('6Lc...')
                    ->required(fn(Forms\Get $get) => $get('recaptcha_enabled'))
                    ->visible(fn(Forms\Get $get) => $get('recaptcha_enabled')),

                Forms\Components\TextInput::make('recaptcha_secret_key')
                    ->label('Secret Key')
                    ->password()
                    ->revealable()
                    ->required(fn(Forms\Get $get) => $get('recaptcha_enabled'))
                    ->visible(fn(Forms\Get $get) => $get('recaptcha_enabled')),

                Forms\Components\TextInput::make('recaptcha_min_score')
                    ->label('Minimum Score')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(1)
                    ->step(0.1)
                    ->default(0.5)
                    ->helperText('Score between 0.0 (bot) and 1.0 (human). Recommended: 0.5')
                    ->visible(fn(Forms\Get $get) => $get('recaptcha_enabled')),

                Forms\Components\Placeholder::make('recaptcha_info')
                    ->content(new \Illuminate\Support\HtmlString(
                        '<div class="text-sm text-gray-500 dark:text-gray-400">' .
                        '<p class="mb-2"><strong>Important:</strong> Use reCAPTCHA v3 keys (not v2).</p>' .
                        '<p>Get your keys from <a href="https://www.google.com/recaptcha/admin" target="_blank" class="text-primary-500 hover:underline">Google reCAPTCHA Admin</a></p>' .
                        '</div>'
                    ))
                    ->visible(fn(Forms\Get $get) => $get('recaptcha_enabled')),
            ])
            ->statePath('recaptchaData');
    }

    public function saveRecaptcha(): void
    {
        $booleanFields = ['recaptcha_enabled'];

        foreach ($this->recaptchaData as $key => $value) {
            $type = in_array($key, $booleanFields) ? 'boolean' : 'string';
            PlatformSetting::set($key, $value, 'security', $type);
        }

        Notification::make()
            ->title('reCAPTCHA v3 settings saved')
            ->success()
            ->send();
    }

    protected function getForms(): array
    {
        return [
            'recaptchaForm',
        ];
    }
}
