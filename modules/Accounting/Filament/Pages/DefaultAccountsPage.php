<?php

namespace Modules\Accounting\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Core\Models\Setting;
use Illuminate\Support\Facades\DB;

class DefaultAccountsPage extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'accounting::filament.pages.default-accounts';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 90;

    public static function canAccess(): bool
    {
        return \Modules\Accounting\Filament\Pages\Concerns\ChecksAccountingPermissions::check('chart_of_accounts.view');
    }

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('accounting::accounting.default_accounts.title');
    }

    public function getTitle(): string
    {
        return __('accounting::accounting.default_accounts.title');
    }

    public function getSubheading(): ?string
    {
        return __('accounting::accounting.default_accounts.subtitle');
    }

    public function mount(): void
    {
        $this->form->fill($this->loadSettings());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('accounting::accounting.default_accounts.receivables'))
                    ->description(__('accounting::accounting.default_accounts.receivables_description'))
                    ->icon('heroicon-o-arrow-down-on-square')
                    ->schema([
                        Forms\Components\Select::make('default_patient_receivable_account_id')
                            ->label(__('accounting::accounting.default_accounts.patient_receivable'))
                            ->helperText(__('accounting::accounting.default_accounts.patient_receivable_help'))
                            ->options(fn () => $this->getAccountOptions(ChartOfAccount::TYPE_RECEIVABLE))
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('default_supplier_receivable_account_id')
                            ->label(__('accounting::accounting.default_accounts.supplier_receivable'))
                            ->helperText(__('accounting::accounting.default_accounts.supplier_receivable_help'))
                            ->options(fn () => $this->getAccountOptions(ChartOfAccount::TYPE_RECEIVABLE))
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('default_staff_receivable_account_id')
                            ->label(__('accounting::accounting.default_accounts.staff_receivable'))
                            ->helperText(__('accounting::accounting.default_accounts.staff_receivable_help'))
                            ->options(fn () => $this->getAccountOptions(ChartOfAccount::TYPE_RECEIVABLE))
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make(__('accounting::accounting.default_accounts.payables'))
                    ->description(__('accounting::accounting.default_accounts.payables_description'))
                    ->icon('heroicon-o-arrow-up-on-square')
                    ->schema([
                        Forms\Components\Select::make('default_patient_payable_account_id')
                            ->label(__('accounting::accounting.default_accounts.patient_payable'))
                            ->helperText(__('accounting::accounting.default_accounts.patient_payable_help'))
                            ->options(fn () => $this->getAccountOptions(ChartOfAccount::TYPE_PAYABLE))
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('default_supplier_payable_account_id')
                            ->label(__('accounting::accounting.default_accounts.supplier_payable'))
                            ->helperText(__('accounting::accounting.default_accounts.supplier_payable_help'))
                            ->options(fn () => $this->getAccountOptions(ChartOfAccount::TYPE_PAYABLE))
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('default_staff_payable_account_id')
                            ->label(__('accounting::accounting.default_accounts.staff_payable'))
                            ->helperText(__('accounting::accounting.default_accounts.staff_payable_help'))
                            ->options(fn () => $this->getAccountOptions(ChartOfAccount::TYPE_PAYABLE))
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make(__('accounting::accounting.default_accounts.revenue'))
                    ->description(__('accounting::accounting.default_accounts.revenue_description'))
                    ->icon('heroicon-o-arrow-trending-up')
                    ->schema([
                        Forms\Components\Select::make('default_service_revenue_account_id')
                            ->label(__('accounting::accounting.default_accounts.service_revenue'))
                            ->helperText(__('accounting::accounting.default_accounts.service_revenue_help'))
                            ->options(fn () => $this->getAccountOptions(ChartOfAccount::TYPE_INCOME))
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('default_product_revenue_account_id')
                            ->label(__('accounting::accounting.default_accounts.product_revenue'))
                            ->helperText(__('accounting::accounting.default_accounts.product_revenue_help'))
                            ->options(fn () => $this->getAccountOptions(ChartOfAccount::TYPE_INCOME))
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('default_other_income_account_id')
                            ->label(__('accounting::accounting.default_accounts.other_income'))
                            ->helperText(__('accounting::accounting.default_accounts.other_income_help'))
                            ->options(fn () => $this->getAccountOptions(ChartOfAccount::TYPE_OTHER_INCOME))
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make(__('accounting::accounting.default_accounts.expenses'))
                    ->description(__('accounting::accounting.default_accounts.expenses_description'))
                    ->icon('heroicon-o-arrow-trending-down')
                    ->schema([
                        Forms\Components\Select::make('default_cost_of_goods_account_id')
                            ->label(__('accounting::accounting.default_accounts.cost_of_goods'))
                            ->helperText(__('accounting::accounting.default_accounts.cost_of_goods_help'))
                            ->options(fn () => $this->getAccountOptions(ChartOfAccount::TYPE_COST_OF_REVENUE))
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('default_expense_account_id')
                            ->label(__('accounting::accounting.default_accounts.general_expense'))
                            ->helperText(__('accounting::accounting.default_accounts.general_expense_help'))
                            ->options(fn () => $this->getAccountOptions(ChartOfAccount::TYPE_EXPENSE))
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('default_discount_account_id')
                            ->label(__('accounting::accounting.default_accounts.discount_expense'))
                            ->helperText(__('accounting::accounting.default_accounts.discount_expense_help'))
                            ->options(fn () => $this->getAccountOptions(ChartOfAccount::TYPE_EXPENSE))
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make(__('accounting::accounting.default_accounts.bank_cash'))
                    ->description(__('accounting::accounting.default_accounts.bank_cash_description'))
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Forms\Components\Select::make('default_cash_account_id')
                            ->label(__('accounting::accounting.default_accounts.cash_account'))
                            ->helperText(__('accounting::accounting.default_accounts.cash_account_help'))
                            ->options(fn () => $this->getAccountOptions(ChartOfAccount::TYPE_BANK_CASH))
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('default_bank_account_id')
                            ->label(__('accounting::accounting.default_accounts.bank_account'))
                            ->helperText(__('accounting::accounting.default_accounts.bank_account_help'))
                            ->options(fn () => $this->getAccountOptions(ChartOfAccount::TYPE_BANK_CASH))
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('accounting::accounting.default_accounts.tax'))
                    ->description(__('accounting::accounting.default_accounts.tax_description'))
                    ->icon('heroicon-o-receipt-percent')
                    ->schema([
                        Forms\Components\Select::make('default_tax_payable_account_id')
                            ->label(__('accounting::accounting.default_accounts.tax_payable'))
                            ->helperText(__('accounting::accounting.default_accounts.tax_payable_help'))
                            ->options(fn () => $this->getAccountOptions(ChartOfAccount::TYPE_CURRENT_LIABILITY))
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('default_tax_receivable_account_id')
                            ->label(__('accounting::accounting.default_accounts.tax_receivable'))
                            ->helperText(__('accounting::accounting.default_accounts.tax_receivable_help'))
                            ->options(fn () => $this->getAccountOptions(ChartOfAccount::TYPE_CURRENT_ASSET))
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('accounting::accounting.default_accounts.inventory'))
                    ->description(__('accounting::accounting.default_accounts.inventory_description'))
                    ->icon('heroicon-o-cube')
                    ->schema([
                        Forms\Components\Select::make('default_stock_valuation_account_id')
                            ->label(__('accounting::accounting.default_accounts.stock_valuation'))
                            ->helperText(__('accounting::accounting.default_accounts.stock_valuation_help'))
                            ->options(fn () => $this->getAccountOptions(ChartOfAccount::TYPE_CURRENT_ASSET))
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('default_stock_input_account_id')
                            ->label(__('accounting::accounting.default_accounts.stock_input'))
                            ->helperText(__('accounting::accounting.default_accounts.stock_input_help'))
                            ->options(fn () => $this->getAccountOptions(ChartOfAccount::TYPE_CURRENT_ASSET))
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('default_stock_output_account_id')
                            ->label(__('accounting::accounting.default_accounts.stock_output'))
                            ->helperText(__('accounting::accounting.default_accounts.stock_output_help'))
                            ->options(fn () => $this->getAccountOptions(ChartOfAccount::TYPE_EXPENSE))
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make(__('accounting::accounting.default_accounts.gift_cards'))
                    ->description(__('accounting::accounting.default_accounts.gift_cards_description'))
                    ->icon('heroicon-o-gift')
                    ->schema([
                        Forms\Components\Select::make('default_gift_card_liability_account_id')
                            ->label(__('accounting::accounting.default_accounts.gift_card_liability'))
                            ->helperText(__('accounting::accounting.default_accounts.gift_card_liability_help'))
                            ->options(fn () => $this->getAccountOptions(ChartOfAccount::TYPE_CURRENT_LIABILITY))
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('default_gift_card_breakage_account_id')
                            ->label(__('accounting::accounting.default_accounts.gift_card_breakage'))
                            ->helperText(__('accounting::accounting.default_accounts.gift_card_breakage_help'))
                            ->options(fn () => $this->getAccountOptions(ChartOfAccount::TYPE_INCOME))
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('accounting::accounting.default_accounts.other'))
                    ->description(__('accounting::accounting.default_accounts.other_description'))
                    ->icon('heroicon-o-ellipsis-horizontal-circle')
                    ->schema([
                        Forms\Components\Select::make('default_rounding_account_id')
                            ->label(__('accounting::accounting.default_accounts.rounding_account'))
                            ->helperText(__('accounting::accounting.default_accounts.rounding_account_help'))
                            ->options(fn () => $this->getAccountOptions(ChartOfAccount::TYPE_EXPENSE))
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('default_exchange_diff_account_id')
                            ->label(__('accounting::accounting.default_accounts.exchange_diff'))
                            ->helperText(__('accounting::accounting.default_accounts.exchange_diff_help'))
                            ->options(fn () => $this->getAccountOptions(ChartOfAccount::TYPE_EXPENSE))
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('default_retained_earnings_account_id')
                            ->label(__('accounting::accounting.default_accounts.retained_earnings'))
                            ->helperText(__('accounting::accounting.default_accounts.retained_earnings_help'))
                            ->options(fn () => $this->getAccountOptions(ChartOfAccount::TYPE_EQUITY))
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(3),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                if ($value !== null) {
                    Setting::updateOrCreate(
                        ['key' => $key],
                        [
                            'value' => $value,
                            'group' => 'accounting_defaults',
                            'type' => 'string',
                        ]
                    );
                } else {
                    // Remove setting if null
                    Setting::where('key', $key)->delete();
                }
            }
        });

        Notification::make()
            ->title(__('accounting::accounting.default_accounts.saved'))
            ->success()
            ->send();
    }

    protected function loadSettings(): array
    {
        return Setting::where('group', 'accounting_defaults')
            ->pluck('value', 'key')
            ->toArray();
    }

    protected function getAccountOptions(?string $type = null): array
    {
        return ChartOfAccount::query()
            ->when($type, fn ($q) => $q->where('type', $type))
            ->where('is_active', true)
            ->postable()
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn ($account) => [
                $account->id => "[{$account->code}] " . $account->getTranslation('name', app()->getLocale())
            ])
            ->toArray();
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label(__('accounting::accounting.default_accounts.save'))
                ->submit('save'),
        ];
    }
}
