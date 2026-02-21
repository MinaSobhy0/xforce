<?php

namespace Modules\PatientPortal\Providers;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Modules\PatientPortal\Filament\Pages\PortalDashboard;
use Modules\PatientPortal\Filament\Pages\Auth\PortalLogin;
use Modules\PatientPortal\Http\Middleware\EnsurePatientAuthenticated;

class PortalPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('portal')
            ->path('portal')
            ->login(PortalLogin::class)
            ->colors([
                'primary' => Color::Teal,
                'danger' => Color::Rose,
                'gray' => Color::Slate,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
            ])
            ->brandName(fn () => config('patientportal.brand_name', 'Patient Portal'))
            ->darkMode(true)
            ->sidebarCollapsibleOnDesktop()
            ->discoverPages(in: module_path('PatientPortal', 'Filament/Pages'), for: 'Modules\\PatientPortal\\Filament\\Pages')
            ->pages([
                PortalDashboard::class,
            ])
            ->discoverWidgets(in: module_path('PatientPortal', 'Filament/Widgets'), for: 'Modules\\PatientPortal\\Filament\\Widgets')
            ->widgets([])
            // DatePicker click anywhere to open
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): View => view('filament.hooks.datepicker-click')
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                EnsurePatientAuthenticated::class,
            ])
            ->authGuard('patient')
            ->registration(false)
            ->passwordReset(false)
            ->emailVerification(false)
            ->profile(false)
            ->topNavigation(false)
            ->font('Inter')
            ->favicon(asset('favicon.ico'));
    }
}
