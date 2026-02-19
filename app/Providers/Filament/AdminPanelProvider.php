<?php

namespace App\Providers\Filament;

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
            ->favicon(asset('favicon.ico'))

            // Colors
            ->colors([
                'primary' => Color::Blue,
                'danger' => Color::Rose,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'info' => Color::Sky,
            ])

            // Dark Mode
            ->darkMode()

            // Sidebar Configuration
            ->sidebarCollapsibleOnDesktop()

            // Navigation Groups
            ->navigationGroups([
                NavigationGroup::make('CRM')
                    ->icon('heroicon-o-users'),
                NavigationGroup::make('User Management')
                    ->icon('heroicon-o-user-group'),
                NavigationGroup::make('Settings')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsed(),
                NavigationGroup::make('Account')
                    ->icon('heroicon-o-user-circle')
                    ->collapsed(),
            ])

            // Resource Discovery - App Resources
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')

            // Resource Discovery - Module Resources
            ->discoverResources(in: base_path('modules/Auth/Filament/Resources'), for: 'Modules\\Auth\\Filament\\Resources')
            ->discoverResources(in: base_path('modules/Auth/Resources'), for: 'Modules\\Auth\\Resources')
            ->discoverResources(in: base_path('modules/Core/Filament/Resources'), for: 'Modules\\Core\\Filament\\Resources')
            ->discoverResources(in: base_path('modules/Core/Resources'), for: 'Modules\\Core\\Resources')
            ->discoverResources(in: base_path('modules/Patients/Filament/Resources'), for: 'Modules\\Patients\\Filament\\Resources')
            ->discoverResources(in: base_path('modules/Treatments/Filament/Resources'), for: 'Modules\\Treatments\\Filament\\Resources')
            ->discoverResources(in: base_path('modules/Booking/Filament/Resources'), for: 'Modules\\Booking\\Filament\\Resources')
            ->discoverResources(in: base_path('modules/Billing/Filament/Resources'), for: 'Modules\\Billing\\Filament\\Resources')
            ->discoverResources(in: base_path('modules/Equipment/Filament/Resources'), for: 'Modules\\Equipment\\Filament\\Resources')
            ->discoverResources(in: base_path('modules/Inventory/Filament/Resources'), for: 'Modules\\Inventory\\Filament\\Resources')

            // Page Discovery
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverPages(in: base_path('modules/Core/Filament/Pages'), for: 'Modules\\Core\\Filament\\Pages')

            ->pages([
                Pages\Dashboard::class,
            ])

            // Widget Discovery
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])

            // Global Search
            ->globalSearch(true)
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])

            // Notifications
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')

            // SPA Mode for smoother navigation
            ->spa()

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
            ])

            // Custom CSS for double sidebar effect (temporarily disabled for debugging)
            // ->renderHook(
            //     PanelsRenderHook::HEAD_END,
            //     fn (): string => Blade::render('<style>' . file_get_contents(resource_path('css/filament/admin/theme.css')) . '</style>')
            // )
            ;
    }
}
