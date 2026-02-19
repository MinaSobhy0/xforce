<?php

namespace Modules\Auth\Resources\TwoFactorSetupResource\Pages;

use Modules\Auth\Resources\TwoFactorSetupResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Pages\Page;
use Filament\Notifications\Notification;
use XLinic\Framework\Core\TwoFactor\TwoFactorService;
use Illuminate\Support\Str;

class ManageTwoFactorSetup extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = TwoFactorSetupResource::class;

    protected static string $view = 'filament.pages.manage-two-factor';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    public function getTitle(): string
    {
        return __('Two-Factor Authentication');
    }

    public function getHeading(): string
    {
        return __('Two-Factor Authentication');
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
            'two_factor_enabled' => $user->two_factor_enabled,
            'backup_codes_generated' => !empty($user->two_factor_backup_codes),
            'confirmation_code' => '',
        ];

        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Current Status'))
                    ->schema([
                        Forms\Components\Placeholder::make('current_status')
                            ->label(__('Two-Factor Authentication'))
                            ->content(function () {
                                $user = auth()->user();
                                if ($user->two_factor_enabled) {
                                    return __('Enabled and Active');
                                } else {
                                    return __('Disabled');
                                }
                            }),

                        Forms\Components\Placeholder::make('confirmed_at')
                            ->label(__('Confirmed At'))
                            ->content(fn () => auth()->user()->two_factor_confirmed_at?->format('M d, Y H:i') ?? __('Not confirmed'))
                            ->visible(fn () => auth()->user()->two_factor_enabled),

                        Forms\Components\Placeholder::make('backup_codes')
                            ->label(__('Backup Codes'))
                            ->content(function () {
                                $user = auth()->user();
                                if ($user->two_factor_backup_codes) {
                                    $codes = json_decode($user->two_factor_backup_codes, true);
                                    return count($codes) . ' ' . __('codes available');
                                }
                                return __('Not generated');
                            })
                            ->visible(fn () => auth()->user()->two_factor_enabled),
                    ])
                    ->collapsed(false),

                Forms\Components\Section::make(__('Setup Two-Factor Authentication'))
                    ->description(__('Use an authenticator app like Google Authenticator or Authy to scan the QR code below.'))
                    ->schema([
                        Forms\Components\Placeholder::make('qr_code')
                            ->label(__('QR Code'))
                            ->content(function () {
                                if (!class_exists(TwoFactorService::class)) {
                                    return __('Two-factor service not available');
                                }
                                $twoFactorService = app(TwoFactorService::class);
                                $user = auth()->user();

                                if (!$user->two_factor_secret) {
                                    $secret = $twoFactorService->generateSecretKey();
                                    $user->update(['two_factor_secret' => $secret]);
                                }

                                $qrCodeUrl = $twoFactorService->getQRCodeUrl(
                                    config('app.name'),
                                    $user->email,
                                    $user->two_factor_secret
                                );

                                return '<div style="text-align: center;"><img src="' . $qrCodeUrl . '" alt="QR Code" /></div>';
                            })
                            ->html()
                            ->visible(fn () => !auth()->user()->two_factor_enabled),

                        Forms\Components\Placeholder::make('manual_entry')
                            ->label(__('Manual Entry Key'))
                            ->content(function () {
                                $user = auth()->user();
                                if ($user->two_factor_secret) {
                                    return '<code style="padding: 8px; background: #f5f5f5; border-radius: 4px;">' .
                                           chunk_split($user->two_factor_secret, 4, ' ') . '</code>';
                                }
                                return '';
                            })
                            ->html()
                            ->visible(fn () => !auth()->user()->two_factor_enabled),

                        Forms\Components\TextInput::make('confirmation_code')
                            ->label(__('Verification Code'))
                            ->helperText(__('Enter the 6-digit code from your authenticator app'))
                            ->length(6)
                            ->numeric()
                            ->visible(fn () => !auth()->user()->two_factor_enabled),

                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('enable_2fa')
                                ->label(__('Enable Two-Factor Authentication'))
                                ->icon('heroicon-o-shield-check')
                                ->color('success')
                                ->action('enableTwoFactor')
                                ->visible(fn () => !auth()->user()->two_factor_enabled),

                            Forms\Components\Actions\Action::make('disable_2fa')
                                ->label(__('Disable Two-Factor Authentication'))
                                ->icon('heroicon-o-shield-exclamation')
                                ->color('danger')
                                ->requiresConfirmation()
                                ->modalDescription(__('This will disable two-factor authentication for your account. Are you sure?'))
                                ->action('disableTwoFactor')
                                ->visible(fn () => auth()->user()->two_factor_enabled),

                            Forms\Components\Actions\Action::make('regenerate_backup_codes')
                                ->label(__('Regenerate Backup Codes'))
                                ->icon('heroicon-o-key')
                                ->color('warning')
                                ->requiresConfirmation()
                                ->modalDescription(__('This will generate new backup codes and invalidate the old ones.'))
                                ->action('regenerateBackupCodes')
                                ->visible(fn () => auth()->user()->two_factor_enabled),

                            Forms\Components\Actions\Action::make('show_backup_codes')
                                ->label(__('Show Backup Codes'))
                                ->icon('heroicon-o-eye')
                                ->color('info')
                                ->action('showBackupCodes')
                                ->visible(fn () => auth()->user()->two_factor_enabled),
                        ])
                        ->columnSpanFull(),
                    ])
                    ->visible(fn () => !auth()->user()->two_factor_enabled || auth()->user()->two_factor_confirmed_at),
            ])
            ->statePath('data');
    }

    public function enableTwoFactor(): void
    {
        $data = $this->form->getState();
        $user = auth()->user();

        if (!class_exists(TwoFactorService::class)) {
            Notification::make()
                ->title(__('Service unavailable'))
                ->body(__('Two-factor authentication service is not available.'))
                ->danger()
                ->send();
            return;
        }

        $twoFactorService = app(TwoFactorService::class);

        if (empty($data['confirmation_code'])) {
            Notification::make()
                ->title(__('Verification code required'))
                ->body(__('Please enter the verification code from your authenticator app.'))
                ->danger()
                ->send();
            return;
        }

        if (!$twoFactorService->verify($user->two_factor_secret, $data['confirmation_code'])) {
            Notification::make()
                ->title(__('Invalid code'))
                ->body(__('The verification code is incorrect. Please try again.'))
                ->danger()
                ->send();
            return;
        }

        // Generate backup codes
        $backupCodes = [];
        for ($i = 0; $i < 10; $i++) {
            $backupCodes[] = strtoupper(Str::random(8));
        }

        $user->update([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
            'two_factor_backup_codes' => json_encode($backupCodes),
        ]);

        // Log the activity
        if (function_exists('activity')) {
            activity()
                ->causedBy($user)
                ->performedOn($user)
                ->log('Two-factor authentication enabled');
        }

        Notification::make()
            ->title(__('Two-factor authentication enabled'))
            ->body(__('Your account is now secured with two-factor authentication.'))
            ->success()
            ->send();

        $this->showBackupCodes();
        $this->fillForms();
    }

    public function disableTwoFactor(): void
    {
        $user = auth()->user();

        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_backup_codes' => null,
        ]);

        // Log the activity
        if (function_exists('activity')) {
            activity()
                ->causedBy($user)
                ->performedOn($user)
                ->log('Two-factor authentication disabled');
        }

        Notification::make()
            ->title(__('Two-factor authentication disabled'))
            ->body(__('Two-factor authentication has been disabled for your account.'))
            ->warning()
            ->send();

        $this->fillForms();
    }

    public function regenerateBackupCodes(): void
    {
        $user = auth()->user();

        // Generate new backup codes
        $backupCodes = [];
        for ($i = 0; $i < 10; $i++) {
            $backupCodes[] = strtoupper(Str::random(8));
        }

        $user->update([
            'two_factor_backup_codes' => json_encode($backupCodes),
        ]);

        // Log the activity
        if (function_exists('activity')) {
            activity()
                ->causedBy($user)
                ->performedOn($user)
                ->log('Two-factor backup codes regenerated');
        }

        Notification::make()
            ->title(__('Backup codes regenerated'))
            ->body(__('New backup codes have been generated. The old codes are no longer valid.'))
            ->success()
            ->send();

        $this->showBackupCodes();
    }

    public function showBackupCodes(): void
    {
        $user = auth()->user();
        $codes = json_decode($user->two_factor_backup_codes, true);

        $codesList = implode('<br>', array_map(fn($code) => '<code style="font-family: monospace; padding: 2px 4px; background: #f0f0f0; border-radius: 3px;">' . $code . '</code>', $codes ?? []));

        Notification::make()
            ->title(__('Your Backup Codes'))
            ->body($codesList)
            ->warning()
            ->persistent()
            ->send();
    }
}
