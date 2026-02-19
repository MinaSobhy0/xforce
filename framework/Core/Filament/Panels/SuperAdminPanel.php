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
use XLinic\Framework\Core\Quota\QuotaMiddleware;

/**
 * Super Admin Panel Configuration
 *
 * Platform administration panel for XLinic Framework. Provides system-wide
 * management capabilities including tenant management, system configuration,
 * monitoring, and platform-level operations.
 *
 * @package XLinic\Framework\Core\Filament\Panels
 */
class SuperAdminPanel extends PanelProvider
{
    /**
     * Panel identifier
     */
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('superadmin')
            ->path('/superadmin')
            ->login()
            ->profile()
            ->colors($this->getColors())
            ->font('Inter')
            ->favicon(asset('favicon.ico'))
            ->brandName('XLinic Platform')
            ->brandLogo(asset('images/platform-logo.svg'))
            ->brandLogoHeight('2rem')
            ->darkMode()
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups($this->getNavigationGroups())
            ->discoverResources(
                in: app_path('Filament/SuperAdmin/Resources'),
                for: 'App\\Filament\\SuperAdmin\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/SuperAdmin/Pages'),
                for: 'App\\Filament\\SuperAdmin\\Pages'
            )
            ->pages($this->getPages())
            ->discoverWidgets(
                in: app_path('Filament/SuperAdmin/Widgets'),
                for: 'App\\Filament\\SuperAdmin\\Widgets'
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
            ->registration(false) // Disabled for security
            ->breadcrumbs(true)
            ->unsavedChangesAlerts()
            ->renderHook('panels::body.start', fn(): string => $this->renderPlatformBanner())
            ->viteTheme('resources/css/superadmin-panel.css')
            ->domain(config('app.superadmin_domain', 'platform.xlinic.com'));
    }

    /**
     * Get panel colors
     */
    protected function getColors(): array
    {
        return [
            'danger' => Color::Red,
            'gray' => Color::Slate,
            'info' => Color::Blue,
            'primary' => Color::Indigo,
            'success' => Color::Green,
            'warning' => Color::Amber,
        ];
    }

    /**
     * Get navigation groups
     */
    protected function getNavigationGroups(): array
    {
        return [
            NavigationGroup::make('Platform Overview')
                ->icon('heroicon-o-chart-pie')
                ->collapsed(false),

            NavigationGroup::make('Tenant Management')
                ->icon('heroicon-o-building-office')
                ->collapsed(false),

            NavigationGroup::make('User Management')
                ->icon('heroicon-o-users')
                ->collapsed(false),

            NavigationGroup::make('System Configuration')
                ->icon('heroicon-o-cog-8-tooth')
                ->collapsed(true),

            NavigationGroup::make('Modules & Plugins')
                ->icon('heroicon-o-puzzle-piece')
                ->collapsed(true),

            NavigationGroup::make('Security & Access')
                ->icon('heroicon-o-shield-check')
                ->collapsed(true),

            NavigationGroup::make('Monitoring & Logs')
                ->icon('heroicon-o-eye')
                ->collapsed(true),

            NavigationGroup::make('Billing & Subscriptions')
                ->icon('heroicon-o-credit-card')
                ->collapsed(true),

            NavigationGroup::make('Support & Maintenance')
                ->icon('heroicon-o-wrench-screwdriver')
                ->collapsed(true),

            NavigationGroup::make('Platform Reports')
                ->icon('heroicon-o-document-chart-bar')
                ->collapsed(true),

            NavigationGroup::make('Developer Tools')
                ->icon('heroicon-o-code-bracket')
                ->collapsed(true),
        ];
    }

    /**
     * Get pages
     */
    protected function getPages(): array
    {
        return [
            \XLinic\Framework\Core\Filament\Pages\SuperAdmin\PlatformDashboard::class,
            \XLinic\Framework\Core\Filament\Pages\SuperAdmin\TenantManagement::class,
            \XLinic\Framework\Core\Filament\Pages\SuperAdmin\SystemConfiguration::class,
            \XLinic\Framework\Core\Filament\Pages\SuperAdmin\ModuleManager::class,
            \XLinic\Framework\Core\Filament\Pages\SuperAdmin\SecurityCenter::class,
            \XLinic\Framework\Core\Filament\Pages\SuperAdmin\SystemHealth::class,
            \XLinic\Framework\Core\Filament\Pages\SuperAdmin\ActivityMonitor::class,
            \XLinic\Framework\Core\Filament\Pages\SuperAdmin\BackupManager::class,
            \XLinic\Framework\Core\Filament\Pages\SuperAdmin\DatabaseManager::class,
            \XLinic\Framework\Core\Filament\Pages\SuperAdmin\PerformanceMonitor::class,
            \XLinic\Framework\Core\Filament\Pages\SuperAdmin\ApiManagement::class,
            \XLinic\Framework\Core\Filament\Pages\SuperAdmin\MaintenanceMode::class,
        ];
    }

    /**
     * Get widgets
     */
    protected function getWidgets(): array
    {
        return [
            Widgets\AccountWidget::class,
            \XLinic\Framework\Core\Filament\Widgets\SuperAdmin\PlatformStatsOverview::class,
            \XLinic\Framework\Core\Filament\Widgets\SuperAdmin\TenantGrowthChart::class,
            \XLinic\Framework\Core\Filament\Widgets\SuperAdmin\SystemResourcesChart::class,
            \XLinic\Framework\Core\Filament\Widgets\SuperAdmin\RecentActivity::class,
            \XLinic\Framework\Core\Filament\Widgets\SuperAdmin\SecurityAlerts::class,
            \XLinic\Framework\Core\Filament\Widgets\SuperAdmin\ServerStatus::class,
            \XLinic\Framework\Core\Filament\Widgets\SuperAdmin\BackupStatus::class,
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
            QuotaMiddleware::api(300), // Higher limits for super admin
            'throttle:superadmin',
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
            'role:super_admin', // Only super admins can access
            'auth.session', // Session authentication
            'password.confirm:30', // Require password confirmation every 30 minutes
        ];
    }

    /**
     * Get plugins
     */
    protected function getPlugins(): array
    {
        $plugins = [];

        // Essential platform management plugins
        if (class_exists(\Filament\SpatieLaravelSettingsPlugin\SpatieLaravelSettingsPlugin::class)) {
            $plugins[] = \Filament\SpatieLaravelSettingsPlugin\SpatieLaravelSettingsPlugin::make()
                ->setIcon('heroicon-o-cog-8-tooth')
                ->setNavigationGroup('System Configuration');
        }

        if (class_exists(\BezhanSalleh\FilamentShield\FilamentShieldPlugin::class)) {
            $plugins[] = \BezhanSalleh\FilamentShield\FilamentShieldPlugin::make()
                ->gridColumns(3)
                ->sectionColumnSpan(1)
                ->checkboxListColumns(3);
        }

        if (class_exists(\Jeffgreco13\FilamentBreezy\BreezyCore::class)) {
            $plugins[] = \Jeffgreco13\FilamentBreezy\BreezyCore::make()
                ->myProfile(
                    shouldRegisterUserMenu: true,
                    shouldRegisterNavigation: false,
                    navigationGroup: 'Platform Overview',
                    hasAvatars: true,
                    slug: 'profile'
                )
                ->enableTwoFactorAuthentication(force: true) // Force 2FA for super admins
                ->enableSanctumTokens();
        }

        // Monitoring and logging plugins
        if (class_exists(\Spatie\LaravelActivitylog\ActivitylogServiceProvider::class)) {
            $plugins[] = \XLinic\Framework\Plugins\ActivityLogPlugin::make();
        }

        if (class_exists(\Spatie\Health\HealthServiceProvider::class)) {
            $plugins[] = \XLinic\Framework\Plugins\HealthCheckPlugin::make();
        }

        // Developer tools
        if (app()->environment('local', 'staging')) {
            if (class_exists(\Filament\DebugbarPlugin\DebugbarPlugin::class)) {
                $plugins[] = \Filament\DebugbarPlugin\DebugbarPlugin::make();
            }

            if (class_exists(\Filament\TelescopePlugin\TelescopePlugin::class)) {
                $plugins[] = \Filament\TelescopePlugin\TelescopePlugin::make();
            }
        }

        return $plugins;
    }

    /**
     * Render platform banner
     */
    protected function renderPlatformBanner(): string
    {
        $environment = app()->environment();
        $maintenanceMode = app()->isDownForMaintenance();

        if ($environment !== 'production' || $maintenanceMode) {
            return view('filament.components.platform-banner', [
                'environment' => $environment,
                'maintenanceMode' => $maintenanceMode,
            ])->render();
        }

        return '';
    }

    /**
     * Boot panel services
     */
    public function boot(): void
    {
        parent::boot();

        // Register platform-specific navigation
        $this->registerPlatformNavigation();

        // Setup security monitoring
        $this->setupSecurityMonitoring();

        // Register platform search providers
        $this->registerPlatformSearchProviders();

        // Setup real-time monitoring
        $this->setupRealTimeMonitoring();

        // Register custom commands
        $this->registerCustomCommands();
    }

    /**
     * Register platform-specific navigation
     */
    protected function registerPlatformNavigation(): void
    {
        if (auth()->check()) {
            $user = auth()->user();

            if ($user->hasRole('super_admin')) {
                \Filament\Facades\Filament::serving(function () {
                    \Filament\Navigation\NavigationBuilder::make()
                        ->items([
                            \Filament\Navigation\NavigationItem::make('Emergency Shutdown')
                                ->url('/superadmin/emergency-shutdown')
                                ->icon('heroicon-o-exclamation-triangle')
                                ->group('Emergency Actions')
                                ->sort(1)
                                ->badge('DANGER')
                                ->badgeColor('danger'),

                            \Filament\Navigation\NavigationItem::make('System Logs')
                                ->url('/superadmin/logs')
                                ->icon('heroicon-o-document-text')
                                ->group('Monitoring & Logs')
                                ->sort(1),

                            \Filament\Navigation\NavigationItem::make('Performance Metrics')
                                ->url('/superadmin/performance')
                                ->icon('heroicon-o-chart-bar-square')
                                ->group('Monitoring & Logs')
                                ->sort(2),
                        ]);
                });
            }
        }
    }

    /**
     * Setup security monitoring
     */
    protected function setupSecurityMonitoring(): void
    {
        // Monitor failed login attempts
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Failed::class,
            function ($event) {
                logger()->warning('Super admin login failed', [
                    'email' => $event->credentials['email'] ?? 'unknown',
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            }
        );

        // Monitor successful logins
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Login::class,
            function ($event) {
                if ($event->user->hasRole('super_admin')) {
                    logger()->info('Super admin login successful', [
                        'user_id' => $event->user->id,
                        'email' => $event->user->email,
                        'ip' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ]);
                }
            }
        );
    }

    /**
     * Register platform search providers
     */
    protected function registerPlatformSearchProviders(): void
    {
        \Filament\Facades\Filament::serving(function () {
            \Filament\GlobalSearch\GlobalSearchManager::registerProvider(
                \App\Filament\SuperAdmin\GlobalSearch\TenantSearchProvider::class
            );

            \Filament\GlobalSearch\GlobalSearchManager::registerProvider(
                \App\Filament\SuperAdmin\GlobalSearch\UserSearchProvider::class
            );

            \Filament\GlobalSearch\GlobalSearchManager::registerProvider(
                \App\Filament\SuperAdmin\GlobalSearch\SystemLogSearchProvider::class
            );

            \Filament\GlobalSearch\GlobalSearchManager::registerProvider(
                \App\Filament\SuperAdmin\GlobalSearch\ConfigurationSearchProvider::class
            );
        });
    }

    /**
     * Setup real-time monitoring
     */
    protected function setupRealTimeMonitoring(): void
    {
        \Filament\Facades\Filament::serving(function () {
            // Listen for system alerts
            \Livewire\Livewire::listen('echo:system-alerts,SystemAlert', function ($data) {
                $this->dispatch('systemAlert', $data);
            });

            // Listen for tenant events
            \Livewire\Livewire::listen('echo:tenant-events,TenantEvent', function ($data) {
                $this->dispatch('tenantEvent', $data);
            });

            // Listen for security events
            \Livewire\Livewire::listen('echo:security-events,SecurityEvent', function ($data) {
                $this->dispatch('securityEvent', $data);
            });

            // Listen for performance alerts
            \Livewire\Livewire::listen('echo:performance-alerts,PerformanceAlert', function ($data) {
                $this->dispatch('performanceAlert', $data);
            });
        });
    }

    /**
     * Register custom commands
     */
    protected function registerCustomCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \XLinic\Framework\Console\Commands\PlatformHealthCheck::class,
                \XLinic\Framework\Console\Commands\TenantCleanup::class,
                \XLinic\Framework\Console\Commands\SystemBackup::class,
                \XLinic\Framework\Console\Commands\SecurityScan::class,
                \XLinic\Framework\Console\Commands\PerformanceTuning::class,
            ]);
        }
    }

    /**
     * Get panel configuration
     */
    public static function getConfig(): array
    {
        return [
            'name' => 'XLinic Platform Admin',
            'description' => 'Multi-tenant Healthcare Platform Administration',
            'version' => '1.0.0',
            'features' => [
                'tenant_management' => true,
                'user_management' => true,
                'system_configuration' => true,
                'module_management' => true,
                'security_center' => true,
                'monitoring_logs' => true,
                'backup_restore' => true,
                'performance_monitoring' => true,
                'api_management' => true,
                'billing_subscriptions' => true,
                'support_tools' => true,
                'developer_tools' => true,
                'emergency_controls' => true,
                'compliance_reporting' => true,
                'audit_trails' => true,
            ],
            'security' => [
                'require_2fa' => true,
                'session_timeout' => 60, // minutes
                'password_confirmation_timeout' => 30, // minutes
                'max_failed_attempts' => 3,
                'lockout_duration' => 300, // seconds
                'require_password_change' => 90, // days
            ],
            'monitoring' => [
                'real_time_alerts' => true,
                'performance_tracking' => true,
                'security_scanning' => true,
                'automated_backups' => true,
                'health_checks' => true,
            ],
        ];
    }

    /**
     * Get platform statistics
     */
    public static function getStatistics(): array
    {
        return [
            'total_tenants' => \App\Models\Tenant::count(),
            'active_tenants' => \App\Models\Tenant::where('status', 'active')->count(),
            'total_users' => \App\Models\User::count(),
            'system_uptime' => $this->getSystemUptime(),
            'disk_usage' => $this->getDiskUsage(),
            'memory_usage' => $this->getMemoryUsage(),
            'active_sessions' => \Illuminate\Support\Facades\DB::table('sessions')->count(),
            'failed_jobs' => \Illuminate\Support\Facades\DB::table('failed_jobs')->count(),
        ];
    }

    /**
     * Get system uptime
     */
    protected static function getSystemUptime(): string
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $uptime = shell_exec('uptime -p');
            return trim($uptime ?? 'Unknown');
        }

        return 'Unknown';
    }

    /**
     * Get disk usage
     */
    protected static function getDiskUsage(): array
    {
        $totalSpace = disk_total_space('/');
        $freeSpace = disk_free_space('/');
        $usedSpace = $totalSpace - $freeSpace;

        return [
            'total' => $totalSpace,
            'used' => $usedSpace,
            'free' => $freeSpace,
            'percentage' => round(($usedSpace / $totalSpace) * 100, 2),
        ];
    }

    /**
     * Get memory usage
     */
    protected static function getMemoryUsage(): array
    {
        $memoryLimit = ini_get('memory_limit');
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);

        return [
            'limit' => $memoryLimit,
            'usage' => $memoryUsage,
            'peak' => $memoryPeak,
            'percentage' => round(($memoryUsage / (int) $memoryLimit) * 100, 2),
        ];
    }

    /**
     * Check if panel is available
     */
    public static function isAvailable(): bool
    {
        return config('filament.superadmin.enabled', true) &&
               !app()->isDownForMaintenance() &&
               auth()->check() &&
               auth()->user()->hasRole('super_admin');
    }

    /**
     * Get required permissions
     */
    public static function getRequiredPermissions(): array
    {
        return [
            'access_platform_admin',
            'manage_tenants',
            'manage_system_configuration',
            'view_system_logs',
            'manage_users',
        ];
    }

    /**
     * Get allowed roles
     */
    public static function getAllowedRoles(): array
    {
        return [
            'super_admin',
        ];
    }
}