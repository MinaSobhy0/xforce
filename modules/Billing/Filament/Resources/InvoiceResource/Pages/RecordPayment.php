<?php

namespace Modules\Billing\Filament\Resources\InvoiceResource\Pages;

use Modules\Billing\Filament\Resources\InvoiceResource;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Accounting\Models\Journal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\Page;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;

class RecordPayment extends Page
{
    protected static string $resource = InvoiceResource::class;

    protected static string $view = 'billing::filament.pages.record-payment';

    public Invoice $record;

    public ?array $data = [];

    public function mount(Invoice $record): void
    {
        $this->record = $record;

        if (!$record->canRecordPayment()) {
            Notification::make()
                ->title(__('billing::billing.record_payment.cannot_record'))
                ->body(__('billing::billing.record_payment.cannot_record_body'))
                ->warning()
                ->send();

            $this->redirect($this->getResource()::getUrl('view', ['record' => $record]));
            return;
        }

        // Get default cash journal
        $cashJournal = Journal::getCashJournal();

        $this->form->fill([
            'amount_minor' => $record->remaining_minor / 100,
            'journal_id' => $cashJournal?->id,
            'paid_at' => now(),
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return __('billing::billing.record_payment.title', ['code' => $this->record->code]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('billing::billing.record_payment.invoice_summary'))
                    ->schema([
                        Forms\Components\Placeholder::make('invoice_code')
                            ->label(__('billing::billing.invoice'))
                            ->content($this->record->code),

                        Forms\Components\Placeholder::make('patient')
                            ->label(__('billing::billing.fields.patient'))
                            ->content($this->record->patient?->full_name),

                        Forms\Components\Placeholder::make('total')
                            ->label(__('billing::billing.record_payment.total_amount'))
                            ->content(format_money($this->record->total_minor)),

                        Forms\Components\Placeholder::make('paid')
                            ->label(__('billing::billing.record_payment.already_paid'))
                            ->content(format_money($this->record->paid_minor)),

                        Forms\Components\Placeholder::make('remaining')
                            ->label(__('billing::billing.fields.remaining'))
                            ->content(format_money($this->record->remaining_minor)),
                    ])
                    ->columns(5),

                Forms\Components\Section::make(__('billing::billing.sections.payment_details'))
                    ->schema([
                        Forms\Components\TextInput::make('amount_minor')
                            ->label(__('billing::billing.record_payment.payment_amount'))
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->maxValue($this->record->remaining_minor / 100)
                            ->prefix(current_currency())
                            ->helperText(__('billing::billing.record_payment.max_amount', ['amount' => format_money($this->record->remaining_minor)])),

                        Forms\Components\Select::make('journal_id')
                            ->label(__('billing::billing.fields.payment_method'))
                            ->options(fn () => Journal::active()
                                ->whereIn('type', ['cash', 'bank'])
                                ->get()
                                ->pluck('display_name', 'id'))
                            ->required()
                            ->native(false)
                            ->searchable(),

                        Forms\Components\DateTimePicker::make('paid_at')
                            ->label(__('billing::billing.fields.paid_at'))
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('reference_number')
                            ->label(__('billing::billing.fields.reference'))
                            ->maxLength(255)
                            ->helperText(__('billing::billing.record_payment.reference_help')),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('billing::billing.fields.notes'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Convert amount to minor units
        $amountMinor = (int) ($data['amount_minor'] * 100);

        // Create payment
        $payment = Payment::create([
            'invoice_id' => $this->record->id,
            'journal_id' => $data['journal_id'],
            'amount_minor' => $amountMinor,
            'paid_at' => $data['paid_at'],
            'reference_number' => $data['reference_number'] ?? null,
            'notes' => $data['notes'] ?? null,
            'received_by_user_id' => auth()->id(),
        ]);

        Notification::make()
            ->title(__('billing::billing.record_payment.payment_recorded'))
            ->body(__('billing::billing.record_payment.amount_label', ['amount' => format_money($amountMinor)]))
            ->success()
            ->send();

        $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label(__('billing::billing.actions.record_payment'))
                ->submit('save'),

            Actions\Action::make('cancel')
                ->label(__('billing::billing.actions.cancel_short'))
                ->url($this->getResource()::getUrl('view', ['record' => $this->record]))
                ->color('gray'),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            $this->getResource()::getUrl() => __('billing::billing.invoices'),
            $this->getResource()::getUrl('view', ['record' => $this->record]) => $this->record->code,
            __('billing::billing.actions.record_payment'),
        ];
    }
}
