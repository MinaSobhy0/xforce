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

    protected static ?string $navigationGroup = 'Financial Reports';

    protected static ?int $navigationSort = 4;

    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?string $account_id = null;
    public array $ledgerEntries = [];
    public int $openingBalance = 0;
    public int $closingBalance = 0;

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
        $this->start_date = now()->startOfMonth()->format('Y-m-d');
        $this->end_date = now()->format('Y-m-d');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('accounting::accounting.filters'))
                    ->schema([
                        Select::make('account_id')
                            ->label(__('accounting::accounting.account'))
                            ->options(
                                ChartOfAccount::where('is_active', true)
                                    ->orderBy('code')
                                    ->get()
                                    ->mapWithKeys(fn ($a) => [$a->id => "{$a->code} - {$a->name}"])
                            )
                            ->searchable()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn () => $this->loadLedger()),

                        DatePicker::make('start_date')
                            ->label(__('accounting::accounting.start_date'))
                            ->reactive()
                            ->afterStateUpdated(fn () => $this->loadLedger()),

                        DatePicker::make('end_date')
                            ->label(__('accounting::accounting.end_date'))
                            ->reactive()
                            ->afterStateUpdated(fn () => $this->loadLedger()),
                    ])
                    ->columns(3),
            ])
            ->statePath('data');
    }

    public function loadLedger(): void
    {
        if (!$this->account_id) {
            $this->ledgerEntries = [];
            return;
        }

        $startDate = Carbon::parse($this->start_date);
        $endDate = Carbon::parse($this->end_date);

        $account = ChartOfAccount::find($this->account_id);
        if (!$account) {
            return;
        }

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

        foreach ($entries as $entry) {
            $runningBalance += ($entry->debit_minor - $entry->credit_minor);

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
                ->action(fn () => $this->loadLedger()),
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
