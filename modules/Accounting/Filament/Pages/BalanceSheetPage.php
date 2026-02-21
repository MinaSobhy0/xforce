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
use Modules\Accounting\Services\BalanceSheetPdfService;
use Carbon\Carbon;

class BalanceSheetPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static string $view = 'accounting::filament.pages.balance-sheet';

    protected static ?string $navigationGroup = 'Financial Reports';

    protected static ?int $navigationSort = 3;

    public ?string $as_of_date = null;
    public array $assets = [];
    public array $liabilities = [];
    public array $equity = [];
    public int $totalAssets = 0;
    public int $totalLiabilities = 0;
    public int $totalEquity = 0;

    public static function getNavigationLabel(): string
    {
        return __('accounting::accounting.balance_sheet');
    }

    public function getTitle(): string
    {
        return __('accounting::accounting.balance_sheet');
    }

    public function mount(): void
    {
        $this->as_of_date = now()->format('Y-m-d');
        $this->loadBalanceSheet();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('accounting::accounting.filters'))
                    ->schema([
                        DatePicker::make('as_of_date')
                            ->label(__('accounting::accounting.as_of_date'))
                            ->default(now())
                            ->reactive()
                            ->afterStateUpdated(fn () => $this->loadBalanceSheet()),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function loadBalanceSheet(): void
    {
        $asOfDate = Carbon::parse($this->as_of_date ?? now());

        // Assets
        $this->assets = $this->getAccountBalances('asset', $asOfDate);
        $this->totalAssets = array_sum(array_column($this->assets, 'amount'));

        // Liabilities
        $this->liabilities = $this->getAccountBalances('liability', $asOfDate);
        $this->totalLiabilities = array_sum(array_column($this->liabilities, 'amount'));

        // Equity
        $this->equity = $this->getAccountBalances('equity', $asOfDate);
        $this->totalEquity = array_sum(array_column($this->equity, 'amount'));

        // Add retained earnings (revenues - expenses)
        $retainedEarnings = $this->calculateRetainedEarnings($asOfDate);
        if ($retainedEarnings != 0) {
            $this->equity[] = [
                'code' => 'RE',
                'name' => __('accounting::accounting.retained_earnings'),
                'amount' => $retainedEarnings,
            ];
            $this->totalEquity += $retainedEarnings;
        }
    }

    protected function getAccountBalances(string $type, Carbon $asOfDate): array
    {
        $accounts = ChartOfAccount::where('type', $type)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $balances = [];

        foreach ($accounts as $account) {
            $balance = JournalEntryLine::where('account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                    $q->where('date', '<=', $asOfDate)
                        ->where('status', 'posted');
                })
                ->sum(DB::raw('debit_minor - credit_minor'));

            // Liabilities and equity have credit balances
            if (in_array($type, ['liability', 'equity'])) {
                $balance = -$balance;
            }

            if ($balance != 0) {
                $balances[] = [
                    'code' => $account->code,
                    'name' => $account->name,
                    'amount' => abs($balance),
                ];
            }
        }

        return $balances;
    }

    protected function calculateRetainedEarnings(Carbon $asOfDate): int
    {
        $revenues = JournalEntryLine::whereHas('account', fn ($q) => $q->where('type', 'revenue'))
            ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                $q->where('date', '<=', $asOfDate)
                    ->where('status', 'posted');
            })
            ->sum(DB::raw('credit_minor - debit_minor'));

        $expenses = JournalEntryLine::whereHas('account', fn ($q) => $q->where('type', 'expense'))
            ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                $q->where('date', '<=', $asOfDate)
                    ->where('status', 'posted');
            })
            ->sum(DB::raw('debit_minor - credit_minor'));

        return $revenues - $expenses;
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
                ->action(fn () => $this->loadBalanceSheet()),
        ];
    }

    public function exportPdf()
    {
        $service = app(BalanceSheetPdfService::class);
        return $service->download($this->as_of_date ?? now()->format('Y-m-d'));
    }

    protected function formatCurrency(int $amountMinor): string
    {
        return number_format($amountMinor / 100, 2) . ' EGP';
    }
}
