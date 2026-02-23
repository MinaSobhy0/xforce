<?php

namespace Modules\Inventory\Filament\Resources\VendorBillResource\Pages;

use Modules\Inventory\Filament\Resources\VendorBillResource;
use Modules\Inventory\Models\VendorBill;
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
    protected static string $resource = VendorBillResource::class;

    protected static string $view = 'inventory::filament.pages.record-payment';

    public VendorBill $record;

    public ?array $data = [];

    public function mount(VendorBill $record): void
    {
        $this->record = $record;

        if (!$record->canRecordPayment()) {
            Notification::make()
                ->title('Cannot record payment')
                ->body('This vendor bill does not accept payments.')
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
        return __('inventory::inventory.actions.record_payment') . ' - ' . $this->record->code;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('inventory::inventory.sections.bill_summary'))
                    ->schema([
                        Forms\Components\Placeholder::make('bill_code')
                            ->label(__('inventory::inventory.fields.code'))
                            ->content($this->record->code),

                        Forms\Components\Placeholder::make('supplier')
                            ->label(__('inventory::inventory.fields.supplier'))
                            ->content($this->record->supplier?->name),

                        Forms\Components\Placeholder::make('total')
                            ->label(__('inventory::inventory.fields.total'))
                            ->content(format_money($this->record->total_minor)),

                        Forms\Components\Placeholder::make('paid')
                            ->label(__('inventory::inventory.fields.paid'))
                            ->content(format_money($this->record->paid_minor)),

                        Forms\Components\Placeholder::make('remaining')
                            ->label(__('inventory::inventory.fields.remaining'))
                            ->content(format_money($this->record->remaining_minor)),
                    ])
                    ->columns(5),

                Forms\Components\Section::make(__('inventory::inventory.sections.payment_details'))
                    ->schema([
                        Forms\Components\TextInput::make('amount_minor')
                            ->label(__('inventory::inventory.fields.payment_amount'))
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->maxValue($this->record->remaining_minor / 100)
                            ->prefix(current_currency())
                            ->helperText(__('inventory::inventory.help.max_payment', ['amount' => format_money($this->record->remaining_minor)])),

                        Forms\Components\Select::make('journal_id')
                            ->label(__('inventory::inventory.fields.payment_method'))
                            ->options(fn () => Journal::active()
                                ->whereIn('type', ['cash', 'bank'])
                                ->get()
                                ->pluck('display_name', 'id'))
                            ->required()
                            ->native(false)
                            ->searchable(),

                        Forms\Components\DateTimePicker::make('paid_at')
                            ->label(__('inventory::inventory.fields.payment_date'))
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('reference_number')
                            ->label(__('inventory::inventory.fields.reference_number'))
                            ->maxLength(255)
                            ->helperText(__('inventory::inventory.help.reference_number')),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('inventory::inventory.fields.notes'))
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

        // Create payment record
        $payment = Payment::create([
            'type' => Payment::TYPE_SEND,
            'vendor_bill_id' => $this->record->id,
            'supplier_id' => $this->record->supplier_id,
            'branch_id' => $this->record->branch_id,
            'journal_id' => $data['journal_id'],
            'amount_minor' => $amountMinor,
            'paid_at' => $data['paid_at'],
            'reference_number' => $data['reference_number'] ?? null,
            'notes' => $data['notes'] ?? null,
            'received_by_user_id' => auth()->id(),
        ]);

        Notification::make()
            ->title(__('inventory::inventory.messages.payment_recorded'))
            ->body(__('inventory::inventory.messages.payment_amount', ['amount' => format_money($amountMinor)]))
            ->success()
            ->send();

        $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label(__('inventory::inventory.actions.record_payment'))
                ->submit('save'),

            Actions\Action::make('cancel')
                ->label(__('core::core.cancel'))
                ->url($this->getResource()::getUrl('view', ['record' => $this->record]))
                ->color('gray'),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            $this->getResource()::getUrl() => __('inventory::inventory.navigation.vendor_bills'),
            $this->getResource()::getUrl('view', ['record' => $this->record]) => $this->record->code,
            __('inventory::inventory.actions.record_payment'),
        ];
    }
}
