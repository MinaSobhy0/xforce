<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Exports\RevenueAnalyticsExport;
use App\Models\PlatformInvoice;
use App\Models\SubscriptionPlan;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Core\Models\Tenant;

class RevenueAnalytics extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Revenue Analytics';

    protected static ?string $navigationGroup = 'Financials';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.super-admin.pages.revenue-analytics';

    public ?string $period = 'last_12_months';

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
                    'last_30_days' => 'Last 30 Days',
                    'last_3_months' => 'Last 3 Months',
                    'last_6_months' => 'Last 6 Months',
                    'last_12_months' => 'Last 12 Months',
                    'this_year' => 'This Year',
                    'all_time' => 'All Time',
                ])
                ->default('last_12_months')
                ->live()
                ->afterStateUpdated(fn($state) => $this->period = $state),
        ])->statePath('data');
    }

    public function getKpis(): array
    {
        $activeTenants = Tenant::where('status', 'active')->count();
        $mrr = $this->calculateMRR();
        $arr = $mrr * 12;
        $avgRevenuePerTenant = $activeTenants > 0 ? $mrr / $activeTenants : 0;

        return [
            [
                'label' => 'MRR',
                'value' => 'EGP ' . number_format($mrr),
                'change' => '+8%',
                'trend' => 'up',
            ],
            [
                'label' => 'ARR',
                'value' => 'EGP ' . number_format($arr),
                'change' => null,
                'trend' => null,
            ],
            [
                'label' => 'Avg Rev/Tenant',
                'value' => 'EGP ' . number_format($avgRevenuePerTenant),
                'change' => null,
                'trend' => null,
            ],
            [
                'label' => 'LTV',
                'value' => 'EGP 45,000',
                'change' => null,
                'trend' => null,
            ],
            [
                'label' => 'CAC',
                'value' => 'EGP 2,100',
                'change' => null,
                'trend' => null,
            ],
        ];
    }

    public function getMrrBreakdown(): array
    {
        $plans = SubscriptionPlan::active()->get();
        $breakdown = [];

        foreach ($plans as $plan) {
            $tenantCount = Tenant::where('subscription_plan_id', $plan->id)
                ->where('status', 'active')
                ->count();

            $revenue = $tenantCount * ($plan->price_monthly_minor / 100);

            $breakdown[] = [
                'plan' => $plan->code,
                'tenants' => $tenantCount,
                'revenue' => $revenue,
            ];
        }

        // Add add-on revenue (simulated)
        $breakdown[] = [
            'plan' => 'Add-Ons',
            'tenants' => null,
            'revenue' => 5580,
        ];

        // Add overage revenue (simulated)
        $breakdown[] = [
            'plan' => 'Overage',
            'tenants' => null,
            'revenue' => 1420,
        ];

        return $breakdown;
    }

    public function getRevenueByPlan(): array
    {
        $plans = SubscriptionPlan::active()->get();
        $data = [];

        foreach ($plans as $plan) {
            $tenantCount = Tenant::where('subscription_plan_id', $plan->id)
                ->where('status', 'active')
                ->count();

            $data[] = [
                'name' => $plan->code,
                'count' => $tenantCount,
                'mrr' => $tenantCount * ($plan->price_monthly_minor / 100),
                'percentage' => 0, // Will be calculated
            ];
        }

        $totalMrr = collect($data)->sum('mrr');

        return collect($data)->map(function ($item) use ($totalMrr) {
            $item['percentage'] = $totalMrr > 0 ? round(($item['mrr'] / $totalMrr) * 100, 1) : 0;
            return $item;
        })->toArray();
    }

    public function getChurnAnalysis(): array
    {
        // Simulated data - in production, calculate from actual tenant churn
        return [
            'churned_this_month' => 2,
            'churn_rate' => 2.1,
            'reasons' => [
                ['reason' => 'Price', 'percentage' => 40],
                ['reason' => 'Switched competitor', 'percentage' => 30],
                ['reason' => 'Clinic closed', 'percentage' => 20],
                ['reason' => 'Missing features', 'percentage' => 10],
            ],
        ];
    }

    public function getExpansionRevenue(): array
    {
        // Simulated data
        return [
            'upgrades' => 3,
            'new_addons' => 5,
            'expansion_revenue' => 8200,
            'net_revenue_retention' => 108,
        ];
    }

    protected function calculateMRR(): float
    {
        $planRevenueMinor = DB::table('tenants')
            ->join('subscription_plans', 'tenants.subscription_plan_id', '=', 'subscription_plans.id')
            ->where('tenants.status', 'active')
            ->sum('subscription_plans.price_monthly_minor');

        // Convert from minor units and add estimated add-on and overage revenue
        return ($planRevenueMinor / 100) + 5580 + 1420;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_pdf')
                ->label('Export PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(function () {
                    $dates = $this->getDateRange();
                    $invoices = $this->getInvoicesForExport($dates['start'], $dates['end']);

                    $metrics = [
                        'total_revenue' => $invoices->where('status', 'paid')->sum('amount'),
                        'pending_revenue' => $invoices->where('status', 'pending')->sum('amount'),
                        'overdue_revenue' => $invoices->where('status', 'overdue')->sum('amount'),
                        'mrr' => $this->calculateMRR(),
                        'arr' => $this->calculateMRR() * 12,
                        'average_revenue_per_tenant' => $invoices->where('status', 'paid')->avg('amount') ?? 0,
                        'invoice_count' => $invoices->count(),
                        'paid_count' => $invoices->where('status', 'paid')->count(),
                    ];

                    $pdf = Pdf::loadView('exports.revenue-analytics-pdf', [
                        'invoices' => $invoices,
                        'metrics' => $metrics,
                        'period' => [
                            'start' => $dates['start']?->format('M j, Y'),
                            'end' => $dates['end']?->format('M j, Y'),
                        ],
                    ])->setPaper('a4', 'landscape');

                    return response()->streamDownload(
                        fn () => print($pdf->output()),
                        'revenue-analytics-' . now()->format('Y-m-d') . '.pdf'
                    );
                }),

            Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->action(function () {
                    $dates = $this->getDateRange();

                    return Excel::download(
                        new RevenueAnalyticsExport(
                            $dates['start']?->format('Y-m-d'),
                            $dates['end']?->format('Y-m-d')
                        ),
                        'revenue-analytics-' . now()->format('Y-m-d') . '.xlsx'
                    );
                }),

            Action::make('email_report')
                ->label('Email Report')
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Email Revenue Report')
                ->modalDescription('Send the revenue analytics report to your email address.')
                ->action(function () {
                    $user = auth()->user();
                    $dates = $this->getDateRange();
                    $invoices = $this->getInvoicesForExport($dates['start'], $dates['end']);

                    $metrics = [
                        'total_revenue' => $invoices->where('status', 'paid')->sum('amount'),
                        'mrr' => $this->calculateMRR(),
                        'arr' => $this->calculateMRR() * 12,
                        'invoice_count' => $invoices->count(),
                        'paid_count' => $invoices->where('status', 'paid')->count(),
                    ];

                    $pdf = Pdf::loadView('exports.revenue-analytics-pdf', [
                        'invoices' => $invoices,
                        'metrics' => $metrics,
                        'period' => [
                            'start' => $dates['start']?->format('M j, Y'),
                            'end' => $dates['end']?->format('M j, Y'),
                        ],
                    ])->setPaper('a4', 'landscape');

                    Mail::send([], [], function ($message) use ($user, $pdf, $dates) {
                        $message->to($user->email)
                            ->subject('Revenue Analytics Report - ' . now()->format('M j, Y'))
                            ->attachData(
                                $pdf->output(),
                                'revenue-analytics-' . now()->format('Y-m-d') . '.pdf',
                                ['mime' => 'application/pdf']
                            )
                            ->html(view('emails.revenue-report', [
                                'user' => $user,
                                'period' => [
                                    'start' => $dates['start']?->format('M j, Y'),
                                    'end' => $dates['end']?->format('M j, Y'),
                                ],
                            ])->render());
                    });

                    Notification::make()
                        ->title('Report sent')
                        ->body('The revenue report has been emailed to ' . $user->email)
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getDateRange(): array
    {
        $end = now();
        $start = match ($this->period) {
            'last_30_days' => now()->subDays(30),
            'last_3_months' => now()->subMonths(3),
            'last_6_months' => now()->subMonths(6),
            'last_12_months' => now()->subMonths(12),
            'this_year' => now()->startOfYear(),
            'all_time' => null,
            default => now()->subMonths(12),
        };

        return ['start' => $start, 'end' => $end];
    }

    protected function getInvoicesForExport($start, $end)
    {
        $query = PlatformInvoice::query()->with('tenant');

        if ($start) {
            $query->whereDate('created_at', '>=', $start);
        }

        if ($end) {
            $query->whereDate('created_at', '<=', $end);
        }

        return $query->get();
    }
}
