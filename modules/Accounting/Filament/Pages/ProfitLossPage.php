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

    protected static ?string $navigationGroup = 'Finance';

    public static function getNavigationParentItem(): ?string
    {
        return __('core::core.nav_folders.financial_reports');
    }

    protected static ?int $navigationSort = 30;

    public static function canAccess(): bool
    {
        return \Modules\Accounting\Filament\Pages\Concerns\ChecksAccountingPermissions::check('profit_loss.view');
    }

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
                            ->live()
                            ->afterStateUpdated(fn () => $this->loadProfitLoss()),

                        DatePicker::make('end_date')
                            ->label(__('accounting::accounting.end_date'))
                            ->live()
                            ->afterStateUpdated(fn () => $this->loadProfitLoss()),
                    ])
                    ->columns(2),
            ]);
    }

    public function loadProfitLoss(): void
    {
        $startDate = Carbon::parse($this->start_date);
        $endDate = Carbon::parse($this->end_date);

        // Get income account types from TYPE_CATEGORY
        $incomeTypes = array_keys(array_filter(
            ChartOfAccount::TYPE_CATEGORY,
            fn ($cat) => $cat === 'income'
        ));

        // Get expense account types from TYPE_CATEGORY
        $expenseTypes = array_keys(array_filter(
            ChartOfAccount::TYPE_CATEGORY,
            fn ($cat) => $cat === 'expense'
        ));

        // Load revenues grouped by type
        $this->revenues = $this->getGroupedAccountBalances($incomeTypes, $startDate, $endDate, 'credit');
        $this->totalRevenue = array_sum(array_column($this->revenues, 'subtotal'));

        // Load expenses grouped by type
        $this->expenses = $this->getGroupedAccountBalances($expenseTypes, $startDate, $endDate, 'debit');
        $this->totalExpenses = array_sum(array_column($this->expenses, 'subtotal'));

        $this->netIncome = $this->totalRevenue - $this->totalExpenses;
    }

    protected function getGroupedAccountBalances(array $types, Carbon $startDate, Carbon $endDate, string $normalBalance): array
    {
        $accounts = ChartOfAccount::whereIn('type', $types)
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('code')
            ->get();

        $groupedBalances = [];

        foreach ($accounts as $account) {
            // Calculate balance based on normal balance type
            if ($normalBalance === 'credit') {
                $balance = JournalEntryLine::where('account_id', $account->id)
                    ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('date', [$startDate, $endDate])
                            ->where('status', 'posted');
                    })
                    ->sum(DB::raw('credit_minor - debit_minor'));
            } else {
                $balance = JournalEntryLine::where('account_id', $account->id)
                    ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('date', [$startDate, $endDate])
                            ->where('status', 'posted');
                    })
                    ->sum(DB::raw('debit_minor - credit_minor'));
            }

            if ($balance != 0) {
                $type = $account->type;
                $typeLabel = ChartOfAccount::TYPES_FLAT[$type] ?? $type;

                if (!isset($groupedBalances[$type])) {
                    $groupedBalances[$type] = [
                        'type' => $type,
                        'type_label' => __('accounting::accounting.account_types.' . $type, [], app()->getLocale()) !== 'accounting::accounting.account_types.' . $type
                            ? __('accounting::accounting.account_types.' . $type)
                            : $typeLabel,
                        'accounts' => [],
                        'subtotal' => 0,
                    ];
                }

                $groupedBalances[$type]['accounts'][] = [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->getTranslation('name', app()->getLocale()) ?? $account->name,
                    'amount' => $balance,
                ];
                $groupedBalances[$type]['subtotal'] += $balance;
            }
        }

        return array_values($groupedBalances);
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

    public function openGeneralLedger($accountId): void
    {
        $this->redirect(GeneralLedgerPage::getUrl([
            'account_id' => $accountId,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
        ]));
    }
}
