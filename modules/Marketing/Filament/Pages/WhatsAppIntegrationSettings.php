<?php

namespace Modules\Marketing\Filament\Pages;

use App\Models\PlatformSetting;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Crypt;
use Modules\Marketing\Services\Meta\EmbeddedSignupService;
use Modules\Marketing\Services\WhatsAppCredentialsResolver;
use Modules\Marketing\Services\WhatsAppService;

/**
 * Tenant-side page where a clinic owner connects their own Meta WhatsApp
 * Business number via Embedded Signup. Lives at /admin/whatsapp-integration.
 *
 * The actual Meta-hosted popup is launched in JavaScript from the blade
 * view (which reads our platform App ID + Embedded Signup config_id from
 * PlatformSetting). When the popup closes successfully, JS calls
 * $wire.handleSignupCallback(code, phoneNumberId, wabaId) which delegates
 * to EmbeddedSignupService to exchange the code and persist the connection.
 */
class WhatsAppIntegrationSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Settings';

    public static function getNavigationParentItem(): ?string
    {
        return __('core::core.nav_folders.integrations');
    }

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'whatsapp-integration';

    protected static string $view = 'marketing::filament.pages.whatsapp-integration-settings';

    public function getTitle(): string
    {
        return (string) __('marketing::whatsapp.page.title');
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('marketing::whatsapp.page.nav_label');
    }

    /**
     * Properties read by the blade view to render either the
     * disconnected hero or the connected status card.
     */
    public function getViewData(): array
    {
        $tenant = current_tenant();
        $meta = $tenant?->getSetting('messaging.whatsapp.meta') ?? [];

        return [
            'metaAppId' => (string) PlatformSetting::get('whatsapp_meta_app_id', ''),
            'metaConfigId' => (string) PlatformSetting::get('whatsapp_meta_signup_config_id', ''),
            'graphVersion' => (string) PlatformSetting::get('whatsapp_meta_api_version', 'v18.0'),
            'isConnected' => ($meta['status'] ?? null) === 'active',
            'status' => $meta['status'] ?? 'disconnected',
            'displayName' => $meta['display_name'] ?? null,
            'phoneE164' => $meta['phone_e164'] ?? null,
            'wabaId' => $meta['waba_id'] ?? null,
            'phoneNumberId' => $meta['phone_number_id'] ?? null,
            'lastError' => $meta['last_error'] ?? null,
            'connectedAt' => $meta['connected_at'] ?? null,
            'techProviderReady' => filled(PlatformSetting::get('whatsapp_meta_app_id'))
                && filled(PlatformSetting::get('whatsapp_meta_signup_config_id')),
        ];
    }

    /**
     * Invoked by the blade JS bridge after the Meta popup returns
     * successfully. All three fields come from the FB.login response.
     */
    public function handleSignupCallback(string $code, string $phoneNumberId, string $wabaId): void
    {
        $tenant = current_tenant();
        if (! $tenant) {
            Notification::make()
                ->title(__('marketing::whatsapp.notify.no_tenant'))
                ->danger()
                ->send();

            return;
        }

        try {
            app(EmbeddedSignupService::class)->connectTenant(
                tenant: $tenant,
                signup: [
                    'code' => $code,
                    'phone_number_id' => $phoneNumberId,
                    'waba_id' => $wabaId,
                ],
                actor: auth()->user(),
            );

            Notification::make()
                ->title(__('marketing::whatsapp.notify.connected'))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('marketing::whatsapp.notify.connect_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTest')
                ->label(__('marketing::whatsapp.actions.send_test'))
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->visible(fn () => $this->isConnected())
                ->form([
                    Forms\Components\TextInput::make('to')
                        ->label(__('marketing::whatsapp.test.to'))
                        ->placeholder('+201234567890')
                        ->helperText(__('marketing::whatsapp.test.to_help'))
                        ->required(),
                    Forms\Components\Select::make('mode')
                        ->label(__('marketing::whatsapp.test.mode'))
                        ->options([
                            'template' => __('marketing::whatsapp.test.mode_template'),
                            'freeform' => __('marketing::whatsapp.test.mode_freeform'),
                        ])
                        ->default('template')
                        ->live()
                        ->required(),
                    Forms\Components\TextInput::make('template_name')
                        ->label(__('marketing::whatsapp.test.template_name'))
                        ->default('hello_world')
                        ->visible(fn (Forms\Get $get) => $get('mode') === 'template')
                        ->required(fn (Forms\Get $get) => $get('mode') === 'template'),
                    Forms\Components\TextInput::make('template_language')
                        ->label(__('marketing::whatsapp.test.template_language'))
                        ->default('en_US')
                        ->visible(fn (Forms\Get $get) => $get('mode') === 'template')
                        ->required(fn (Forms\Get $get) => $get('mode') === 'template'),
                    Forms\Components\Textarea::make('message')
                        ->label(__('marketing::whatsapp.test.message'))
                        ->default(__('marketing::whatsapp.test.default_body'))
                        ->visible(fn (Forms\Get $get) => $get('mode') === 'freeform')
                        ->required(fn (Forms\Get $get) => $get('mode') === 'freeform')
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    $svc = app(WhatsAppService::class);

                    $result = ($data['mode'] ?? 'template') === 'template'
                        ? $svc->sendTemplateMessage(
                            $data['to'],
                            $data['template_name'] ?: 'hello_world',
                            $data['template_language'] ?: 'en_US'
                        )
                        : $svc->sendTextMessage($data['to'], $data['message']);

                    if ($result['success'] ?? false) {
                        Notification::make()
                            ->title(__('marketing::whatsapp.notify.test_sent'))
                            ->body(($result['message_id'] ?? null) ? "Message ID: {$result['message_id']}" : null)
                            ->success()
                            ->send();
                    } else {
                        $body = $result['error'] ?? 'Unknown error';
                        if (! empty($result['error_code'])) {
                            $body = "[code {$result['error_code']}] {$body}";
                        }
                        Notification::make()
                            ->title(__('marketing::whatsapp.notify.test_failed'))
                            ->body($body)
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

            Action::make('disconnect')
                ->label(__('marketing::whatsapp.actions.disconnect'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('marketing::whatsapp.disconnect.heading'))
                ->modalDescription(__('marketing::whatsapp.disconnect.description'))
                ->visible(fn () => $this->isConnected())
                ->action(function (): void {
                    $tenant = current_tenant();
                    if ($tenant) {
                        app(EmbeddedSignupService::class)->disconnect($tenant);
                    }

                    Notification::make()
                        ->title(__('marketing::whatsapp.notify.disconnected'))
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function isConnected(): bool
    {
        $tenant = current_tenant();

        return ($tenant?->getSetting('messaging.whatsapp.meta.status')) === 'active';
    }
}
