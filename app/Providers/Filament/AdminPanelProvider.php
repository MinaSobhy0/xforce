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
use Filament\Support\Enums\MaxWidth;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Clinic Owner Portal - sys.xforcehr.com/admin
 *
 * This panel is for clinic OWNERS to manage their SUBSCRIPTION.
 * NOT for managing clinic operations (patients, services, etc.)
 *
 * For clinic operations, use TenantPanelProvider at tenant.xforcehr.com/admin
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->domain('xforcehr.com')
            ->login()
            ->authGuard('owner')
            ->brandName('XForce')

            // Show the uploaded platform logo on the login screen (and the
            // sidebar brand slot) instead of the wordmark. Reuses the same
            // platform_logo PlatformSetting the super-admin panel uses;
            // falls back to the brandName text when unset.
            ->brandLogo(function () {
                $logo = \App\Models\PlatformSetting::get('platform_logo');

                return $logo ? asset('storage/'.$logo) : null;
            })
            ->brandLogoHeight('3rem')
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): string => \Illuminate\Support\Facades\Blade::render('<x-filament-topbar-reload />'),
            )
            ->colors([
                'primary' => Color::Blue,
            ])
            ->darkMode()
            ->favicon(function () {
                $favicon = \App\Models\PlatformSetting::get('favicon');
                return $favicon ? asset('storage/' . $favicon) : null;
            })

            // Sidebar settings
            ->sidebarCollapsibleOnDesktop(false)
            ->sidebarFullyCollapsibleOnDesktop(false)

            // Match the tenant panel's wider canvas (~1536px instead of
            // Filament's default ~1280px).
            ->maxContentWidth(MaxWidth::ScreenTwoExtraLarge)

            // Navigation Groups for clinic owner portal
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

            // Discover ONLY clinic owner portal resources (subscription, invoices, support)
            // NO tenant module resources here (no Patients, Services, etc.)
            ->discoverResources(in: app_path('Filament/OwnerPortal/Resources'), for: 'App\\Filament\\OwnerPortal\\Resources')
            ->discoverPages(in: app_path('Filament/OwnerPortal/Pages'), for: 'App\\Filament\\OwnerPortal\\Pages')
            ->discoverWidgets(in: app_path('Filament/OwnerPortal/Widgets'), for: 'App\\Filament\\OwnerPortal\\Widgets')

            // No default pages/widgets - use discovered ones
            ->pages([])
            ->widgets([])

            // Middleware - NO IdentifyTenant here (sys subdomain uses public schema)
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
                \App\Http\Middleware\SetLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\TwoFactorEnforce::class,
                // H-10: intra-tenant branch isolation (log-only until
                // config('security.branch.enforce') is enabled).
                \App\Http\Middleware\EnforceBranchAccess::class,
            ]);
    }
}
