<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Filament\SpatieLaravelTranslatablePlugin;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class SuperAdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            // Identity
            ->id('super-admin')
            ->path('platform')
            ->brandName('XLinic Platform')
            ->favicon(asset('favicon.ico'))

            // Colors (Indigo theme for platform)
            ->colors([
                'primary' => Color::Indigo,
                'danger'  => Color::Rose,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'info'    => Color::Sky,
            ])

            // Auth
            ->login()

            // Dark Mode
            ->darkMode()

            // Navigation Groups
            ->navigationGroups([
                NavigationGroup::make('Tenants')
                    ->icon('heroicon-o-building-office-2')
                    ->label(__('Tenants')),
                NavigationGroup::make('Billing')
                    ->icon('heroicon-o-credit-card')
                    ->label(__('Billing')),
                NavigationGroup::make('Plans & Modules')
                    ->icon('heroicon-o-puzzle-piece')
                    ->label(__('Plans & Modules')),
                NavigationGroup::make('Support')
                    ->icon('heroicon-o-ticket')
                    ->label(__('Support')),
                NavigationGroup::make('Monitoring')
                    ->icon('heroicon-o-chart-bar')
                    ->label(__('Monitoring')),
                NavigationGroup::make('System')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->label(__('System'))
                    ->collapsed(),
            ])

            // Resource Discovery
            ->discoverResources(
                in: app_path('Filament/SuperAdmin/Resources'),
                for: 'App\\Filament\\SuperAdmin\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/SuperAdmin/Pages'),
                for: 'App\\Filament\\SuperAdmin\\Pages'
            )
            ->discoverWidgets(
                in: app_path('Filament/SuperAdmin/Widgets'),
                for: 'App\\Filament\\SuperAdmin\\Widgets'
            )

            // Dashboard Widgets
            ->widgets([
                Widgets\AccountWidget::class,
                \App\Filament\SuperAdmin\Widgets\PlatformStatsWidget::class,
                \App\Filament\SuperAdmin\Widgets\RevenueTrendWidget::class,
                \App\Filament\SuperAdmin\Widgets\ClinicsByPlanWidget::class,
                \App\Filament\SuperAdmin\Widgets\ModulePopularityWidget::class,
                \App\Filament\SuperAdmin\Widgets\RecentSignupsWidget::class,
                \App\Filament\SuperAdmin\Widgets\NeedsAttentionWidget::class,
            ])

            // Global Search
            ->globalSearch(true)
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])

            // Notifications
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')

            // SPA Mode
            ->spa()

            // Plugins
            ->plugin(
                SpatieLaravelTranslatablePlugin::make()
                    ->defaultLocales(['en', 'ar'])
            )

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
