<?php

namespace XLinic\Framework\Core\Filament\Panels;

use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use XLinic\Framework\Core\Tenancy\TenantMiddleware;
use XLinic\Framework\Core\Quota\QuotaMiddleware;

/**
 * Patient Portal Panel Configuration
 *
 * Patient-facing portal for XLinic Framework. Provides patients with
 * access to their medical records, appointment scheduling, billing
 * information, and communication tools with healthcare providers.
 *
 * @package XLinic\Framework\Core\Filament\Panels
 */
class PortalPanel extends PanelProvider
{
    /**
     * Panel identifier
     */
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('portal')
            ->path('/portal')
            ->login()
            ->registration()
            ->passwordReset()
            ->emailVerification()
            ->profile()
            ->colors($this->getColors())
            ->font('Inter')
            ->favicon(asset('favicon.ico'))
            ->brandName('XLinic Patient Portal')
            ->brandLogo(asset('images/portal-logo.svg'))
            ->brandLogoHeight('2rem')
            ->darkMode()
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups($this->getNavigationGroups())
            ->discoverResources(
                in: app_path('Filament/Portal/Resources'),
                for: 'App\\Filament\\Portal\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/Portal/Pages'),
                for: 'App\\Filament\\Portal\\Pages'
            )
            ->pages($this->getPages())
            ->discoverWidgets(
                in: app_path('Filament/Portal/Widgets'),
                for: 'App\\Filament\\Portal\\Widgets'
            )
            ->widgets($this->getWidgets())
            ->middleware($this->getMiddleware())
            ->authMiddleware($this->getAuthMiddleware())
            ->plugins($this->getPlugins())
            ->spa()
            ->maxContentWidth('full')
            ->topNavigation(false)
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s') // Less frequent for patient portal
            ->breadcrumbs(true)
            ->unsavedChangesAlerts()
            ->renderHook('panels::body.start', fn(): string => $this->renderPatientBanner())
            ->renderHook('panels::sidebar.start', fn(): string => $this->renderPatientInfo())
            ->viteTheme('resources/css/portal-panel.css')
            ->domain(config('app.portal_domain'))
            ->homeUrl('/portal/dashboard');
    }

    /**
     * Get panel colors
     */
    protected function getColors(): array
    {
        return [
            'danger' => Color::Rose,
            'gray' => Color::Gray,
            'info' => Color::Sky,
            'primary' => Color::Blue,
            'success' => Color::Green,
            'warning' => Color::Orange,
        ];
    }

    /**
     * Get navigation groups
     */
    protected function getNavigationGroups(): array
    {
        return [
            NavigationGroup::make('Dashboard')
                ->icon('heroicon-o-home')
                ->collapsed(false),

            NavigationGroup::make('My Health')
                ->icon('heroicon-o-heart')
                ->collapsed(false),

            NavigationGroup::make('Appointments')
                ->icon('heroicon-o-calendar-days')
                ->collapsed(false),

            NavigationGroup::make('Medical Records')
                ->icon('heroicon-o-document-text')
                ->collapsed(true),

            NavigationGroup::make('Test Results')
                ->icon('heroicon-o-beaker')
                ->collapsed(true),

            NavigationGroup::make('Medications')
                ->icon('heroicon-o-building-storefront')
                ->collapsed(true),

            NavigationGroup::make('Billing & Insurance')
                ->icon('heroicon-o-credit-card')
                ->collapsed(true),

            NavigationGroup::make('Communications')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->collapsed(true),

            NavigationGroup::make('Health Tools')
                ->icon('heroicon-o-calculator')
                ->collapsed(true),

            NavigationGroup::make('My Account')
                ->icon('heroicon-o-user-circle')
                ->collapsed(true),
        ];
    }

    /**
     * Get pages
     */
    protected function getPages(): array
    {
        return [
            \XLinic\Framework\Core\Filament\Pages\Portal\PatientDashboard::class,
            \XLinic\Framework\Core\Filament\Pages\Portal\MyProfile::class,
            \XLinic\Framework\Core\Filament\Pages\Portal\MyAppointments::class,
            \XLinic\Framework\Core\Filament\Pages\Portal\MedicalRecords::class,
            \XLinic\Framework\Core\Filament\Pages\Portal\TestResults::class,
            \XLinic\Framework\Core\Filament\Pages\Portal\Medications::class,
            \XLinic\Framework\Core\Filament\Pages\Portal\BillingStatement::class,
            \XLinic\Framework\Core\Filament\Pages\Portal\InsuranceInfo::class,
            \XLinic\Framework\Core\Filament\Pages\Portal\Messages::class,
            \XLinic\Framework\Core\Filament\Pages\Portal\HealthTrackers::class,
            \XLinic\Framework\Core\Filament\Pages\Portal\FamilyAccess::class,
            \XLinic\Framework\Core\Filament\Pages\Portal\PreventiveCare::class,
        ];
    }

    /**
     * Get widgets
     */
    protected function getWidgets(): array
    {
        return [
            Widgets\AccountWidget::class,
            \XLinic\Framework\Core\Filament\Widgets\Portal\UpcomingAppointments::class,
            \XLinic\Framework\Core\Filament\Widgets\Portal\RecentTestResults::class,
            \XLinic\Framework\Core\Filament\Widgets\Portal\MedicationReminders::class,
            \XLinic\Framework\Core\Filament\Widgets\Portal\HealthSummary::class,
            \XLinic\Framework\Core\Filament\Widgets\Portal\UnreadMessages::class,
            \XLinic\Framework\Core\Filament\Widgets\Portal\PreventiveCareReminders::class,
            \XLinic\Framework\Core\Filament\Widgets\Portal\HealthTips::class,
        ];
    }

    /**
     * Get middleware
     */
    protected function getMiddleware(): array
    {
        return [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            AuthenticateSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            DisableBladeIconComponents::class,
            DispatchServingFilamentEvent::class,
            TenantMiddleware::subdomain(['excluded' => ['www', 'api', 'admin', 'superadmin']]),
            QuotaMiddleware::api(60), // 60 requests per minute for patients
            'web',
        ];
    }

    /**
     * Get authentication middleware
     */
    protected function getAuthMiddleware(): array
    {
        return [
            Authenticate::class,
            'role:patient|guardian', // Only patients and guardians can access
        ];
    }

    /**
     * Get plugins
     */
    protected function getPlugins(): array
    {
        $plugins = [];

        // Patient-specific plugins
        if (class_exists(\Jeffgreco13\FilamentBreezy\BreezyCore::class)) {
            $plugins[] = \Jeffgreco13\FilamentBreezy\BreezyCore::make()
                ->myProfile(
                    shouldRegisterUserMenu: true,
                    shouldRegisterNavigation: true,
                    navigationGroup: 'My Account',
                    hasAvatars: true,
                    slug: 'my-profile'
                )
                ->enableTwoFactorAuthentication()
                ->enableSanctumTokens(false); // Disable API tokens for patients
        }

        // Health tracking plugins
        if (class_exists(\App\Filament\Plugins\HealthTrackingPlugin::class)) {
            $plugins[] = \App\Filament\Plugins\HealthTrackingPlugin::make();
        }

        // Communication plugins
        if (class_exists(\App\Filament\Plugins\PatientMessagingPlugin::class)) {
            $plugins[] = \App\Filament\Plugins\PatientMessagingPlugin::make();
        }

        // Appointment scheduling
        if (class_exists(\App\Filament\Plugins\PatientAppointmentPlugin::class)) {
            $plugins[] = \App\Filament\Plugins\PatientAppointmentPlugin::make();
        }

        return $plugins;
    }

    /**
     * Render patient banner
     */
    protected function renderPatientBanner(): string
    {
        $user = auth()->user();

        if (!$user || !$user->hasRole('patient')) {
            return '';
        }

        // Check for important alerts
        $alerts = $this->getPatientAlerts($user);

        if (empty($alerts)) {
            return '';
        }

        return view('filament.components.patient-banner', [
            'user' => $user,
            'alerts' => $alerts,
        ])->render();
    }

    /**
     * Render patient info sidebar
     */
    protected function renderPatientInfo(): string
    {
        $user = auth()->user();

        if (!$user || !$user->hasRole('patient')) {
            return '';
        }

        return view('filament.components.patient-info', [
            'user' => $user,
            'patient' => $user->patient,
        ])->render();
    }

    /**
     * Get patient alerts
     */
    protected function getPatientAlerts(object $user): array
    {
        $alerts = [];

        // Check for upcoming appointments
        $upcomingAppointments = \App\Models\Appointment::where('patient_id', $user->patient?->id)
            ->where('scheduled_at', '>=', now())
            ->where('scheduled_at', '<=', now()->addDays(7))
            ->count();

        if ($upcomingAppointments > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => "You have {$upcomingAppointments} upcoming appointment(s) in the next 7 days.",
                'action' => 'View Appointments',
                'url' => '/portal/appointments',
            ];
        }

        // Check for new test results
        $newResults = \App\Models\TestResult::where('patient_id', $user->patient?->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNull('viewed_at')
            ->count();

        if ($newResults > 0) {
            $alerts[] = [
                'type' => 'success',
                'message' => "You have {$newResults} new test result(s) available.",
                'action' => 'View Results',
                'url' => '/portal/test-results',
            ];
        }

        // Check for overdue bills
        $overdueBills = \App\Models\Bill::where('patient_id', $user->patient?->id)
            ->where('due_date', '<', now())
            ->where('status', 'pending')
            ->count();

        if ($overdueBills > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "You have {$overdueBills} overdue bill(s).",
                'action' => 'View Bills',
                'url' => '/portal/billing',
            ];
        }

        // Check for medication refills
        $refillsNeeded = \App\Models\Prescription::where('patient_id', $user->patient?->id)
            ->where('refills_remaining', '>', 0)
            ->where('expires_at', '>=', now())
            ->whereHas('lastFill', function ($query) {
                $query->where('dispensed_at', '<=', now()->subDays(25)); // 25+ days ago
            })
            ->count();

        if ($refillsNeeded > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => "You may need to refill {$refillsNeeded} medication(s) soon.",
                'action' => 'View Medications',
                'url' => '/portal/medications',
            ];
        }

        return $alerts;
    }

    /**
     * Boot panel services
     */
    public function boot(): void
    {
        parent::boot();

        // Register patient-specific navigation
        $this->registerPatientNavigation();

        // Setup patient notifications
        $this->setupPatientNotifications();

        // Register patient search providers
        $this->registerPatientSearchProviders();

        // Setup health reminders
        $this->setupHealthReminders();
    }

    /**
     * Register patient-specific navigation
     */
    protected function registerPatientNavigation(): void
    {
        if (auth()->check()) {
            $user = auth()->user();

            if ($user->hasRole('patient')) {
                \Filament\Facades\Filament::serving(function () use ($user) {
                    \Filament\Navigation\NavigationBuilder::make()
                        ->items([
                            \Filament\Navigation\NavigationItem::make('Schedule Appointment')
                                ->url('/portal/appointments/schedule')
                                ->icon('heroicon-o-plus-circle')
                                ->group('Quick Actions')
                                ->sort(1),

                            \Filament\Navigation\NavigationItem::make('Message Provider')
                                ->url('/portal/messages/compose')
                                ->icon('heroicon-o-paper-airplane')
                                ->group('Quick Actions')
                                ->sort(2),

                            \Filament\Navigation\NavigationItem::make('Pay Bills')
                                ->url('/portal/billing/pay')
                                ->icon('heroicon-o-credit-card')
                                ->group('Quick Actions')
                                ->sort(3)
                                ->visible(fn() => $this->hasOutstandingBills($user)),
                        ]);
                });
            }
        }
    }

    /**
     * Setup patient notifications
     */
    protected function setupPatientNotifications(): void
    {
        // Register custom notification channels for patients
        \Illuminate\Support\Facades\Notification::extend('patient_sms', function ($app) {
            return new \App\Notifications\Channels\PatientSmsChannel();
        });

        \Illuminate\Support\Facades\Notification::extend('patient_email', function ($app) {
            return new \App\Notifications\Channels\PatientEmailChannel();
        });
    }

    /**
     * Register patient search providers
     */
    protected function registerPatientSearchProviders(): void
    {
        \Filament\Facades\Filament::serving(function () {
            \Filament\GlobalSearch\GlobalSearchManager::registerProvider(
                \App\Filament\Portal\GlobalSearch\AppointmentSearchProvider::class
            );

            \Filament\GlobalSearch\GlobalSearchManager::registerProvider(
                \App\Filament\Portal\GlobalSearch\MedicalRecordSearchProvider::class
            );

            \Filament\GlobalSearch\GlobalSearchManager::registerProvider(
                \App\Filament\Portal\GlobalSearch\TestResultSearchProvider::class
            );

            \Filament\GlobalSearch\GlobalSearchManager::registerProvider(
                \App\Filament\Portal\GlobalSearch\MedicationSearchProvider::class
            );
        });
    }

    /**
     * Setup health reminders
     */
    protected function setupHealthReminders(): void
    {
        // Setup automated health reminders
        \Illuminate\Console\Scheduling\Schedule::macro('healthReminders', function () {
            $this->command('portal:send-medication-reminders')->daily();
            $this->command('portal:send-appointment-reminders')->hourly();
            $this->command('portal:send-preventive-care-reminders')->weekly();
        });
    }

    /**
     * Check if patient has outstanding bills
     */
    protected function hasOutstandingBills(object $user): bool
    {
        return \App\Models\Bill::where('patient_id', $user->patient?->id)
            ->where('status', 'pending')
            ->exists();
    }

    /**
     * Get panel configuration
     */
    public static function getConfig(): array
    {
        return [
            'name' => 'XLinic Patient Portal',
            'description' => 'Patient Access to Healthcare Information',
            'version' => '1.0.0',
            'features' => [
                'appointment_scheduling' => true,
                'medical_records_access' => true,
                'test_results_viewing' => true,
                'medication_management' => true,
                'billing_statements' => true,
                'insurance_information' => true,
                'provider_messaging' => true,
                'health_tracking' => true,
                'family_access' => true,
                'preventive_care_reminders' => true,
                'mobile_responsive' => true,
                'offline_access' => false,
                'telemedicine_integration' => true,
                'prescription_refills' => true,
                'document_upload' => true,
            ],
            'security' => [
                'require_email_verification' => true,
                'session_timeout' => 30, // minutes
                'max_failed_attempts' => 5,
                'lockout_duration' => 900, // seconds (15 minutes)
                'password_reset_timeout' => 60, // minutes
                'two_factor_optional' => true,
            ],
            'privacy' => [
                'hipaa_compliant' => true,
                'audit_all_access' => true,
                'data_encryption' => true,
                'secure_messaging' => true,
                'privacy_controls' => true,
            ],
        ];
    }

    /**
     * Get portal statistics
     */
    public static function getStatistics(): array
    {
        return [
            'total_patients' => \App\Models\Patient::count(),
            'active_portal_users' => \App\Models\User::whereHas('roles', function ($q) {
                $q->where('name', 'patient');
            })->whereNotNull('last_login_at')->count(),
            'portal_adoption_rate' => self::calculatePortalAdoptionRate(),
            'monthly_logins' => self::getMonthlyLogins(),
            'most_used_features' => self::getMostUsedFeatures(),
            'average_session_duration' => self::getAverageSessionDuration(),
        ];
    }

    /**
     * Calculate portal adoption rate
     */
    protected static function calculatePortalAdoptionRate(): float
    {
        $totalPatients = \App\Models\Patient::count();
        $portalUsers = \App\Models\User::whereHas('roles', function ($q) {
            $q->where('name', 'patient');
        })->count();

        return $totalPatients > 0 ? round(($portalUsers / $totalPatients) * 100, 2) : 0;
    }

    /**
     * Get monthly logins
     */
    protected static function getMonthlyLogins(): int
    {
        return \App\Models\User::whereHas('roles', function ($q) {
            $q->where('name', 'patient');
        })->where('last_login_at', '>=', now()->startOfMonth())->count();
    }

    /**
     * Get most used features
     */
    protected static function getMostUsedFeatures(): array
    {
        // This would typically come from analytics data
        return [
            'appointments' => 85,
            'test_results' => 72,
            'medications' => 68,
            'billing' => 45,
            'messages' => 38,
        ];
    }

    /**
     * Get average session duration
     */
    protected static function getAverageSessionDuration(): string
    {
        // This would be calculated from session data
        return '12 minutes';
    }

    /**
     * Check if panel is available
     */
    public static function isAvailable(): bool
    {
        return config('filament.portal.enabled', true) && !app()->isDownForMaintenance();
    }

    /**
     * Get required permissions
     */
    public static function getRequiredPermissions(): array
    {
        return [
            'access_patient_portal',
            'view_own_records',
        ];
    }

    /**
     * Get allowed roles
     */
    public static function getAllowedRoles(): array
    {
        return [
            'patient',
            'guardian',
        ];
    }
}