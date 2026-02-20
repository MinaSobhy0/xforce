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
                ->title('Cannot record payment')
                ->body('This invoice does not accept payments.')
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
        return 'Record Payment for ' . $this->record->code;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Invoice Summary')
                    ->schema([
                        Forms\Components\Placeholder::make('invoice_code')
                            ->label('Invoice')
                            ->content($this->record->code),

                        Forms\Components\Placeholder::make('patient')
                            ->label('Patient')
                            ->content($this->record->patient?->full_name),

                        Forms\Components\Placeholder::make('total')
                            ->label('Total Amount')
                            ->content(number_format($this->record->total_minor / 100, 2) . ' ' . config('app.currency_symbol', 'EGP')),

                        Forms\Components\Placeholder::make('paid')
                            ->label('Already Paid')
                            ->content(number_format($this->record->paid_minor / 100, 2) . ' ' . config('app.currency_symbol', 'EGP')),

                        Forms\Components\Placeholder::make('remaining')
                            ->label('Remaining')
                            ->content(number_format($this->record->remaining_minor / 100, 2) . ' ' . config('app.currency_symbol', 'EGP')),
                    ])
                    ->columns(5),

                Forms\Components\Section::make('Payment Details')
                    ->schema([
                        Forms\Components\TextInput::make('amount_minor')
                            ->label('Payment Amount')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->maxValue($this->record->remaining_minor / 100)
                            ->prefix(config('app.currency_symbol', 'EGP'))
                            ->helperText('Maximum: ' . number_format($this->record->remaining_minor / 100, 2)),

                        Forms\Components\Select::make('journal_id')
                            ->label('Payment Method')
                            ->options(fn () => Journal::active()
                                ->whereIn('type', ['cash', 'bank'])
                                ->get()
                                ->pluck('display_name', 'id'))
                            ->required()
                            ->native(false)
                            ->searchable(),

                        Forms\Components\DateTimePicker::make('paid_at')
                            ->label('Payment Date/Time')
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('reference_number')
                            ->label('Reference Number')
                            ->maxLength(255)
                            ->helperText('Card last 4 digits, transfer reference, etc.'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
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
            ->title('Payment recorded successfully')
            ->body('Amount: ' . number_format($amountMinor / 100, 2) . ' ' . config('app.currency_symbol', 'EGP'))
            ->success()
            ->send();

        $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label('Record Payment')
                ->submit('save'),

            Actions\Action::make('cancel')
                ->label('Cancel')
                ->url($this->getResource()::getUrl('view', ['record' => $this->record]))
                ->color('gray'),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            $this->getResource()::getUrl() => 'Invoices',
            $this->getResource()::getUrl('view', ['record' => $this->record]) => $this->record->code,
            'Record Payment',
        ];
    }
}
