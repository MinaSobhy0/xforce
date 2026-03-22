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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

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

        // SECURITY: Idempotency protection - prevent duplicate payments from double-clicks
        $idempotencyKey = "payment_vendorbill_{$this->record->id}_" . auth()->id() . '_' . md5(serialize($data));
        $lockKey = "payment_lock_vendorbill_{$this->record->id}";

        // Check if this exact payment was already processed (5 minute window)
        if (Cache::has($idempotencyKey)) {
            Notification::make()
                ->title(__('inventory::inventory.messages.duplicate_prevented'))
                ->body(__('inventory::inventory.messages.duplicate_prevented_body'))
                ->warning()
                ->send();
            $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
            return;
        }

        // Use atomic lock to prevent race conditions
        $lock = Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            Notification::make()
                ->title(__('inventory::inventory.messages.payment_in_progress'))
                ->body(__('inventory::inventory.messages.please_wait'))
                ->warning()
                ->send();
            return;
        }

        try {
            DB::transaction(function () use ($data, $amountMinor, $idempotencyKey) {
                // Re-fetch vendor bill with lock to ensure accurate remaining balance
                $bill = VendorBill::lockForUpdate()->find($this->record->id);

                // Verify payment is still valid
                if (!$bill->canRecordPayment()) {
                    throw new \Exception(__('inventory::inventory.messages.cannot_record_payment'));
                }

                if ($amountMinor > $bill->remaining_minor) {
                    throw new \Exception(__('inventory::inventory.messages.amount_exceeds_remaining'));
                }

                // Create payment record
                Payment::create([
                    'type' => Payment::TYPE_SEND,
                    'vendor_bill_id' => $bill->id,
                    'supplier_id' => $bill->supplier_id,
                    'branch_id' => $bill->branch_id,
                    'journal_id' => $data['journal_id'],
                    'amount_minor' => $amountMinor,
                    'paid_at' => $data['paid_at'],
                    'reference_number' => $data['reference_number'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'received_by_user_id' => auth()->id(),
                ]);

                // Mark as processed to prevent duplicates
                Cache::put($idempotencyKey, true, now()->addMinutes(5));

                Notification::make()
                    ->title(__('inventory::inventory.messages.payment_recorded'))
                    ->body(__('inventory::inventory.messages.payment_amount', ['amount' => format_money($amountMinor)]))
                    ->success()
                    ->send();
            });
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('inventory::inventory.messages.payment_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
            return;
        } finally {
            $lock->release();
        }

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
