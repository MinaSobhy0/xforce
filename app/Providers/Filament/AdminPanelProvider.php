<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('XLinic')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->darkMode()

            // Discover resources from modules
            ->discoverResources(in: base_path('modules/Auth/Filament/Resources'), for: 'Modules\\Auth\\Filament\\Resources')
            ->discoverResources(in: base_path('modules/Auth/Resources'), for: 'Modules\\Auth\\Resources')
            ->discoverResources(in: base_path('modules/Core/Filament/Resources'), for: 'Modules\\Core\\Filament\\Resources')
            ->discoverResources(in: base_path('modules/Core/Resources'), for: 'Modules\\Core\\Resources')
            ->discoverResources(in: base_path('modules/Patients/Filament/Resources'), for: 'Modules\\Patients\\Filament\\Resources')
            ->discoverResources(in: base_path('modules/Treatments/Filament/Resources'), for: 'Modules\\Treatments\\Filament\\Resources')

            // Pages
            ->pages([
                Pages\Dashboard::class,
            ])

            // Widgets
            ->widgets([
                Widgets\AccountWidget::class,
            ])

            // Middleware
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
                Authenticate::class,
            ]);
    }
}
