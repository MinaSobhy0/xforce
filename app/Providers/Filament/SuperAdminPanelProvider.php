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
use Filament\View\PanelsRenderHook;
use App\Models\PlatformSetting;
use Illuminate\Contracts\View\View;
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
            ->domain('sys.x-linic.com')
            ->brandName(PlatformSetting::get('platform_name', 'XLinic Platform'))
            ->brandLogo(function () {
                $logo = PlatformSetting::get('platform_logo');
                return $logo ? asset('storage/' . $logo) : null;
            })
            ->brandLogoHeight('5rem')
            ->favicon(function () {
                $favicon = PlatformSetting::get('favicon');
                return $favicon ? asset('storage/' . $favicon) : null;
            })

            // Colors (dynamic from platform settings)
            ->colors(function () {
                $primaryColor = PlatformSetting::get('primary_color', '#6366f1'); // Default indigo
                return [
                    'primary' => Color::hex($primaryColor),
                    'danger'  => Color::Rose,
                    'success' => Color::Emerald,
                    'warning' => Color::Amber,
                    'info'    => Color::Sky,
                ];
            })

            // Auth
            ->login()

            // Dark Mode
            ->darkMode()

            // Sidebar settings - ensure visible on desktop
            ->sidebarCollapsibleOnDesktop(false)
            ->sidebarFullyCollapsibleOnDesktop(false)

            // Navigation Groups with icons for double sidebar
            ->navigationGroups([
                NavigationGroup::make('Tenants')
                    ->label(__('Tenants'))
                    ->icon('heroicon-o-building-office-2'),
                NavigationGroup::make('Billing')
                    ->label(__('Billing'))
                    ->icon('heroicon-o-credit-card'),
                NavigationGroup::make('Plans & Modules')
                    ->label(__('Plans & Modules'))
                    ->icon('heroicon-o-cube'),
                NavigationGroup::make('Support')
                    ->label(__('Support'))
                    ->icon('heroicon-o-lifebuoy'),
                NavigationGroup::make('Monitoring')
                    ->label(__('Monitoring'))
                    ->icon('heroicon-o-chart-bar'),
                NavigationGroup::make('System')
                    ->label(__('System'))
                    ->icon('heroicon-o-cog-6-tooth'),
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

            // SPA Mode disabled - was causing sidebar issues
            // ->spa()

            // Plugins
            ->plugin(
                SpatieLaravelTranslatablePlugin::make()
                    ->defaultLocales(['en', 'ar'])
            )

            // DatePicker click anywhere to open
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): View => view('filament.hooks.datepicker-click')
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
