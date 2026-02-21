<?php

namespace Modules\Accounting\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Actions\Action;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\Expense;
use Modules\Payroll\Models\PayrollRun;
use Carbon\Carbon;

class CashFlowPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static string $view = 'accounting::filament.pages.cash-flow';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationParentItem = 'Reports';


    protected static ?int $navigationSort = 5;

    public ?string $start_date = null;
    public ?string $end_date = null;
    public array $operatingActivities = [];
    public array $investingActivities = [];
    public array $financingActivities = [];
    public int $netOperating = 0;
    public int $netInvesting = 0;
    public int $netFinancing = 0;
    public int $netCashFlow = 0;

    public static function getNavigationLabel(): string
    {
        return __('accounting::accounting.cash_flow');
    }

    public function getTitle(): string
    {
        return __('accounting::accounting.cash_flow_statement');
    }

    public function mount(): void
    {
        $this->start_date = now()->startOfMonth()->format('Y-m-d');
        $this->end_date = now()->format('Y-m-d');
        $this->loadCashFlow();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('accounting::accounting.period'))
                    ->schema([
                        DatePicker::make('start_date')
                            ->label(__('accounting::accounting.start_date'))
                            ->reactive()
                            ->afterStateUpdated(fn () => $this->loadCashFlow()),

                        DatePicker::make('end_date')
                            ->label(__('accounting::accounting.end_date'))
                            ->reactive()
                            ->afterStateUpdated(fn () => $this->loadCashFlow()),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function loadCashFlow(): void
    {
        $startDate = Carbon::parse($this->start_date);
        $endDate = Carbon::parse($this->end_date);

        // Operating Activities
        $this->operatingActivities = [];

        // Cash received from customers
        $paymentsReceived = Payment::whereBetween('paid_at', [$startDate, $endDate])
            ->sum('amount_minor');

        $this->operatingActivities[] = [
            'description' => __('accounting::accounting.cash_from_customers'),
            'amount' => $paymentsReceived,
            'type' => 'inflow',
        ];

        // Cash paid for expenses
        $expensesPaid = 0;
        if (class_exists(Expense::class)) {
            $expensesPaid = Expense::whereBetween('expense_date', [$startDate, $endDate])
                ->sum('amount_minor');
        }

        $this->operatingActivities[] = [
            'description' => __('accounting::accounting.cash_for_expenses'),
            'amount' => -$expensesPaid,
            'type' => 'outflow',
        ];

        // Cash paid for payroll
        $payrollPaid = 0;
        if (class_exists(PayrollRun::class)) {
            $payrollPaid = PayrollRun::whereBetween('paid_at', [$startDate, $endDate])
                ->where('status', 'paid')
                ->sum('total_net_salary_minor');
        }

        $this->operatingActivities[] = [
            'description' => __('accounting::accounting.cash_for_payroll'),
            'amount' => -$payrollPaid,
            'type' => 'outflow',
        ];

        $this->netOperating = $paymentsReceived - $expensesPaid - $payrollPaid;

        // Investing Activities (simplified - could include equipment purchases)
        $this->investingActivities = [];
        $this->netInvesting = 0;

        // Financing Activities (simplified)
        $this->financingActivities = [];
        $this->netFinancing = 0;

        $this->netCashFlow = $this->netOperating + $this->netInvesting + $this->netFinancing;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_pdf')
                ->label(__('accounting::accounting.export_pdf'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(fn () => $this->exportPdf()),

            Action::make('refresh')
                ->label(__('accounting::accounting.refresh'))
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->loadCashFlow()),
        ];
    }

    public function exportPdf()
    {
        // PDF export implementation
    }

    protected function formatCurrency(int $amountMinor): string
    {
        return number_format($amountMinor / 100, 2) . ' EGP';
    }
}
