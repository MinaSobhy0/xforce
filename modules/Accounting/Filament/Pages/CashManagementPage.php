<?php

namespace Modules\Accounting\Filament\Pages;

use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Services\CashManagementService;

class CashManagementPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static string $view = 'accounting::filament.pages.cash-management';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return \Modules\Accounting\Filament\Pages\Concerns\ChecksAccountingPermissions::check('journal_entries.view');
    }

    // Selected account
    public ?string $selected_account_id = null;

    // Account info
    public int $currentBalance = 0;
    public int $todayCashIn = 0;
    public int $todayCashOut = 0;

    // Transaction form
    public string $transaction_type = 'cash_in';
    public ?string $date = null;
    public ?string $amount = null;
    public ?string $counter_account_id = null;
    public ?string $partner_id = null;
    public ?string $reference = null;
    public ?string $description = null;

    // Transaction history
    public array $recentTransactions = [];

    public static function getNavigationLabel(): string
    {
        return __('accounting::accounting.cash_management');
    }

    public function getTitle(): string
    {
        return __('accounting::accounting.cash_management');
    }

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');

        // Auto-select first cash/bank account if available
        $service = app(CashManagementService::class);
        $accounts = $service->getCashBankAccounts();

        if ($accounts->isNotEmpty()) {
            $this->selected_account_id = $accounts->first()->id;
            $this->loadAccountData();
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('accounting::accounting.transaction_form'))
                    ->schema([
                        Radio::make('transaction_type')
                            ->label(__('accounting::accounting.transaction_type'))
                            ->options([
                                'cash_in' => __('accounting::accounting.cash_in'),
                                'cash_out' => __('accounting::accounting.cash_out'),
                            ])
                            ->default('cash_in')
                            ->inline()
                            ->live()
                            ->afterStateUpdated(fn() => $this->counter_account_id = null),

                        DatePicker::make('date')
                            ->label(__('accounting::accounting.date'))
                            ->default(now())
                            ->required(),

                        TextInput::make('amount')
                            ->label(__('accounting::accounting.amount'))
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->prefix('EGP')
                            ->placeholder('0.00'),

                        Select::make('counter_account_id')
                            ->label(__('accounting::accounting.counter_account'))
                            ->options(function (Get $get) {
                                $type = $get('transaction_type') ?? 'cash_in';
                                $service = app(CashManagementService::class);

                                // Exclude the currently selected cash account
                                $excludeId = $this->selected_account_id;

                                return $service->getCounterAccounts($type)
                                    ->reject(fn($account) => $account->id === $excludeId)
                                    ->mapWithKeys(fn($account) => [
                                        $account->id => "{$account->code} - {$account->translated_name}"
                                    ]);
                            })
                            ->searchable()
                            ->required()
                            ->placeholder(__('accounting::accounting.select_counter_account')),

                        Select::make('partner_id')
                            ->label(__('accounting::accounting.partner'))
                            ->options(fn() => app(CashManagementService::class)->getPartnerOptions())
                            ->searchable()
                            ->placeholder(__('accounting::accounting.optional_partner')),

                        TextInput::make('reference')
                            ->label(__('accounting::accounting.reference'))
                            ->maxLength(255)
                            ->placeholder(__('accounting::accounting.reference_placeholder')),

                        Textarea::make('description')
                            ->label(__('accounting::accounting.description'))
                            ->rows(2)
                            ->placeholder(__('accounting::accounting.description_placeholder')),
                    ])
                    ->columns(2),
            ]);
    }

    public function accountForm(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('selected_account_id')
                    ->label(__('accounting::accounting.select_account'))
                    ->options(function () {
                        $service = app(CashManagementService::class);
                        return $service->getCashBankAccounts()
                            ->mapWithKeys(fn($account) => [
                                $account->id => "{$account->code} - {$account->translated_name}"
                            ]);
                    })
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn() => $this->loadAccountData())
                    ->placeholder(__('accounting::accounting.select_account')),
            ]);
    }

    protected function getForms(): array
    {
        return [
            'form',
            'accountForm',
        ];
    }

    public function loadAccountData(): void
    {
        if (!$this->selected_account_id) {
            $this->currentBalance = 0;
            $this->todayCashIn = 0;
            $this->todayCashOut = 0;
            $this->recentTransactions = [];
            return;
        }

        $service = app(CashManagementService::class);

        $this->currentBalance = $service->getAccountBalance($this->selected_account_id);

        $todayActivity = $service->getTodayActivity($this->selected_account_id);
        $this->todayCashIn = $todayActivity['cash_in'];
        $this->todayCashOut = $todayActivity['cash_out'];

        $this->recentTransactions = $service->getRecentTransactions($this->selected_account_id, 20)->toArray();

        // Reset form fields
        $this->counter_account_id = null;
    }

    public function saveTransaction(): void
    {
        if (!$this->selected_account_id) {
            Notification::make()
                ->title(__('accounting::accounting.messages.select_account_first'))
                ->danger()
                ->send();
            return;
        }

        $this->validate([
            'amount' => 'required|numeric|min:0.01',
            'counter_account_id' => 'required|exists:chart_of_accounts,id',
            'date' => 'required|date',
        ]);

        $service = app(CashManagementService::class);

        // Convert amount to minor units (cents)
        $amountMinor = (int) round(floatval($this->amount) * 100);

        // Parse partner
        $partnerData = $service->parsePartnerKey($this->partner_id);

        try {
            if ($this->transaction_type === 'cash_in') {
                $entry = $service->recordCashIn(
                    cashAccountId: $this->selected_account_id,
                    counterAccountId: $this->counter_account_id,
                    amountMinor: $amountMinor,
                    date: Carbon::parse($this->date),
                    partnerId: $partnerData['id'],
                    partnerType: $partnerData['type'],
                    reference: $this->reference,
                    description: $this->description
                );
            } else {
                $entry = $service->recordCashOut(
                    cashAccountId: $this->selected_account_id,
                    counterAccountId: $this->counter_account_id,
                    amountMinor: $amountMinor,
                    date: Carbon::parse($this->date),
                    partnerId: $partnerData['id'],
                    partnerType: $partnerData['type'],
                    reference: $this->reference,
                    description: $this->description
                );
            }

            Notification::make()
                ->title(__('accounting::accounting.messages.transaction_saved'))
                ->body(__('accounting::accounting.messages.entry_code', ['code' => $entry->code]))
                ->success()
                ->send();

            // Reset form
            $this->amount = null;
            $this->counter_account_id = null;
            $this->partner_id = null;
            $this->reference = null;
            $this->description = null;
            $this->date = now()->format('Y-m-d');

            // Reload account data
            $this->loadAccountData();

        } catch (\Exception $e) {
            Notification::make()
                ->title(__('accounting::accounting.messages.transaction_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label(__('accounting::accounting.refresh'))
                ->icon('heroicon-o-arrow-path')
                ->action(fn() => $this->loadAccountData()),
        ];
    }

    public function formatCurrency(int $amountMinor): string
    {
        return number_format($amountMinor / 100, 2) . ' EGP';
    }
}
