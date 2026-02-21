<?php

namespace App\Filament\SuperAdmin\Pages;

use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Tenant;

class UsageAnalytics extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationLabel = 'Usage Analytics';

    protected static ?string $navigationGroup = 'Financials';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.super-admin.pages.usage-analytics';

    public ?string $period = 'last_30_days';

    public function mount(): void
    {
        $this->form->fill([
            'period' => $this->period,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('period')
                ->options([
                    'last_7_days' => 'Last 7 Days',
                    'last_30_days' => 'Last 30 Days',
                    'last_3_months' => 'Last 3 Months',
                    'this_year' => 'This Year',
                ])
                ->default('last_30_days')
                ->live()
                ->afterStateUpdated(fn($state) => $this->period = $state),
        ])->statePath('data');
    }

    public function getAggregateMetrics(): array
    {
        // In production, these would be calculated from actual usage data
        return [
            [
                'label' => 'Appointments',
                'value' => '42,500',
                'description' => 'this month',
                'icon' => 'heroicon-o-calendar',
            ],
            [
                'label' => 'Patients Created',
                'value' => '3,200',
                'description' => 'this month',
                'icon' => 'heroicon-o-user-plus',
            ],
            [
                'label' => 'WhatsApp Messages',
                'value' => '85,000',
                'description' => 'this month',
                'icon' => 'heroicon-o-chat-bubble-left',
            ],
            [
                'label' => 'SMS Messages',
                'value' => '12,400',
                'description' => 'this month',
                'icon' => 'heroicon-o-device-phone-mobile',
            ],
            [
                'label' => 'Storage Used',
                'value' => '234 GB',
                'description' => 'of 500 GB',
                'icon' => 'heroicon-o-server-stack',
            ],
        ];
    }

    public function getTopTenantsByUsage(): array
    {
        // Simulated data - in production, join with tenant_usage table
        return [
            [
                'name' => 'Dr. Layla Center',
                'appointments' => 5200,
                'patients' => 8100,
                'whatsapp' => 12000,
                'storage' => '45.2 GB',
            ],
            [
                'name' => 'Cairo Glow',
                'appointments' => 3850,
                'patients' => 4200,
                'whatsapp' => 8200,
                'storage' => '34.2 GB',
            ],
            [
                'name' => 'Beauty Hub',
                'appointments' => 2100,
                'patients' => 1850,
                'whatsapp' => 4500,
                'storage' => '12.1 GB',
            ],
            [
                'name' => 'Glow & Shine',
                'appointments' => 1800,
                'patients' => 1200,
                'whatsapp' => 3200,
                'storage' => '8.4 GB',
            ],
            [
                'name' => 'Skin Perfect',
                'appointments' => 1500,
                'patients' => 980,
                'whatsapp' => 2800,
                'storage' => '6.7 GB',
            ],
        ];
    }

    public function getUsageTrendData(): array
    {
        // Generate 3 months of usage trend data
        $months = [];
        $appointments = [];
        $patients = [];
        $messages = [];

        for ($i = 2; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = $date->format('M Y');

            // In production, these would come from actual usage tracking
            // For now, generate realistic trending data
            $baseAppointments = 35000 + ($i * 2500);
            $basePatients = 2500 + ($i * 300);
            $baseMessages = 70000 + ($i * 5000);

            $appointments[] = $baseAppointments + rand(-2000, 5000);
            $patients[] = $basePatients + rand(-200, 500);
            $messages[] = $baseMessages + rand(-3000, 8000);
        }

        return [
            'labels' => $months,
            'datasets' => [
                [
                    'label' => 'Appointments',
                    'data' => $appointments,
                    'borderColor' => 'rgb(99, 102, 241)',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'tension' => 0.3,
                    'fill' => true,
                ],
                [
                    'label' => 'New Patients',
                    'data' => $patients,
                    'borderColor' => 'rgb(16, 185, 129)',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'tension' => 0.3,
                    'fill' => true,
                ],
                [
                    'label' => 'Messages (WhatsApp + SMS)',
                    'data' => $messages,
                    'borderColor' => 'rgb(245, 158, 11)',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'tension' => 0.3,
                    'fill' => true,
                ],
            ],
        ];
    }

    public function getQuotaWarnings(): array
    {
        // In production, query tenants approaching their limits
        return [
            [
                'tenant' => 'Beauty Hub',
                'warning' => 'Users at 90% (18/20)',
                'severity' => 'warning',
                'metric' => 'users',
            ],
            [
                'tenant' => 'Glow Shine',
                'warning' => 'Patients at 85% (425/500)',
                'severity' => 'warning',
                'metric' => 'patients',
            ],
            [
                'tenant' => 'Skin Lab',
                'warning' => 'Storage at 95% (1.9/2 GB) — UPGRADE NEEDED',
                'severity' => 'critical',
                'metric' => 'storage',
            ],
            [
                'tenant' => 'Nour Beauty',
                'warning' => 'Appointments at 80% (1,600/2,000)',
                'severity' => 'warning',
                'metric' => 'appointments',
            ],
        ];
    }

    public function sendUpgradeNudge(): void
    {
        // In production, send emails to tenants approaching limits
        \Filament\Notifications\Notification::make()
            ->title('Upgrade nudge emails sent')
            ->body('Emails sent to ' . count($this->getQuotaWarnings()) . ' clinics approaching their limits.')
            ->success()
            ->send();
    }
}
