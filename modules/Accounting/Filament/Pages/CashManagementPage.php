<?php

namespace Modules\Accounting\Filament\Pages;

use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Set;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;
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
        return \Modules\Accounting\Filament\Pages\Concerns\ChecksAccountingPermissions::check('cash_management.view');
    }

    // Selected journal (cash or bank)
    public ?string $selected_journal_id = null;

    // The account ID derived from journal's default account
    public ?string $selected_account_id = null;

    // Journal info for display
    public ?string $journalName = null;

    public ?string $journalType = null;

    // Account info
    public int $currentBalance = 0;

    public int $todayCashIn = 0;

    public int $todayCashOut = 0;

    // Transaction form
    public string $transaction_type = 'cash_in';

    public string $entry_mode = 'partner'; // 'partner' or 'account'

    public ?string $date = null;

    public ?string $amount = null;

    public ?string $counter_account_id = null;

    public ?string $partner_id = null;

    public ?string $reference = null;

    public ?string $description = null;

    // Transaction history
    public array $recentTransactions = [];

    // Filter for transactions
    public ?string $filter_date = null;

    // Last saved entry for printing
    public ?int $lastEntryId = null;

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
        $this->filter_date = now()->format('Y-m-d');

        // Auto-select first cash/bank journal if available
        $journals = $this->getCashBankJournals();

        if ($journals->isNotEmpty()) {
            $this->selected_journal_id = (string) $journals->first()->id;
            $this->loadJournalData();
        }
    }

    /**
     * Get cash and bank journals for selection.
     */
    protected function getCashBankJournals()
    {
        return Journal::where('is_active', true)
            ->whereIn('type', [Journal::TYPE_CASH, Journal::TYPE_BANK])
            ->orderBy('type')
            ->orderBy('code')
            ->get();
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
                            ->afterStateUpdated(function () {
                                $this->counter_account_id = null;
                                $this->partner_id = null;
                            }),

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

                        Radio::make('entry_mode')
                            ->label(__('accounting::accounting.entry_mode'))
                            ->options([
                                'partner' => __('accounting::accounting.with_partner'),
                                'account' => __('accounting::accounting.with_account'),
                            ])
                            ->default('partner')
                            ->inline()
                            ->live()
                            ->afterStateUpdated(function () {
                                $this->counter_account_id = null;
                                $this->partner_id = null;
                            }),

                        // Partner selection (when entry_mode = 'partner')
                        Select::make('partner_id')
                            ->label(__('accounting::accounting.partner'))
                            ->options(function (Get $get) {
                                $transactionType = $get('transaction_type') ?? 'cash_in';

                                return app(CashManagementService::class)->getPartnerOptions($transactionType);
                            })
                            ->searchable()
                            ->required(fn (Get $get) => $get('entry_mode') === 'partner')
                            ->visible(fn (Get $get) => $get('entry_mode') === 'partner')
                            ->live()
                            ->placeholder(__('accounting::accounting.select_partner'))
                            ->helperText(fn (Get $get) => $this->getPartnerHelperText($get('transaction_type'), $get('partner_id'))),

                        // Counter account selection (when entry_mode = 'account')
                        Select::make('counter_account_id')
                            ->label(__('accounting::accounting.counter_account'))
                            ->options(function (Get $get) {
                                $type = $get('transaction_type') ?? 'cash_in';
                                $service = app(CashManagementService::class);

                                // Exclude the currently selected cash/bank account
                                $excludeId = $this->selected_account_id;

                                return $service->getCounterAccounts($type)
                                    ->reject(fn ($account) => $account->id == $excludeId)
                                    ->mapWithKeys(fn ($account) => [
                                        $account->id => "{$account->code} - {$account->translated_name}",
                                    ]);
                            })
                            ->searchable()
                            ->required(fn (Get $get) => $get('entry_mode') === 'account')
                            ->visible(fn (Get $get) => $get('entry_mode') === 'account')
                            ->placeholder(__('accounting::accounting.select_counter_account')),

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

    /**
     * Get helper text explaining which account will be used for the partner.
     */
    protected function getPartnerHelperText(?string $transactionType, ?string $partnerId): ?string
    {
        if (! $partnerId) {
            return null;
        }

        $service = app(CashManagementService::class);
        $partnerData = $service->parsePartnerKey($partnerId);

        if (! $partnerData['type']) {
            return null;
        }

        $isSupplier = str_contains($partnerData['type'], 'Supplier');

        if ($transactionType === 'cash_in') {
            if ($isSupplier) {
                return __('accounting::accounting.will_credit_payable');
            }

            return __('accounting::accounting.will_credit_receivable');
        } else {
            if ($isSupplier) {
                return __('accounting::accounting.will_debit_payable');
            }

            return __('accounting::accounting.will_debit_receivable');
        }
    }

    public function journalForm(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('selected_journal_id')
                    ->label(__('accounting::accounting.select_journal'))
                    ->options(function () {
                        return $this->getCashBankJournals()
                            ->mapWithKeys(fn (Journal $journal) => [
                                $journal->id => $this->formatJournalOption($journal),
                            ]);
                    })
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn () => $this->loadJournalData())
                    ->placeholder(__('accounting::accounting.select_journal')),
            ]);
    }

    /**
     * Format journal option with type badge.
     */
    protected function formatJournalOption(Journal $journal): string
    {
        $type = $journal->type === Journal::TYPE_CASH
            ? __('accounting::accounting.journal_types.cash')
            : __('accounting::accounting.journal_types.bank');

        return "[{$type}] {$journal->code} - {$journal->translated_name}";
    }

    protected function getForms(): array
    {
        return [
            'form',
            'journalForm',
        ];
    }

    public function loadJournalData(): void
    {
        if (! $this->selected_journal_id) {
            $this->selected_account_id = null;
            $this->journalName = null;
            $this->journalType = null;
            $this->currentBalance = 0;
            $this->todayCashIn = 0;
            $this->todayCashOut = 0;
            $this->recentTransactions = [];

            return;
        }

        $journal = Journal::find($this->selected_journal_id);

        if (! $journal) {
            return;
        }

        // Store journal info
        $this->journalName = $journal->translated_name;
        $this->journalType = $journal->type;

        // Use journal's default debit account (the cash/bank account)
        $this->selected_account_id = $journal->default_debit_account_id;

        if (! $this->selected_account_id) {
            // Fallback: try to find the first cash/bank account
            $cashAccount = ChartOfAccount::where('is_active', true)
                ->where('type', ChartOfAccount::TYPE_BANK_CASH)
                ->first();
            $this->selected_account_id = $cashAccount?->id;
        }

        if ($this->selected_account_id) {
            $service = app(CashManagementService::class);

            $this->currentBalance = $service->getAccountBalance($this->selected_account_id);

            $todayActivity = $service->getTodayActivity($this->selected_account_id);
            $this->todayCashIn = $todayActivity['cash_in'];
            $this->todayCashOut = $todayActivity['cash_out'];

            $filterDate = $this->filter_date ? Carbon::parse($this->filter_date) : null;
            $this->recentTransactions = $service->getRecentTransactions($this->selected_account_id, 50, $filterDate)->toArray();
        }

        // Reset form fields
        $this->counter_account_id = null;
        $this->partner_id = null;
    }

    /**
     * Update filter date and reload transactions.
     */
    public function updatedFilterDate(): void
    {
        $this->loadJournalData();
    }

    public function saveTransaction(): void
    {
        if (! $this->selected_journal_id || ! $this->selected_account_id) {
            Notification::make()
                ->title(__('accounting::accounting.messages.select_journal_first'))
                ->danger()
                ->send();

            return;
        }

        // Validate based on entry mode
        $rules = [
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
        ];

        if ($this->entry_mode === 'partner') {
            $rules['partner_id'] = 'required|string';
        } else {
            $rules['counter_account_id'] = 'required|exists:chart_of_accounts,id';
        }

        $this->validate($rules);

        $service = app(CashManagementService::class);

        // Convert amount to minor units (cents)
        $amountMinor = (int) round(floatval($this->amount) * 100);

        try {
            if ($this->entry_mode === 'partner') {
                // Partner mode: auto-determine counter account
                $entry = $service->recordPartnerTransaction(
                    cashAccountId: $this->selected_account_id,
                    partnerKey: $this->partner_id,
                    amountMinor: $amountMinor,
                    date: Carbon::parse($this->date),
                    isCashIn: $this->transaction_type === 'cash_in',
                    reference: $this->reference,
                    description: $this->description,
                    journalId: $this->selected_journal_id
                );
            } else {
                // Account mode: use manual counter account
                if ($this->transaction_type === 'cash_in') {
                    $entry = $service->recordCashIn(
                        cashAccountId: $this->selected_account_id,
                        counterAccountId: $this->counter_account_id,
                        amountMinor: $amountMinor,
                        date: Carbon::parse($this->date),
                        reference: $this->reference,
                        description: $this->description,
                        journalId: $this->selected_journal_id
                    );
                } else {
                    $entry = $service->recordCashOut(
                        cashAccountId: $this->selected_account_id,
                        counterAccountId: $this->counter_account_id,
                        amountMinor: $amountMinor,
                        date: Carbon::parse($this->date),
                        reference: $this->reference,
                        description: $this->description,
                        journalId: $this->selected_journal_id
                    );
                }
            }

            // Store the last entry for printing
            $this->lastEntryId = $entry->id;

            // Show success notification
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

            // Reload journal data
            $this->loadJournalData();

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
            Action::make('transfer')
                ->label(__('accounting::accounting.transfer'))
                ->icon('heroicon-o-arrows-right-left')
                ->color('warning')
                ->visible(fn () => $this->selected_journal_id !== null)
                ->form([
                    Select::make('destination_journal_id')
                        ->label(__('accounting::accounting.destination_journal'))
                        ->options(function () {
                            return $this->getCashBankJournals()
                                ->reject(fn ($journal) => $journal->id == $this->selected_journal_id)
                                ->mapWithKeys(fn (Journal $journal) => [
                                    $journal->id => $this->formatJournalOption($journal),
                                ]);
                        })
                        ->required()
                        ->searchable(),
                    Toggle::make('close_cash')
                        ->label(__('accounting::accounting.close_cash'))
                        ->helperText(__('accounting::accounting.close_cash_hint'))
                        ->live()
                        ->afterStateUpdated(function (bool $state, Set $set) {
                            if ($state) {
                                $balanceInMajor = $this->currentBalance / 100;
                                $set('transfer_amount', number_format($balanceInMajor, 2, '.', ''));
                                $set('transfer_reference', __('accounting::accounting.closing_balance_ref', [
                                    'date' => now()->format('Y-m-d'),
                                ]));
                            } else {
                                $set('transfer_amount', null);
                                $set('transfer_reference', null);
                            }
                        }),
                    TextInput::make('transfer_amount')
                        ->label(__('accounting::accounting.amount'))
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->step(0.01)
                        ->prefix('EGP')
                        ->placeholder('0.00'),
                    TextInput::make('transfer_reference')
                        ->label(__('accounting::accounting.reference'))
                        ->maxLength(255)
                        ->placeholder(__('accounting::accounting.reference_placeholder')),
                    Textarea::make('transfer_description')
                        ->label(__('accounting::accounting.description'))
                        ->rows(2)
                        ->placeholder(__('accounting::accounting.transfer_description_placeholder')),
                ])
                ->action(function (array $data): void {
                    $this->executeTransfer($data);
                }),
            Action::make('refresh')
                ->label(__('accounting::accounting.refresh'))
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->loadJournalData()),
        ];
    }

    /**
     * Execute a transfer between journals.
     */
    protected function executeTransfer(array $data): void
    {
        if (! $this->selected_journal_id || ! $this->selected_account_id) {
            Notification::make()
                ->title(__('accounting::accounting.messages.select_journal_first'))
                ->danger()
                ->send();
            return;
        }

        $destinationJournal = Journal::find($data['destination_journal_id']);
        if (! $destinationJournal || ! $destinationJournal->default_debit_account_id) {
            Notification::make()
                ->title(__('accounting::accounting.messages.destination_account_not_configured'))
                ->danger()
                ->send();
            return;
        }

        $amountMinor = (int) round(floatval($data['transfer_amount']) * 100);

        try {
            $service = app(CashManagementService::class);

            $entry = $service->recordTransfer(
                sourceAccountId: $this->selected_account_id,
                destinationAccountId: $destinationJournal->default_debit_account_id,
                amountMinor: $amountMinor,
                date: now(),
                reference: $data['transfer_reference'] ?? null,
                description: $data['transfer_description'] ?? __('accounting::accounting.transfer_between_accounts'),
                sourceJournalId: $this->selected_journal_id
            );

            Notification::make()
                ->title(__('accounting::accounting.messages.transfer_saved'))
                ->body(__('accounting::accounting.messages.entry_code', ['code' => $entry->code]))
                ->success()
                ->send();

            $this->loadJournalData();

        } catch (\Exception $e) {
            Notification::make()
                ->title(__('accounting::accounting.messages.transfer_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function formatCurrency(int $amountMinor): string
    {
        return number_format($amountMinor / 100, 2).' EGP';
    }

    /**
     * Get the last saved entry for printing.
     */
    public function getLastEntry(): ?array
    {
        if (! $this->lastEntryId) {
            return null;
        }

        $entry = \Modules\Accounting\Models\JournalEntry::with(['lines.account', 'journal'])
            ->find($this->lastEntryId);

        if (! $entry) {
            return null;
        }

        // Get the cash line (debit for cash in, credit for cash out)
        $cashLine = $entry->lines->first(fn ($line) => $line->debit_minor > 0);
        $counterLine = $entry->lines->first(fn ($line) => $line->credit_minor > 0);

        // Determine if cash in or cash out
        $isCashIn = $cashLine && $cashLine->account_id == $this->selected_account_id;

        if (! $isCashIn) {
            // Swap for cash out
            $cashLine = $entry->lines->first(fn ($line) => $line->credit_minor > 0);
            $counterLine = $entry->lines->first(fn ($line) => $line->debit_minor > 0);
        }

        $amount = $isCashIn ? ($cashLine?->debit_minor ?? 0) : ($cashLine?->credit_minor ?? 0);

        // Determine voucher type based on partner and transaction direction
        $partnerLine = $counterLine ?? $cashLine;
        $partnerType = $partnerLine?->partner_type;
        $partner = $partnerLine?->partner;

        // Get partner name based on partner type
        $partnerName = null;
        if ($partner) {
            if (method_exists($partner, 'getTranslation')) {
                // Supplier uses translated name
                $partnerName = $partner->getTranslation('name', app()->getLocale())
                    ?? $partner->getTranslation('name', 'en')
                    ?? $partner->name ?? null;
            } elseif (isset($partner->full_name)) {
                // Patient uses full_name
                $partnerName = $partner->full_name;
            } elseif (isset($partner->user)) {
                // Staff uses user->name
                $partnerName = $partner->user?->name;
            } else {
                $partnerName = $partner->name ?? null;
            }
        }

        $voucherType = $this->getVoucherTypeLabel($partnerType, $isCashIn);

        return [
            'code' => $entry->code,
            'date' => $entry->date->format('Y-m-d'),
            'type' => $voucherType,
            'amount' => $amount,
            'amount_formatted' => $this->formatCurrency($amount),
            'reference' => $entry->reference,
            'description' => $entry->description,
            'journal' => $entry->journal?->translated_name,
            'partner_name' => $partnerName,
        ];
    }

    /**
     * Get voucher type label based on partner type and transaction direction.
     */
    protected function getVoucherTypeLabel(?string $partnerType, bool $isCashIn): string
    {
        if (! $partnerType) {
            return $isCashIn
                ? __('accounting::accounting.cash_in')
                : __('accounting::accounting.cash_out');
        }

        // Determine partner category
        $isSupplier = str_contains($partnerType, 'Supplier');
        $isPatient = str_contains($partnerType, 'Patient');
        $isStaff = str_contains($partnerType, 'StaffProfile');

        if ($isSupplier) {
            return $isCashIn
                ? __('accounting::accounting.voucher_types.supplier_refund')
                : __('accounting::accounting.voucher_types.supplier_payment');
        }

        if ($isPatient) {
            return $isCashIn
                ? __('accounting::accounting.voucher_types.customer_payment')
                : __('accounting::accounting.voucher_types.customer_refund');
        }

        if ($isStaff) {
            return $isCashIn
                ? __('accounting::accounting.voucher_types.staff_repayment')
                : __('accounting::accounting.voucher_types.staff_advance');
        }

        return $isCashIn
            ? __('accounting::accounting.cash_in')
            : __('accounting::accounting.cash_out');
    }

    /**
     * Print a specific entry voucher.
     */
    public function printEntry(int $entryId): void
    {
        $this->lastEntryId = $entryId;
        $entryData = $this->getLastEntry();

        if (!$entryData) {
            Notification::make()
                ->title('Entry not found')
                ->danger()
                ->send();
            return;
        }

        $this->dispatch('print-cash-voucher', data: $entryData);
    }

    /**
     * Get journal type label for display.
     */
    public function getJournalTypeLabel(): string
    {
        if (! $this->journalType) {
            return '';
        }

        return $this->journalType === Journal::TYPE_CASH
            ? __('accounting::accounting.journal_types.cash')
            : __('accounting::accounting.journal_types.bank');
    }

    /**
     * Get journal type color for styling.
     */
    public function getJournalTypeColor(): string
    {
        if (! $this->journalType) {
            return 'gray';
        }

        return $this->journalType === Journal::TYPE_CASH ? 'info' : 'primary';
    }
}
