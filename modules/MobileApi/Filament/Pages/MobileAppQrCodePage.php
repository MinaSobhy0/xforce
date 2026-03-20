<?php

namespace Modules\MobileApi\Filament\Pages;

use App\Models\TenantAppCode;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Livewire\Attributes\Computed;

class MobileAppQrCodePage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 100;

    protected static string $view = 'mobile_api::filament.pages.mobile-app-qr-code';

    public static function getNavigationLabel(): string
    {
        return __('mobile_api::mobile.filament.qr_page_title');
    }

    public function getTitle(): string
    {
        return __('mobile_api::mobile.filament.qr_page_title');
    }

    #[Computed]
    public function tenant()
    {
        return current_tenant();
    }

    #[Computed]
    public function appCode(): ?TenantAppCode
    {
        $tenant = $this->tenant;

        if (!$tenant) {
            return null;
        }

        return $tenant->getOrCreatePrimaryAppCode();
    }

    #[Computed]
    public function qrCodeSvg(): string
    {
        $appCode = $this->appCode;

        if (!$appCode) {
            return '';
        }

        $renderer = new ImageRenderer(
            new RendererStyle(280, 2),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);

        // Use web_link for QR (https:// URL works with all QR scanners)
        return base64_encode($writer->writeString($appCode->web_link));
    }

    #[Computed]
    public function deepLink(): string
    {
        return $this->appCode?->deep_link ?? '';
    }

    #[Computed]
    public function webLink(): string
    {
        return $this->appCode?->web_link ?? '';
    }

    #[Computed]
    public function code(): string
    {
        return $this->appCode?->code ?? '';
    }

    public function copyDeepLink(): void
    {
        $this->dispatch('copy-to-clipboard', text: $this->deepLink);

        Notification::make()
            ->title(__('mobile_api::mobile.filament.link_copied'))
            ->success()
            ->send();
    }

    public function downloadQr(): void
    {
        // The download is handled client-side via JavaScript
        Notification::make()
            ->title('QR Code download started')
            ->success()
            ->send();
    }
}
