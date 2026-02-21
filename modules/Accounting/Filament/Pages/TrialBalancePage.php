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
use Modules\Accounting\Services\TrialBalancePdfService;
use Carbon\Carbon;

class TrialBalancePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static string $view = 'accounting::filament.pages.trial-balance';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationParentItem = 'Reports';

    protected static ?int $navigationSort = 1;

    public ?string $as_of_date = null;
    public array $trialBalance = [];
    public int $totalDebit = 0;
    public int $totalCredit = 0;

    public static function getNavigationLabel(): string
    {
        return __('accounting::accounting.trial_balance');
    }

    public function getTitle(): string
    {
        return __('accounting::accounting.trial_balance');
    }

    public function mount(): void
    {
        $this->as_of_date = now()->format('Y-m-d');
        $this->loadTrialBalance();
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
                            ->afterStateUpdated(fn () => $this->loadTrialBalance()),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function loadTrialBalance(): void
    {
        $asOfDate = Carbon::parse($this->as_of_date ?? now());

        $accounts = ChartOfAccount::where('is_active', true)
            ->orderBy('code')
            ->get();

        $this->trialBalance = [];
        $this->totalDebit = 0;
        $this->totalCredit = 0;

        foreach ($accounts as $account) {
            $balance = JournalEntryLine::where('account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                    $q->where('date', '<=', $asOfDate)
                        ->where('status', 'posted');
                })
                ->sum(DB::raw('debit_minor - credit_minor'));

            if ($balance != 0) {
                $debit = $balance > 0 ? $balance : 0;
                $credit = $balance < 0 ? abs($balance) : 0;

                $this->trialBalance[] = [
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'debit' => $debit,
                    'credit' => $credit,
                ];

                $this->totalDebit += $debit;
                $this->totalCredit += $credit;
            }
        }
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
                ->action(fn () => $this->loadTrialBalance()),
        ];
    }

    public function exportPdf()
    {
        $service = app(TrialBalancePdfService::class);
        return $service->download($this->as_of_date ?? now()->format('Y-m-d'));
    }

    protected function formatCurrency(int $amountMinor): string
    {
        return number_format($amountMinor / 100, 2) . ' EGP';
    }
}
