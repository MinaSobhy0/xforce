<?php

namespace App\Providers\Filament;

use App\Http\Middleware\IdentifyTenant;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
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

            // Sidebar settings
            ->sidebarCollapsibleOnDesktop(false)
            ->sidebarFullyCollapsibleOnDesktop(false)

            // Navigation Groups with icons for tenant portal
            ->navigationGroups([
                NavigationGroup::make('Subscription')
                    ->label(__('Subscription'))
                    ->icon('heroicon-o-credit-card'),
                NavigationGroup::make('Support')
                    ->label(__('Support'))
                    ->icon('heroicon-o-lifebuoy'),
                NavigationGroup::make('Account')
                    ->label(__('Account'))
                    ->icon('heroicon-o-user-circle'),
            ])

            // Discover tenant portal resources, pages, and widgets
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')

            // Discover Core module resources, pages, and widgets
            ->discoverResources(in: base_path('modules/Core/Filament/Resources'), for: 'Modules\\Core\\Filament\\Resources')
            ->discoverPages(in: base_path('modules/Core/Filament/Pages'), for: 'Modules\\Core\\Filament\\Pages')

            // Discover Auth module resources
            ->discoverResources(in: base_path('modules/Auth/Filament/Resources'), for: 'Modules\\Auth\\Filament\\Resources')

            // Discover Patients module resources
            ->discoverResources(in: base_path('modules/Patients/Filament/Resources'), for: 'Modules\\Patients\\Filament\\Resources')

            // Discover Treatments module resources
            ->discoverResources(in: base_path('modules/Treatments/Filament/Resources'), for: 'Modules\\Treatments\\Filament\\Resources')

            // No default pages/widgets - use discovered ones
            ->pages([])
            ->widgets([])

            // Middleware - IdentifyTenant must come FIRST to set up database connection
            ->middleware([
                IdentifyTenant::class,
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
