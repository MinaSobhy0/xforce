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
            ->login(\App\Filament\Pages\Auth\Login::class)
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
                NavigationGroup::make('Projects')
                    ->label(__('Projects'))
                    ->icon('heroicon-o-rectangle-stack'),
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
                NavigationGroup::make('Mobile App')
                    ->label(__('Mobile App'))
                    ->icon('heroicon-o-device-phone-mobile')
                    ->collapsed(),
            ])

            // Discover Core module resources and pages
            ->discoverResources(in: base_path('modules/Core/Filament/Resources'), for: 'Modules\\Core\\Filament\\Resources')
            ->discoverPages(in: base_path('modules/Core/Filament/Pages'), for: 'Modules\\Core\\Filament\\Pages')

            // Discover Auth module resources
            ->discoverResources(in: base_path('modules/Auth/Filament/Resources'), for: 'Modules\\Auth\\Filament\\Resources')

            // Discover Patients module resources and pages
            ->discoverResources(in: base_path('modules/Patients/Filament/Resources'), for: 'Modules\\Patients\\Filament\\Resources')
            ->discoverPages(in: base_path('modules/Patients/Filament/Pages'), for: 'Modules\\Patients\\Filament\\Pages')

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

            // Discover Packages module resources and pages
            ->discoverResources(in: base_path('modules/Packages/Filament/Resources'), for: 'Modules\\Packages\\Filament\\Resources')
            ->discoverPages(in: base_path('modules/Packages/Filament/Pages'), for: 'Modules\\Packages\\Filament\\Pages')

            // Discover GiftCards module resources and pages
            ->discoverResources(in: base_path('modules/GiftCards/Filament/Resources'), for: 'Modules\\GiftCards\\Filament\\Resources')
            ->discoverPages(in: base_path('modules/GiftCards/Filament/Pages'), for: 'Modules\\GiftCards\\Filament\\Pages')

            // Discover Memberships module resources
            ->discoverResources(in: base_path('modules/Memberships/Filament/Resources'), for: 'Modules\\Memberships\\Filament\\Resources')

            // Discover Inventory module resources and pages
            ->discoverResources(in: base_path('modules/Inventory/Filament/Resources'), for: 'Modules\\Inventory\\Filament\\Resources')
            ->discoverPages(in: base_path('modules/Inventory/Filament/Pages'), for: 'Modules\\Inventory\\Filament\\Pages')

            // Discover Staff module resources
            ->discoverResources(in: base_path('modules/Staff/Filament/Resources'), for: 'Modules\\Staff\\Filament\\Resources')

            // Discover Payroll module resources
            ->discoverResources(in: base_path('modules/Payroll/Filament/Resources'), for: 'Modules\\Payroll\\Filament\\Resources')

            // Discover Attendance module resources and pages
            ->discoverResources(in: base_path('modules/Attendance/Filament/Resources'), for: 'Modules\\Attendance\\Filament\\Resources')
            ->discoverPages(in: base_path('modules/Attendance/Filament/Pages'), for: 'Modules\\Attendance\\Filament\\Pages')

            // Discover TreatmentPlans module resources
            ->discoverResources(in: base_path('modules/TreatmentPlans/Filament/Resources'), for: 'Modules\\TreatmentPlans\\Filament\\Resources')
            ->discoverPages(in: base_path('modules/TreatmentPlans/Filament/Pages'), for: 'Modules\\TreatmentPlans\\Filament\\Pages')

            // Discover Marketing module resources
            ->discoverResources(in: base_path('modules/Marketing/Filament/Resources'), for: 'Modules\\Marketing\\Filament\\Resources')
            ->discoverPages(in: base_path('modules/Marketing/Filament/Pages'), for: 'Modules\\Marketing\\Filament\\Pages')

            // Discover Loyalty module resources
            ->discoverResources(in: base_path('modules/Loyalty/Filament/Resources'), for: 'Modules\\Loyalty\\Filament\\Resources')
            ->discoverPages(in: base_path('modules/Loyalty/Filament/Pages'), for: 'Modules\\Loyalty\\Filament\\Pages')

            // Discover Reporting module pages
            ->discoverPages(in: base_path('modules/Reporting/Filament/Pages'), for: 'Modules\\Reporting\\Filament\\Pages')

            // Discover Prescriptions module resources
            ->discoverResources(in: base_path('modules/Prescriptions/Filament/Resources'), for: 'Modules\\Prescriptions\\Filament\\Resources')

            // Discover Assets module resources
            ->discoverResources(in: base_path('modules/Assets/Filament/Resources'), for: 'Modules\\Assets\\Filament\\Resources')

            // Discover Evaluations module resources and pages
            ->discoverResources(in: base_path('modules/Evaluations/Filament/Resources'), for: 'Modules\\Evaluations\\Filament\\Resources')
            ->discoverPages(in: base_path('modules/Evaluations/Filament/Pages'), for: 'Modules\\Evaluations\\Filament\\Pages')

            // Discover MobileApi module resources and pages
            ->discoverResources(in: base_path('modules/MobileApi/Filament/Resources'), for: 'Modules\\MobileApi\\Filament\\Resources')
            ->discoverPages(in: base_path('modules/MobileApi/Filament/Pages'), for: 'Modules\\MobileApi\\Filament\\Pages')

            // Discover Projects module resources and pages
            ->discoverResources(in: base_path('modules/Projects/Filament/Resources'), for: 'Modules\\Projects\\Filament\\Resources')
            ->discoverPages(in: base_path('modules/Projects/Filament/Pages'), for: 'Modules\\Projects\\Filament\\Pages')

            // Discover OdooIntegration module resources, pages, and widgets
            ->discoverResources(in: base_path('modules/OdooIntegration/Filament/Resources'), for: 'Modules\\OdooIntegration\\Filament\\Resources')
            ->discoverPages(in: base_path('modules/OdooIntegration/Filament/Pages'), for: 'Modules\\OdooIntegration\\Filament\\Pages')
            ->discoverWidgets(in: base_path('modules/OdooIntegration/Filament/Widgets'), for: 'Modules\\OdooIntegration\\Filament\\Widgets')

            // Custom routes for prescription PDF printing
            ->routes(function () {
                \Illuminate\Support\Facades\Route::get('/prescriptions/{prescription}/print', function (\Modules\Prescriptions\Models\Prescription $prescription) {
                    return app(\Modules\Prescriptions\Services\PrescriptionPdfService::class)->stream($prescription);
                })->name('prescriptions.print');

                \Illuminate\Support\Facades\Route::get('/prescriptions/{prescription}/download', function (\Modules\Prescriptions\Models\Prescription $prescription) {
                    return app(\Modules\Prescriptions\Services\PrescriptionPdfService::class)->download($prescription);
                })->name('prescriptions.download');
            })

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

            // User limit warning banner
            ->renderHook(
                PanelsRenderHook::BODY_START,
                function (): string {
                    $tenant = current_tenant();
                    if (!$tenant) {
                        return '';
                    }

                    $limit = $tenant->getEffectiveLimit('users');
                    if ($limit === null) {
                        return '';
                    }

                    $currentUsers = \Illuminate\Support\Facades\DB::connection('tenant')
                        ->table('users')
                        ->whereNull('deleted_at')
                        ->count();

                    if ($currentUsers <= $limit) {
                        return '';
                    }

                    // Over limit - show banner
                    $daysRemaining = 14;
                    $isExpired = false;

                    if ($tenant->users_overage_at) {
                        $daysRemaining = $tenant->getUserOverageGraceDaysRemaining();
                        $isExpired = $tenant->isUserOverageGraceExpired();
                    } else {
                        $tenant->update([
                            'users_overage_at' => now(),
                            'users_overage_notified' => false,
                        ]);
                    }

                    $bgColor = $isExpired ? '#dc2626' : '#f59e0b'; // red-600 or amber-500
                    $message = $isExpired
                        ? __('auth::limits.banner.expired', ['current' => $currentUsers, 'limit' => $limit])
                        : __('auth::limits.banner.warning', ['current' => $currentUsers, 'limit' => $limit, 'days' => max(0, $daysRemaining ?? 14)]);
                    $actionText = __('auth::limits.banner.action');
                    $actionUrl = 'https://xforcehr.com/admin';

                    return <<<HTML
<div style="width: 100%; background-color: {$bgColor}; color: white; padding: 0.5rem 1rem; text-align: center; font-size: 0.875rem; font-weight: 500; z-index: 50;">
    <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; flex-wrap: wrap;">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
        </svg>
        <span>{$message}</span>
        <a href="{$actionUrl}" target="_blank" rel="noopener noreferrer" style="margin-left: 0.5rem; display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.75rem; background-color: rgba(255,255,255,0.2); border-radius: 0.375rem; color: white; font-size: 0.75rem; font-weight: 600; text-decoration: none;">
            {$actionText}
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1rem; height: 1rem;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
            </svg>
        </a>
    </div>
</div>
HTML;
                }
            )

            // Custom sidebar theme styles
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): string => '<link rel="stylesheet" href="' . asset('css/filament/admin/theme.css') . '">'
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
                \App\Http\Middleware\SetLocale::class,
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
