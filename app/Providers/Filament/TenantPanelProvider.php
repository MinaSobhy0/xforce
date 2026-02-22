<?php

namespace App\Providers\Filament;

use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\RequireTenant;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\SpatieLaravelTranslatablePlugin;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class TenantPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('tenant')
            ->path('admin')
            ->login()
            ->brandName(fn () => $this->getTenantBrandName())
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->darkMode()
            ->spa()
            ->favicon(function () {
                $favicon = \App\Models\PlatformSetting::get('favicon');
                return $favicon ? asset('storage/' . $favicon) : null;
            })

            // Sidebar settings
            ->sidebarCollapsibleOnDesktop()
            ->sidebarFullyCollapsibleOnDesktop()

            // Navigation Groups for clinic operations
            ->navigationGroups([
                NavigationGroup::make('Operations')
                    ->label(__('Operations'))
                    ->icon('heroicon-o-calendar'),
                NavigationGroup::make('HR')
                    ->label(__('HR'))
                    ->icon('heroicon-o-user-group'),
                NavigationGroup::make('Inventory')
                    ->label(__('Inventory'))
                    ->icon('heroicon-o-cube'),
                NavigationGroup::make('Finance')
                    ->label(__('Finance'))
                    ->icon('heroicon-o-banknotes'),
                NavigationGroup::make('Marketing')
                    ->label(__('Marketing'))
                    ->icon('heroicon-o-megaphone'),
                NavigationGroup::make('Reports')
                    ->label(__('Reports'))
                    ->icon('heroicon-o-chart-bar'),
                NavigationGroup::make('Settings')
                    ->label(__('Settings'))
                    ->icon('heroicon-o-cog-6-tooth'),
            ])

            // Discover Core module resources and pages
            ->discoverResources(in: base_path('modules/Core/Filament/Resources'), for: 'Modules\\Core\\Filament\\Resources')
            ->discoverPages(in: base_path('modules/Core/Filament/Pages'), for: 'Modules\\Core\\Filament\\Pages')

            // Discover Auth module resources
            ->discoverResources(in: base_path('modules/Auth/Filament/Resources'), for: 'Modules\\Auth\\Filament\\Resources')

            // Discover Patients module resources
            ->discoverResources(in: base_path('modules/Patients/Filament/Resources'), for: 'Modules\\Patients\\Filament\\Resources')

            // Discover Services module resources and pages
            ->discoverResources(in: base_path('modules/Services/Filament/Resources'), for: 'Modules\\Services\\Filament\\Resources')
            ->discoverPages(in: base_path('modules/Services/Filament/Pages'), for: 'Modules\\Services\\Filament\\Pages')

            // Discover Equipment module resources
            ->discoverResources(in: base_path('modules/Equipment/Filament/Resources'), for: 'Modules\\Equipment\\Filament\\Resources')

            // Discover Booking module resources and pages
            ->discoverResources(in: base_path('modules/Booking/Filament/Resources'), for: 'Modules\\Booking\\Filament\\Resources')
            ->discoverPages(in: base_path('modules/Booking/Filament/Pages'), for: 'Modules\\Booking\\Filament\\Pages')

            // Discover Billing module resources
            ->discoverResources(in: base_path('modules/Billing/Filament/Resources'), for: 'Modules\\Billing\\Filament\\Resources')
            ->discoverPages(in: base_path('modules/Billing/Filament/Pages'), for: 'Modules\\Billing\\Filament\\Pages')

            // Discover Accounting module resources
            ->discoverResources(in: base_path('modules/Accounting/Filament/Resources'), for: 'Modules\\Accounting\\Filament\\Resources')
            ->discoverPages(in: base_path('modules/Accounting/Filament/Pages'), for: 'Modules\\Accounting\\Filament\\Pages')

            // Discover Packages module resources
            ->discoverResources(in: base_path('modules/Packages/Filament/Resources'), for: 'Modules\\Packages\\Filament\\Resources')

            // Discover GiftCards module resources
            ->discoverResources(in: base_path('modules/GiftCards/Filament/Resources'), for: 'Modules\\GiftCards\\Filament\\Resources')

            // Discover Memberships module resources
            ->discoverResources(in: base_path('modules/Memberships/Filament/Resources'), for: 'Modules\\Memberships\\Filament\\Resources')

            // Discover Inventory module resources
            ->discoverResources(in: base_path('modules/Inventory/Filament/Resources'), for: 'Modules\\Inventory\\Filament\\Resources')

            // Discover Staff module resources
            ->discoverResources(in: base_path('modules/Staff/Filament/Resources'), for: 'Modules\\Staff\\Filament\\Resources')

            // Discover Payroll module resources
            ->discoverResources(in: base_path('modules/Payroll/Filament/Resources'), for: 'Modules\\Payroll\\Filament\\Resources')

            // Discover Marketing module resources
            ->discoverResources(in: base_path('modules/Marketing/Filament/Resources'), for: 'Modules\\Marketing\\Filament\\Resources')
            ->discoverPages(in: base_path('modules/Marketing/Filament/Pages'), for: 'Modules\\Marketing\\Filament\\Pages')

            // Discover Loyalty module resources
            ->discoverResources(in: base_path('modules/Loyalty/Filament/Resources'), for: 'Modules\\Loyalty\\Filament\\Resources')
            ->discoverPages(in: base_path('modules/Loyalty/Filament/Pages'), for: 'Modules\\Loyalty\\Filament\\Pages')

            // Discover Reporting module pages
            ->discoverPages(in: base_path('modules/Reporting/Filament/Pages'), for: 'Modules\\Reporting\\Filament\\Pages')

            // Default pages (widgets are defined in Dashboard class)
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->widgets([])

            // Plugins
            ->plugin(
                SpatieLaravelTranslatablePlugin::make()
                    ->defaultLocales(['en', 'ar'])
            )

            // Branch Switcher in the topbar
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): View => view('filament.hooks.branch-switcher')
            )

            // Middleware - Session must start before tenant identification for CSRF
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                IdentifyTenant::class,
                RequireTenant::class,
                AuthenticateSession::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\TwoFactorEnforce::class,
            ]);
    }

    /**
     * Get the brand name from the current tenant.
     */
    protected function getTenantBrandName(): string
    {
        $tenant = app('currentTenant') ?? null;
        return $tenant?->name ?? 'XLinic Clinic';
    }
}
