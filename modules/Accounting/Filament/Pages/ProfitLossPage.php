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
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\JournalEntryLine;
use Modules\Accounting\Services\ProfitLossPdfService;
use Carbon\Carbon;

class ProfitLossPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static string $view = 'accounting::filament.pages.profit-loss';

    protected static ?string $navigationGroup = 'Financial Reports';

    protected static ?int $navigationSort = 2;

    public ?string $start_date = null;
    public ?string $end_date = null;
    public array $revenues = [];
    public array $expenses = [];
    public int $totalRevenue = 0;
    public int $totalExpenses = 0;
    public int $netIncome = 0;

    public static function getNavigationLabel(): string
    {
        return __('accounting::accounting.profit_loss');
    }

    public function getTitle(): string
    {
        return __('accounting::accounting.profit_loss_statement');
    }

    public function mount(): void
    {
        $this->start_date = now()->startOfMonth()->format('Y-m-d');
        $this->end_date = now()->format('Y-m-d');
        $this->loadProfitLoss();
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
                            ->afterStateUpdated(fn () => $this->loadProfitLoss()),

                        DatePicker::make('end_date')
                            ->label(__('accounting::accounting.end_date'))
                            ->reactive()
                            ->afterStateUpdated(fn () => $this->loadProfitLoss()),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function loadProfitLoss(): void
    {
        $startDate = Carbon::parse($this->start_date);
        $endDate = Carbon::parse($this->end_date);

        // Revenue accounts (type = 'revenue')
        $revenueAccounts = ChartOfAccount::where('type', 'revenue')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $this->revenues = [];
        $this->totalRevenue = 0;

        foreach ($revenueAccounts as $account) {
            $balance = JournalEntryLine::where('account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('date', [$startDate, $endDate])
                        ->where('status', 'posted');
                })
                ->sum(DB::raw('credit_minor - debit_minor'));

            if ($balance != 0) {
                $this->revenues[] = [
                    'code' => $account->code,
                    'name' => $account->name,
                    'amount' => $balance,
                ];
                $this->totalRevenue += $balance;
            }
        }

        // Expense accounts (type = 'expense')
        $expenseAccounts = ChartOfAccount::where('type', 'expense')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $this->expenses = [];
        $this->totalExpenses = 0;

        foreach ($expenseAccounts as $account) {
            $balance = JournalEntryLine::where('account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('date', [$startDate, $endDate])
                        ->where('status', 'posted');
                })
                ->sum(DB::raw('debit_minor - credit_minor'));

            if ($balance != 0) {
                $this->expenses[] = [
                    'code' => $account->code,
                    'name' => $account->name,
                    'amount' => $balance,
                ];
                $this->totalExpenses += $balance;
            }
        }

        $this->netIncome = $this->totalRevenue - $this->totalExpenses;
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
                ->action(fn () => $this->loadProfitLoss()),
        ];
    }

    public function exportPdf()
    {
        $service = app(ProfitLossPdfService::class);
        return $service->download(
            $this->start_date ?? now()->startOfMonth()->format('Y-m-d'),
            $this->end_date ?? now()->format('Y-m-d')
        );
    }

    protected function formatCurrency(int $amountMinor): string
    {
        return number_format($amountMinor / 100, 2) . ' EGP';
    }
}
