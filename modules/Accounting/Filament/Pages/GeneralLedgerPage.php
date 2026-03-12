<?php

namespace Modules\Accounting\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Actions\Action;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\JournalEntryLine;
use Carbon\Carbon;

class GeneralLedgerPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static string $view = 'accounting::filament.pages.general-ledger';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 13;

    public static function canAccess(): bool
    {
        return \Modules\Accounting\Filament\Pages\Concerns\ChecksAccountingPermissions::check('general_ledger.view');
    }

    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?string $account_id = null;
    public ?string $account_type = null;
    public array $accountBalances = [];
    public array $ledgerEntries = [];
    public int $openingBalance = 0;
    public int $closingBalance = 0;
    public int $totalDebit = 0;
    public int $totalCredit = 0;
    public ?string $selectedAccountName = null;

    public static function getNavigationLabel(): string
    {
        return __('accounting::accounting.general_ledger');
    }

    public function getTitle(): string
    {
        return __('accounting::accounting.general_ledger');
    }

    public function mount(): void
    {
        // Check for date range in query string, otherwise use defaults
        $this->start_date = request()->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $this->end_date = request()->get('end_date', now()->format('Y-m-d'));

        // Check for account_id in query string
        if (request()->has('account_id')) {
            $this->account_id = request()->get('account_id');
            $this->loadSingleAccountLedger();
        } else {
            $this->loadAllAccountBalances();
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('accounting::accounting.filters'))
                    ->schema([
                        DatePicker::make('start_date')
                            ->label(__('accounting::accounting.start_date'))
                            ->live()
                            ->afterStateUpdated(fn () => $this->applyFilters()),

                        DatePicker::make('end_date')
                            ->label(__('accounting::accounting.end_date'))
                            ->live()
                            ->afterStateUpdated(fn () => $this->applyFilters()),

                        Select::make('account_type')
                            ->label(__('accounting::accounting.account_type'))
                            ->options([
                                '' => __('accounting::accounting.all_types'),
                                'asset' => __('accounting::accounting.assets'),
                                'liability' => __('accounting::accounting.liabilities'),
                                'equity' => __('accounting::accounting.equity'),
                                'revenue' => __('accounting::accounting.revenue'),
                                'expense' => __('accounting::accounting.expenses'),
                            ])
                            ->live()
                            ->afterStateUpdated(fn () => $this->applyFilters()),

                        Select::make('account_id')
                            ->label(__('accounting::accounting.account'))
                            ->options(
                                ChartOfAccount::where('is_active', true)
                                    ->orderBy('code')
                                    ->get()
                                    ->mapWithKeys(fn ($a) => [$a->id => "{$a->code} - {$a->name}"])
                            )
                            ->searchable()
                            ->placeholder(__('accounting::accounting.all_accounts'))
                            ->live()
                            ->afterStateUpdated(fn () => $this->applyFilters()),
                    ])
                    ->columns(4),
            ]);
    }

    public function applyFilters(): void
    {
        if ($this->account_id) {
            $this->loadSingleAccountLedger();
        } else {
            $this->loadAllAccountBalances();
        }
    }

    public function loadAllAccountBalances(): void
    {
        $this->ledgerEntries = [];
        $this->selectedAccountName = null;

        $startDate = Carbon::parse($this->start_date);
        $endDate = Carbon::parse($this->end_date);

        $query = ChartOfAccount::where('is_active', true)->orderBy('code');

        if ($this->account_type) {
            $query->where('type', $this->account_type);
        }

        $accounts = $query->get();

        $this->accountBalances = [];
        $this->totalDebit = 0;
        $this->totalCredit = 0;

        foreach ($accounts as $account) {
            // Opening balance (before start date)
            $openingBalance = JournalEntryLine::where('account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($startDate) {
                    $q->where('date', '<', $startDate)
                        ->where('status', 'posted');
                })
                ->sum(DB::raw('debit_minor - credit_minor'));

            // Period activity
            $periodDebit = JournalEntryLine::where('account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('date', [$startDate, $endDate])
                        ->where('status', 'posted');
                })
                ->sum('debit_minor');

            $periodCredit = JournalEntryLine::where('account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('date', [$startDate, $endDate])
                        ->where('status', 'posted');
                })
                ->sum('credit_minor');

            $closingBalance = $openingBalance + $periodDebit - $periodCredit;

            // Only show accounts with activity or balance
            if ($openingBalance != 0 || $periodDebit != 0 || $periodCredit != 0) {
                // Get translated name - handle both array and string formats
                $name = $account->getTranslation('name', app()->getLocale(), false)
                    ?? $account->getTranslation('name', 'en', false)
                    ?? $account->name;

                $this->accountBalances[] = [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => is_array($name) ? ($name[app()->getLocale()] ?? $name['en'] ?? '') : (string) $name,
                    'type' => $account->type,
                    'opening_balance' => $openingBalance,
                    'debit' => $periodDebit,
                    'credit' => $periodCredit,
                    'closing_balance' => $closingBalance,
                ];

                $this->totalDebit += $periodDebit;
                $this->totalCredit += $periodCredit;
            }
        }
    }

    public function loadSingleAccountLedger(): void
    {
        $this->accountBalances = [];

        $startDate = Carbon::parse($this->start_date);
        $endDate = Carbon::parse($this->end_date);

        $account = ChartOfAccount::find($this->account_id);
        if (!$account) {
            return;
        }

        // Get translated name - handle both array and string formats
        $name = $account->getTranslation('name', app()->getLocale(), false)
            ?? $account->getTranslation('name', 'en', false)
            ?? $account->name;
        $nameStr = is_array($name) ? ($name[app()->getLocale()] ?? $name['en'] ?? '') : (string) $name;

        $this->selectedAccountName = "{$account->code} - {$nameStr}";

        // Opening balance (before start date)
        $this->openingBalance = JournalEntryLine::where('account_id', $this->account_id)
            ->whereHas('journalEntry', function ($q) use ($startDate) {
                $q->where('date', '<', $startDate)
                    ->where('status', 'posted');
            })
            ->sum(DB::raw('debit_minor - credit_minor'));

        // Ledger entries in period
        $entries = JournalEntryLine::where('account_id', $this->account_id)
            ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('date', [$startDate, $endDate])
                    ->where('status', 'posted');
            })
            ->with(['journalEntry'])
            ->orderBy(DB::raw('(SELECT date FROM journal_entries WHERE journal_entries.id = journal_entry_lines.journal_entry_id)'))
            ->get();

        $runningBalance = $this->openingBalance;
        $this->ledgerEntries = [];
        $this->totalDebit = 0;
        $this->totalCredit = 0;

        foreach ($entries as $entry) {
            $runningBalance += ($entry->debit_minor - $entry->credit_minor);
            $this->totalDebit += $entry->debit_minor;
            $this->totalCredit += $entry->credit_minor;

            $this->ledgerEntries[] = [
                'date' => $entry->journalEntry->date->format('Y-m-d'),
                'entry_number' => $entry->journalEntry->code,
                'description' => $entry->description ?? $entry->journalEntry->description,
                'debit' => $entry->debit_minor,
                'credit' => $entry->credit_minor,
                'balance' => $runningBalance,
            ];
        }

        $this->closingBalance = $runningBalance;
    }

    public function viewAccount(string $accountId): void
    {
        $this->account_id = $accountId;
        $this->loadSingleAccountLedger();
    }

    public function clearAccountFilter(): void
    {
        $this->account_id = null;
        $this->loadAllAccountBalances();
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
                ->action(fn () => $this->applyFilters()),
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
