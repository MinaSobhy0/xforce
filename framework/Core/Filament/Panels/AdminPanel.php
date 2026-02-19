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
 * Admin Panel Configuration
 *
 * Main administrative panel for XLinic Framework. Provides comprehensive
 * management interface for healthcare practice operations, patient
 * records, appointments, and system administration.
 *
 * @package XLinic\Framework\Core\Filament\Panels
 */
class AdminPanel extends PanelProvider
{
    /**
     * Panel identifier
     */
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('/admin')
            ->login()
            ->profile()
            ->colors($this->getColors())
            ->font('Inter')
            ->favicon(asset('favicon.ico'))
            ->brandName('XLinic Admin')
            ->brandLogo(asset('images/logo.svg'))
            ->brandLogoHeight('2rem')
            ->darkMode()
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups($this->getNavigationGroups())
            ->discoverResources(
                in: app_path('Filament/Admin/Resources'),
                for: 'App\\Filament\\Admin\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/Admin/Pages'),
                for: 'App\\Filament\\Admin\\Pages'
            )
            ->pages($this->getPages())
            ->discoverWidgets(
                in: app_path('Filament/Admin/Widgets'),
                for: 'App\\Filament\\Admin\\Widgets'
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
            ->databaseNotificationsPolling('30s')
            ->emailVerification()
            ->passwordReset()
            ->registration(false) // Disabled by default for security
            ->tenantMenuPlacement('header')
            ->breadcrumbs(true)
            ->unsavedChangesAlerts()
            ->renderHook('panels::body.start', fn(): string => $this->renderTenantBanner())
            ->viteTheme('resources/css/admin-panel.css');
    }

    /**
     * Get panel colors
     */
    protected function getColors(): array
    {
        return [
            'danger' => Color::Rose,
            'gray' => Color::Gray,
            'info' => Color::Blue,
            'primary' => Color::Emerald,
            'success' => Color::Emerald,
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

            NavigationGroup::make('Patients')
                ->icon('heroicon-o-users')
                ->collapsed(false),

            NavigationGroup::make('Appointments')
                ->icon('heroicon-o-calendar-days')
                ->collapsed(false),

            NavigationGroup::make('Medical Records')
                ->icon('heroicon-o-document-text')
                ->collapsed(true),

            NavigationGroup::make('Billing & Insurance')
                ->icon('heroicon-o-credit-card')
                ->collapsed(true),

            NavigationGroup::make('Inventory')
                ->icon('heroicon-o-cube')
                ->collapsed(true),

            NavigationGroup::make('Staff Management')
                ->icon('heroicon-o-user-group')
                ->collapsed(true),

            NavigationGroup::make('Reports')
                ->icon('heroicon-o-chart-bar')
                ->collapsed(true),

            NavigationGroup::make('Settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->collapsed(true),

            NavigationGroup::make('System')
                ->icon('heroicon-o-server')
                ->collapsed(true),
        ];
    }

    /**
     * Get pages
     */
    protected function getPages(): array
    {
        return [
            \XLinic\Framework\Core\Filament\Pages\Dashboard::class,
            \XLinic\Framework\Core\Filament\Pages\ProfilePage::class,
            \XLinic\Framework\Core\Filament\Pages\SettingsPage::class,
            \XLinic\Framework\Core\Filament\Pages\SystemHealth::class,
            \XLinic\Framework\Core\Filament\Pages\ActivityLog::class,
        ];
    }

    /**
     * Get widgets
     */
    protected function getWidgets(): array
    {
        return [
            Widgets\AccountWidget::class,
            \XLinic\Framework\Core\Filament\Widgets\StatsOverview::class,
            \XLinic\Framework\Core\Filament\Widgets\AppointmentsChart::class,
            \XLinic\Framework\Core\Filament\Widgets\RecentPatients::class,
            \XLinic\Framework\Core\Filament\Widgets\SystemStatus::class,
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
            TenantMiddleware::subdomain(['excluded' => ['www', 'api', 'app']]),
            QuotaMiddleware::api(120), // 120 requests per minute
        ];
    }

    /**
     * Get authentication middleware
     */
    protected function getAuthMiddleware(): array
    {
        return [
            Authenticate::class,
            'verified', // Require email verification
            'role:admin|doctor|nurse|staff', // Require appropriate role
        ];
    }

    /**
     * Get plugins
     */
    protected function getPlugins(): array
    {
        $plugins = [];

        // Add core plugins
        if (class_exists(\Filament\SpatieLaravelSettingsPlugin\SpatieLaravelSettingsPlugin::class)) {
            $plugins[] = \Filament\SpatieLaravelSettingsPlugin\SpatieLaravelSettingsPlugin::make()
                ->setIcon('heroicon-o-cog-6-tooth')
                ->setNavigationGroup('Settings');
        }

        if (class_exists(\BezhanSalleh\FilamentShield\FilamentShieldPlugin::class)) {
            $plugins[] = \BezhanSalleh\FilamentShield\FilamentShieldPlugin::make()
                ->gridColumns(2)
                ->sectionColumnSpan(1);
        }

        if (class_exists(\Jeffgreco13\FilamentBreezy\BreezyCore::class)) {
            $plugins[] = \Jeffgreco13\FilamentBreezy\BreezyCore::make()
                ->myProfile(
                    shouldRegisterUserMenu: true,
                    shouldRegisterNavigation: false,
                    navigationGroup: 'Settings',
                    hasAvatars: true,
                    slug: 'profile'
                )
                ->enableTwoFactorAuthentication()
                ->enableSanctumTokens();
        }

        // Add medical plugins if available
        if (class_exists(\App\Filament\Plugins\MedicalRecordsPlugin::class)) {
            $plugins[] = \App\Filament\Plugins\MedicalRecordsPlugin::make();
        }

        if (class_exists(\App\Filament\Plugins\AppointmentPlugin::class)) {
            $plugins[] = \App\Filament\Plugins\AppointmentPlugin::make();
        }

        return $plugins;
    }

    /**
     * Render tenant banner
     */
    protected function renderTenantBanner(): string
    {
        $tenant = app(\XLinic\Framework\Core\Tenancy\TenantManager::class)->getCurrentTenant();

        if (!$tenant) {
            return '';
        }

        return view('filament.components.tenant-banner', [
            'tenant' => $tenant,
        ])->render();
    }

    /**
     * Boot panel services
     */
    public function boot(): void
    {
        parent::boot();

        // Register custom navigation items
        $this->registerCustomNavigation();

        // Register global search providers
        $this->registerGlobalSearchProviders();

        // Register notification channels
        $this->registerNotificationChannels();

        // Setup real-time features
        $this->setupRealTimeFeatures();
    }

    /**
     * Register custom navigation items
     */
    protected function registerCustomNavigation(): void
    {
        // Add dynamic navigation items based on user permissions and modules
        if (auth()->check()) {
            $user = auth()->user();

            // Add quick actions navigation
            if ($user->can('create_appointments')) {
                \Filament\Facades\Filament::serving(function () {
                    \Filament\Navigation\NavigationBuilder::make()
                        ->items([
                            \Filament\Navigation\NavigationItem::make('Quick Appointment')
                                ->url('/admin/appointments/create')
                                ->icon('heroicon-o-plus-circle')
                                ->group('Quick Actions')
                                ->sort(1),
                        ]);
                });
            }
        }
    }

    /**
     * Register global search providers
     */
    protected function registerGlobalSearchProviders(): void
    {
        \Filament\Facades\Filament::serving(function () {
            \Filament\GlobalSearch\GlobalSearchManager::registerProvider(
                \App\Filament\GlobalSearch\PatientSearchProvider::class
            );

            \Filament\GlobalSearch\GlobalSearchManager::registerProvider(
                \App\Filament\GlobalSearch\AppointmentSearchProvider::class
            );

            \Filament\GlobalSearch\GlobalSearchManager::registerProvider(
                \App\Filament\GlobalSearch\MedicalRecordSearchProvider::class
            );
        });
    }

    /**
     * Register notification channels
     */
    protected function registerNotificationChannels(): void
    {
        // Register custom notification channels for medical events
        if (class_exists(\App\Notifications\Channels\SmsChannel::class)) {
            \Illuminate\Support\Facades\Notification::extend('sms', function ($app) {
                return new \App\Notifications\Channels\SmsChannel();
            });
        }

        if (class_exists(\App\Notifications\Channels\WebhookChannel::class)) {
            \Illuminate\Support\Facades\Notification::extend('webhook', function ($app) {
                return new \App\Notifications\Channels\WebhookChannel();
            });
        }
    }

    /**
     * Setup real-time features
     */
    protected function setupRealTimeFeatures(): void
    {
        // Setup real-time notifications for appointments
        \Filament\Facades\Filament::serving(function () {
            // Listen for appointment changes
            \Livewire\Livewire::listen('echo:appointments,AppointmentUpdated', function () {
                $this->dispatch('appointmentUpdated');
            });

            // Listen for emergency alerts
            \Livewire\Livewire::listen('echo:emergency,EmergencyAlert', function ($data) {
                $this->dispatch('emergencyAlert', $data);
            });

            // Listen for system notifications
            \Livewire\Livewire::listen('echo:system,SystemNotification', function ($data) {
                $this->dispatch('systemNotification', $data);
            });
        });
    }

    /**
     * Get panel configuration
     */
    public static function getConfig(): array
    {
        return [
            'name' => 'XLinic Admin',
            'description' => 'Healthcare Practice Management System',
            'version' => '1.0.0',
            'features' => [
                'patient_management' => true,
                'appointment_scheduling' => true,
                'medical_records' => true,
                'billing_insurance' => true,
                'inventory_management' => true,
                'staff_management' => true,
                'reporting' => true,
                'multi_tenant' => true,
                'role_permissions' => true,
                'audit_logging' => true,
                'api_access' => true,
                'mobile_responsive' => true,
                'real_time_updates' => true,
                'backup_restore' => true,
                'compliance_tracking' => true,
            ],
            'supported_locales' => ['en', 'es', 'fr'],
            'default_timezone' => 'UTC',
            'max_file_upload_size' => '50MB',
            'session_timeout' => 120, // minutes
            'password_policy' => [
                'min_length' => 8,
                'require_uppercase' => true,
                'require_lowercase' => true,
                'require_numbers' => true,
                'require_symbols' => true,
            ],
        ];
    }

    /**
     * Get panel statistics
     */
    public static function getStatistics(): array
    {
        return [
            'total_users' => \App\Models\User::count(),
            'active_sessions' => \Illuminate\Support\Facades\DB::table('sessions')->count(),
            'total_patients' => \App\Models\Patient::count() ?? 0,
            'todays_appointments' => \App\Models\Appointment::whereDate('scheduled_at', today())->count() ?? 0,
            'pending_tasks' => \App\Models\Task::where('status', 'pending')->count() ?? 0,
            'system_health' => 'healthy', // This would be calculated based on various metrics
        ];
    }

    /**
     * Check if panel is available
     */
    public static function isAvailable(): bool
    {
        // Check if admin panel should be available
        return config('filament.admin.enabled', true) && !app()->isDownForMaintenance();
    }

    /**
     * Get panel permissions
     */
    public static function getRequiredPermissions(): array
    {
        return [
            'access_admin_panel',
            'view_dashboard',
        ];
    }

    /**
     * Get panel roles
     */
    public static function getAllowedRoles(): array
    {
        return [
            'super_admin',
            'admin',
            'doctor',
            'nurse',
            'staff',
            'receptionist',
        ];
    }
}