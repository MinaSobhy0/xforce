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
use Filament\Support\Enums\MaxWidth;
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

            // Widen the main content area from Filament's default ~1280px
            // to ~1536px so wide tables get more breathing room on
            // 1920px+ displays without pushing forms into uncomfortable
            // line lengths.
            ->maxContentWidth(MaxWidth::ScreenTwoExtraLarge)

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

            // Discover KnowledgeBase module pages
            ->discoverPages(in: base_path('modules/KnowledgeBase/Filament/Pages'), for: 'Modules\\KnowledgeBase\\Filament\\Pages')

            // Discover Website module resources and pages
            ->discoverResources(in: base_path('modules/Website/Filament/Resources'), for: 'Modules\\Website\\Filament\\Resources')
            ->discoverPages(in: base_path('modules/Website/Filament/Pages'), for: 'Modules\\Website\\Filament\\Pages')

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

            // Help Button in the topbar (before user menu)
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): View => view('knowledgebase::hooks.help-button')
            )

            // Screen Guide overlay (injected at body end)
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): View => view('knowledgebase::hooks.screen-guide')
            )

            // Subscription banner (past_due / grace / expired) — shown on top
            // of the user-limit one so a tenant who is both unpaid AND over-
            // count sees both, with the more severe one (subscription) first.
            ->renderHook(
                PanelsRenderHook::BODY_START,
                function (): string {
                    $tenant = current_tenant();
                    if (! $tenant) {
                        return '';
                    }

                    // Resolve status via the same logic TenantSubscription uses.
                    $subscription = $tenant->subscription ?? null;
                    $expiresAt = $tenant->subscription_expires_at;
                    $status = null; // 'past_due' | 'grace' | 'expired'
                    $expiredDaysAgo = null;
                    $graceEndsAt = null;

                    if ($subscription && method_exists($subscription, 'isInGracePeriod') && $subscription->isInGracePeriod()) {
                        $status = 'grace';
                        $graceEndsAt = $subscription->grace_period_ends_at ?? null;
                    } elseif ($subscription && method_exists($subscription, 'isExpired') && $subscription->isExpired()) {
                        $status = 'expired';
                        $expiredDaysAgo = $expiresAt ? (int) now()->diffInDays($expiresAt, false) * -1 : null;
                    } elseif ($expiresAt && $expiresAt->isPast()) {
                        // Legacy path — no subscription record but expiry is in the past.
                        $status = 'past_due';
                        $expiredDaysAgo = (int) now()->diffInDays($expiresAt, false) * -1;
                    }

                    if (! $status) {
                        return '';
                    }

                    $autoSuspendAfter = (int) \App\Models\PlatformSetting::get('auto_suspend_after', 7);
                    $daysUntilSuspend = max(0, $autoSuspendAfter - max(0, $expiredDaysAgo ?? 0));

                    $bgColor = $status === 'grace' ? '#f59e0b' : '#dc2626';
                    $message = match ($status) {
                        'grace' => __('auth::limits.banner.subscription_grace', [
                            'date' => optional($graceEndsAt)->format('M d, Y') ?? '-',
                        ]),
                        'expired', 'past_due' => __('auth::limits.banner.subscription_overdue', [
                            'days_ago' => max(0, $expiredDaysAgo ?? 0),
                            'days_until_suspend' => $daysUntilSuspend,
                        ]),
                    };
                    $actionText = __('auth::limits.banner.contact_support');
                    $actionUrl = 'mailto:support@xforcehr.com';

                    return <<<HTML
<div style="width: 100%; background-color: {$bgColor}; color: white; padding: 0.5rem 1rem; text-align: center; font-size: 0.875rem; font-weight: 500; z-index: 50;">
    <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; flex-wrap: wrap;">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
        </svg>
        <span>{$message}</span>
        <a href="{$actionUrl}" style="margin-left: 0.5rem; display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.75rem; background-color: rgba(255,255,255,0.2); border-radius: 0.375rem; color: white; font-size: 0.75rem; font-weight: 600; text-decoration: none;">
            {$actionText}
        </a>
    </div>
</div>
HTML;
                }
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

                    // Over limit - show banner.
                    //
                    // First observation: stamp users_overage_at so the
                    // 14-day grace counter has a start date. The columns
                    // users_overage_at / users_overage_notified are in
                    // Tenant::$guarded (HIGH-impact billing fields), so
                    // ->update([...]) is silently dropped — that's why
                    // the counter was previously stuck at the default 14
                    // and never decremented. Assign directly + save() to
                    // bypass mass-assignment guard (this code path is the
                    // trusted system-internal setter the guard is meant
                    // to protect against, not us).
                    if (! $tenant->users_overage_at) {
                        $tenant->users_overage_at = now();
                        $tenant->users_overage_notified = false;
                        $tenant->save();
                    }

                    $daysRemaining = $tenant->getUserOverageGraceDaysRemaining() ?? 14;
                    $isExpired = $tenant->isUserOverageGraceExpired();

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
                // H-10: intra-tenant branch isolation (log-only until
                // config('security.branch.enforce') is enabled).
                \App\Http\Middleware\EnforceBranchAccess::class,
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
