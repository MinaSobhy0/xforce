<?php

namespace Modules\Accounting\Filament\Pages;

use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Services\PartnerLedgerPdfService;
use Modules\Accounting\Services\PartnerLedgerService;

class PartnerLedgerPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static string $view = 'accounting::filament.pages.partner-ledger';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 25;

    // Filter properties
    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?string $partner_type = null;
    public ?string $partner_id = null;
    public ?string $account_id = null;
    public bool $show_zero_balances = false;

    // Report data
    public array $partnerData = [];
    public array $stats = [];

    public static function getNavigationLabel(): string
    {
        return __('accounting::accounting.partner_ledger');
    }

    public function getTitle(): string
    {
        return __('accounting::accounting.partner_ledger');
    }

    public function mount(): void
    {
        $this->start_date = now()->startOfMonth()->format('Y-m-d');
        $this->end_date = now()->format('Y-m-d');
        $this->loadReportData();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('accounting::accounting.filters'))
                    ->schema([
                        DatePicker::make('start_date')
                            ->label(__('accounting::accounting.start_date'))
                            ->reactive()
                            ->afterStateUpdated(fn() => $this->loadReportData()),

                        DatePicker::make('end_date')
                            ->label(__('accounting::accounting.end_date'))
                            ->reactive()
                            ->afterStateUpdated(fn() => $this->loadReportData()),

                        Select::make('partner_type')
                            ->label(__('accounting::accounting.partner_type'))
                            ->options([
                                'customer' => __('accounting::accounting.customers'),
                                'supplier' => __('accounting::accounting.suppliers'),
                                'staff' => __('accounting::accounting.staff'),
                            ])
                            ->placeholder(__('accounting::accounting.all_partners'))
                            ->reactive()
                            ->afterStateUpdated(function () {
                                $this->partner_id = null;
                                $this->loadReportData();
                            }),

                        Select::make('partner_id')
                            ->label(__('accounting::accounting.partner'))
                            ->options(fn() => $this->getPartnerOptions())
                            ->searchable()
                            ->placeholder(__('accounting::accounting.all'))
                            ->reactive()
                            ->afterStateUpdated(fn() => $this->loadReportData())
                            ->visible(fn() => !empty($this->partner_type)),

                        Select::make('account_id')
                            ->label(__('accounting::accounting.account'))
                            ->options(
                                ChartOfAccount::where('is_active', true)
                                    ->whereIn('type', ['asset', 'liability']) // Receivable/Payable accounts
                                    ->orderBy('code')
                                    ->get()
                                    ->mapWithKeys(fn($a) => [$a->id => "{$a->code} - {$a->name}"])
                            )
                            ->searchable()
                            ->placeholder(__('accounting::accounting.all_accounts'))
                            ->reactive()
                            ->afterStateUpdated(fn() => $this->loadReportData()),

                        Toggle::make('show_zero_balances')
                            ->label(__('accounting::accounting.show_zero_balances'))
                            ->reactive()
                            ->afterStateUpdated(fn() => $this->loadReportData()),
                    ])
                    ->columns(6),
            ])
            ->statePath('data');
    }

    protected function loadReportData(): void
    {
        $service = app(PartnerLedgerService::class);

        $partnerData = $service->getPartnerLedger(
            Carbon::parse($this->start_date),
            Carbon::parse($this->end_date),
            $this->partner_type,
            $this->partner_id,
            $this->account_id,
            $this->show_zero_balances
        );

        $this->partnerData = $partnerData->toArray();
        $this->stats = $service->getStats($partnerData);
    }

    protected function getPartnerOptions(): array
    {
        $service = app(PartnerLedgerService::class);
        return $service->getPartnerOptions($this->partner_type);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_pdf')
                ->label(__('accounting::accounting.export_pdf'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(fn() => $this->exportPdf()),

            Action::make('refresh')
                ->label(__('accounting::accounting.refresh'))
                ->icon('heroicon-o-arrow-path')
                ->action(fn() => $this->loadReportData()),
        ];
    }

    public function exportPdf()
    {
        return app(PartnerLedgerPdfService::class)->download(
            $this->start_date,
            $this->end_date,
            $this->partner_type,
            $this->partner_id,
            $this->account_id,
            $this->show_zero_balances
        );
    }

    protected function formatCurrency(int $amountMinor): string
    {
        return number_format($amountMinor / 100, 2) . ' EGP';
    }
}
