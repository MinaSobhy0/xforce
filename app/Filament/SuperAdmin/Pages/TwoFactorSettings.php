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
use Illuminate\Support\Facades\Auth;

class TwoFactorSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Two-Factor Auth';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.super-admin.pages.two-factor-settings';

    public ?array $data = [];
    public ?array $personalData = [];
    public bool $showQrCode = false;
    public bool $showRecoveryCodes = false;
    public ?string $qrCodeSvg = null;
    public ?string $twoFactorSecret = null;
    public array $recoveryCodes = [];
    public ?string $confirmationCode = null;

    public function mount(): void
    {
        $user = Auth::user();

        // Check if user has 2FA enabled
        if ($user->hasEnabledTwoFactorAuthentication()) {
            $this->showQrCode = false;
        } elseif ($user->hasTwoFactorPending()) {
            $this->showQrCode = true;
            $this->qrCodeSvg = $user->getTwoFactorQrCodeSvg();
            $this->twoFactorSecret = $user->getTwoFactorSecret();
        }

        // Platform-wide 2FA policy settings
        $this->form->fill([
            'require_2fa_super_admin' => PlatformSetting::get('require_2fa_super_admin', true),
            'require_2fa_admin' => PlatformSetting::get('require_2fa_admin', false),
            'require_2fa_tenant_owner' => PlatformSetting::get('require_2fa_tenant_owner', false),
            'allow_remember_device' => PlatformSetting::get('allow_remember_device', true),
            'remember_device_days' => PlatformSetting::get('remember_device_days', 30),
            'recovery_codes_count' => PlatformSetting::get('recovery_codes_count', 8),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Platform 2FA Policy')
                    ->description('Configure two-factor authentication requirements for the platform')
                    ->schema([
                        Forms\Components\Toggle::make('require_2fa_super_admin')
                            ->label('Require 2FA for Super Admins')
                            ->helperText('Super admins must have 2FA enabled'),

                        Forms\Components\Toggle::make('require_2fa_admin')
                            ->label('Require 2FA for Tenant Admins')
                            ->helperText('Tenant administrators must have 2FA enabled'),

                        Forms\Components\Toggle::make('require_2fa_tenant_owner')
                            ->label('Require 2FA for Tenant Owners')
                            ->helperText('Tenant account owners must have 2FA enabled'),

                        Forms\Components\Toggle::make('allow_remember_device')
                            ->label('Allow "Remember this device"')
                            ->helperText('Users can skip 2FA on trusted devices')
                            ->live(),

                        Forms\Components\TextInput::make('remember_device_days')
                            ->label('Remember Device Duration (days)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(90)
                            ->visible(fn (Forms\Get $get) => $get('allow_remember_device')),

                        Forms\Components\TextInput::make('recovery_codes_count')
                            ->label('Recovery Codes Count')
                            ->numeric()
                            ->minValue(4)
                            ->maxValue(16)
                            ->helperText('Number of backup codes generated'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        $user = Auth::user();
        $actions = [];

        if (!$user->hasEnabledTwoFactorAuthentication() && !$user->hasTwoFactorPending()) {
            $actions[] = Action::make('enable_2fa')
                ->label('Enable 2FA')
                ->icon('heroicon-o-shield-check')
                ->color('success')
                ->action('enableTwoFactor');
        }

        if ($user->hasTwoFactorPending()) {
            $actions[] = Action::make('cancel_setup')
                ->label('Cancel Setup')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->action('cancelTwoFactorSetup');
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $actions[] = Action::make('view_recovery_codes')
                ->label('View Recovery Codes')
                ->icon('heroicon-o-key')
                ->color('gray')
                ->action('viewRecoveryCodes');

            $actions[] = Action::make('regenerate_recovery_codes')
                ->label('Regenerate Recovery Codes')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('This will invalidate all existing recovery codes. Make sure to save the new ones.')
                ->action('regenerateRecoveryCodes');

            $actions[] = Action::make('disable_2fa')
                ->label('Disable 2FA')
                ->icon('heroicon-o-shield-exclamation')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Disabling 2FA will make your account less secure. Are you sure?')
                ->action('disableTwoFactor');
        }

        $actions[] = Action::make('save')
            ->label('Save Policy Settings')
            ->action('save')
            ->color('primary');

        return $actions;
    }

    public function enableTwoFactor(): void
    {
        $user = Auth::user();
        $secret = $user->enableTwoFactorAuthentication();

        $this->showQrCode = true;
        $this->qrCodeSvg = $user->getTwoFactorQrCodeSvg();
        $this->twoFactorSecret = $secret;

        Notification::make()
            ->title('2FA Setup Started')
            ->body('Scan the QR code with your authenticator app and enter the code to confirm.')
            ->info()
            ->send();
    }

    public function confirmTwoFactor(): void
    {
        $this->validate([
            'confirmationCode' => 'required|string|size:6',
        ]);

        $user = Auth::user();

        if ($user->confirmTwoFactorAuthentication($this->confirmationCode)) {
            $this->showQrCode = false;
            $this->qrCodeSvg = null;
            $this->twoFactorSecret = null;
            $this->confirmationCode = null;

            // Show recovery codes after successful setup
            $this->recoveryCodes = $user->getRecoveryCodes();
            $this->showRecoveryCodes = true;

            Notification::make()
                ->title('2FA Enabled Successfully')
                ->body('Two-factor authentication has been enabled for your account.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Invalid Code')
                ->body('The code you entered is invalid. Please try again.')
                ->danger()
                ->send();
        }
    }

    public function cancelTwoFactorSetup(): void
    {
        $user = Auth::user();
        $user->disableTwoFactorAuthentication();

        $this->showQrCode = false;
        $this->qrCodeSvg = null;
        $this->twoFactorSecret = null;

        Notification::make()
            ->title('2FA Setup Cancelled')
            ->info()
            ->send();
    }

    public function disableTwoFactor(): void
    {
        $user = Auth::user();
        $user->disableTwoFactorAuthentication();

        $this->showQrCode = false;
        $this->showRecoveryCodes = false;
        $this->recoveryCodes = [];

        Notification::make()
            ->title('2FA Disabled')
            ->body('Two-factor authentication has been disabled for your account.')
            ->warning()
            ->send();
    }

    public function viewRecoveryCodes(): void
    {
        $user = Auth::user();
        $this->recoveryCodes = $user->getRecoveryCodes();
        $this->showRecoveryCodes = true;
    }

    public function hideRecoveryCodes(): void
    {
        $this->showRecoveryCodes = false;
        $this->recoveryCodes = [];
    }

    public function regenerateRecoveryCodes(): void
    {
        $user = Auth::user();
        $this->recoveryCodes = $user->regenerateRecoveryCodes();
        $this->showRecoveryCodes = true;

        Notification::make()
            ->title('Recovery Codes Regenerated')
            ->body('Your old recovery codes have been invalidated. Save the new ones.')
            ->success()
            ->send();
    }

    public function save(): void
    {
        $data = $this->form->getState();

        PlatformSetting::set('require_2fa_super_admin', $data['require_2fa_super_admin'], 'security', 'boolean');
        PlatformSetting::set('require_2fa_admin', $data['require_2fa_admin'], 'security', 'boolean');
        PlatformSetting::set('require_2fa_tenant_owner', $data['require_2fa_tenant_owner'], 'security', 'boolean');
        PlatformSetting::set('allow_remember_device', $data['allow_remember_device'], 'security', 'boolean');
        PlatformSetting::set('remember_device_days', $data['remember_device_days'] ?? 30, 'security', 'integer');
        PlatformSetting::set('recovery_codes_count', $data['recovery_codes_count'], 'security', 'integer');

        Notification::make()
            ->title('2FA Policy Settings saved')
            ->success()
            ->send();
    }

    public function getUser()
    {
        return Auth::user();
    }
}
