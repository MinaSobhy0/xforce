<?php

namespace Modules\Core\Filament\Pages;

use Filament\Pages\Page;
use Modules\Core\Models\TenantUsage;
use XLinic\Framework\Core\Tenancy\TenantManager;

class UsageDashboardPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'core::filament.pages.usage-dashboard';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 60;

    public ?array $usage = [];
    public ?array $limits = [];
    public ?array $monthlyStats = [];

    public static function getNavigationLabel(): string
    {
        return __('core::core.usage_dashboard');
    }

    public function getTitle(): string
    {
        return __('core::core.usage_dashboard');
    }

    public function mount(): void
    {
        $tenantManager = app(TenantManager::class);
        $tenant = $tenantManager->current();

        if (!$tenant) {
            $this->usage = [];
            $this->limits = [];
            $this->monthlyStats = [];
            return;
        }

        // Get current usage
        $tenantUsage = TenantUsage::where('tenant_id', $tenant->id)->first();

        // Get plan limits from tenant settings (set by SuperAdmin)
        $this->limits = [
            'users' => $tenant->max_users ?? 10,
            'branches' => $tenant->max_branches ?? 3,
            'patients' => $tenant->max_patients ?? 1000,
            'storage_gb' => round(($tenant->max_storage_mb ?? 1024) / 1024, 1), // Convert MB to GB
            'whatsapp_messages' => $tenant->getSetting('max_whatsapp_messages', 500),
            'sms_messages' => $tenant->getSetting('max_sms_messages', 200),
            'emails' => $tenant->getSetting('max_emails', 1000),
            'api_requests' => $tenant->getSetting('max_api_requests', 10000),
        ];

        if ($tenantUsage) {
            $this->usage = [
                'users' => $tenantUsage->users_count ?? 0,
                'branches' => $tenantUsage->branches_count ?? 0,
                'patients' => $tenantUsage->patients_count ?? 0,
                'storage_gb' => round(($tenantUsage->storage_used_bytes ?? 0) / (1024 * 1024 * 1024), 2),
                'whatsapp_messages' => $tenantUsage->whatsapp_messages_count ?? 0,
                'sms_messages' => $tenantUsage->sms_messages_count ?? 0,
                'emails' => $tenantUsage->emails_sent_count ?? 0,
                'api_requests' => $tenantUsage->api_requests_count ?? 0,
            ];

            $this->monthlyStats = [
                'appointments_this_month' => $tenantUsage->appointments_this_month ?? 0,
                'revenue_this_month' => $tenantUsage->revenue_this_month_minor ?? 0,
                'new_patients_this_month' => $tenantUsage->new_patients_this_month ?? 0,
                'treatments_this_month' => $tenantUsage->treatments_this_month ?? 0,
            ];
        } else {
            $this->usage = array_fill_keys(array_keys($this->limits), 0);
            $this->monthlyStats = [
                'appointments_this_month' => 0,
                'revenue_this_month' => 0,
                'new_patients_this_month' => 0,
                'treatments_this_month' => 0,
            ];
        }
    }

    public function getUsageItems(): array
    {
        return [
            [
                'label' => __('core::core.users'),
                'icon' => 'heroicon-o-users',
                'current' => $this->usage['users'] ?? 0,
                'limit' => $this->limits['users'] ?? 0,
                'color' => 'primary',
            ],
            [
                'label' => __('core::core.branches'),
                'icon' => 'heroicon-o-building-office-2',
                'current' => $this->usage['branches'] ?? 0,
                'limit' => $this->limits['branches'] ?? 0,
                'color' => 'success',
            ],
            [
                'label' => __('core::core.patients'),
                'icon' => 'heroicon-o-user-group',
                'current' => $this->usage['patients'] ?? 0,
                'limit' => $this->limits['patients'] ?? 0,
                'color' => 'info',
            ],
            [
                'label' => __('core::core.storage'),
                'icon' => 'heroicon-o-server',
                'current' => $this->usage['storage_gb'] ?? 0,
                'limit' => $this->limits['storage_gb'] ?? 0,
                'suffix' => 'GB',
                'color' => 'warning',
            ],
        ];
    }

    public function getMonthlyUsageItems(): array
    {
        return [
            [
                'label' => __('core::core.whatsapp_messages'),
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'current' => $this->usage['whatsapp_messages'] ?? 0,
                'limit' => $this->limits['whatsapp_messages'] ?? 0,
                'color' => 'success',
            ],
            [
                'label' => __('core::core.sms_messages'),
                'icon' => 'heroicon-o-device-phone-mobile',
                'current' => $this->usage['sms_messages'] ?? 0,
                'limit' => $this->limits['sms_messages'] ?? 0,
                'color' => 'info',
            ],
            [
                'label' => __('core::core.emails'),
                'icon' => 'heroicon-o-envelope',
                'current' => $this->usage['emails'] ?? 0,
                'limit' => $this->limits['emails'] ?? 0,
                'color' => 'primary',
            ],
            [
                'label' => __('core::core.api_requests'),
                'icon' => 'heroicon-o-code-bracket',
                'current' => $this->usage['api_requests'] ?? 0,
                'limit' => $this->limits['api_requests'] ?? 0,
                'color' => 'gray',
            ],
        ];
    }

    public function getStatsItems(): array
    {
        return [
            [
                'label' => __('core::core.appointments_this_month'),
                'value' => $this->monthlyStats['appointments_this_month'] ?? 0,
                'icon' => 'heroicon-o-calendar',
                'color' => 'primary',
            ],
            [
                'label' => __('core::core.revenue_this_month'),
                'value' => number_format(($this->monthlyStats['revenue_this_month'] ?? 0) / 100, 2) . ' EGP',
                'icon' => 'heroicon-o-banknotes',
                'color' => 'success',
            ],
            [
                'label' => __('core::core.new_patients_this_month'),
                'value' => $this->monthlyStats['new_patients_this_month'] ?? 0,
                'icon' => 'heroicon-o-user-plus',
                'color' => 'info',
            ],
            [
                'label' => __('core::core.treatments_this_month'),
                'value' => $this->monthlyStats['treatments_this_month'] ?? 0,
                'icon' => 'heroicon-o-heart',
                'color' => 'warning',
            ],
        ];
    }

    public function calculatePercentage(int|float $current, int|float $limit): float
    {
        if ($limit <= 0) {
            return 0;
        }

        return min(100, round(($current / $limit) * 100, 1));
    }

    public function getPercentageColor(float $percentage): string
    {
        if ($percentage >= 90) {
            return 'danger';
        }

        if ($percentage >= 75) {
            return 'warning';
        }

        return 'success';
    }
}
