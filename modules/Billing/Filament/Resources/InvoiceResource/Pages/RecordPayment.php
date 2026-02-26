<?php

namespace Modules\Billing\Filament\Resources\InvoiceResource\Pages;

use Modules\Billing\Filament\Resources\InvoiceResource;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Accounting\Models\Journal;
use Modules\GiftCards\Models\GiftCard;
use Modules\GiftCards\Services\GiftCardService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
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
                        Forms\Components\Select::make('journal_id')
                            ->label(__('billing::billing.fields.payment_method'))
                            ->options(fn () => Journal::active()
                                ->whereIn('type', [Journal::TYPE_CASH, Journal::TYPE_BANK, Journal::TYPE_GIFT_CARD])
                                ->get()
                                ->pluck('display_name', 'id'))
                            ->required()
                            ->native(false)
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                $journal = Journal::find($state);
                                if ($journal?->type === Journal::TYPE_GIFT_CARD) {
                                    $set('gift_card_id', null);
                                    $set('amount_minor', null);
                                } else {
                                    $set('gift_card_id', null);
                                    $set('gift_card_code', null);
                                }
                            }),

                        // Gift card code input (only for gift card payment)
                        Forms\Components\TextInput::make('gift_card_code')
                            ->label(__('billing::billing.record_payment.gift_card_code'))
                            ->placeholder(__('billing::billing.record_payment.enter_gift_card_code'))
                            ->visible(fn (Get $get) => $this->isGiftCardJournal($get('journal_id')))
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                if (empty($state)) {
                                    $set('gift_card_id', null);
                                    $set('amount_minor', null);
                                    return;
                                }

                                $giftCardService = app(GiftCardService::class);
                                $validation = $giftCardService->validateForPayment($state);

                                if (!$validation['valid']) {
                                    Notification::make()
                                        ->title($validation['error'])
                                        ->danger()
                                        ->send();
                                    $set('gift_card_id', null);
                                    $set('amount_minor', null);
                                    return;
                                }

                                $card = $validation['card'];
                                $set('gift_card_id', $card->id);

                                // Set amount to minimum of card balance and invoice remaining
                                $maxAmount = min($card->remaining_value_minor, $this->record->remaining_minor);
                                $set('amount_minor', $maxAmount / 100);
                            }),

                        // Gift card info placeholder
                        Forms\Components\Placeholder::make('gift_card_info')
                            ->label(__('billing::billing.record_payment.gift_card_balance'))
                            ->content(function (Get $get) {
                                $cardId = $get('gift_card_id');
                                if (!$cardId) {
                                    return '-';
                                }
                                $card = GiftCard::find($cardId);
                                if (!$card) {
                                    return '-';
                                }
                                return format_money($card->remaining_value_minor) . ' (' . $card->code . ')';
                            })
                            ->visible(fn (Get $get) => $this->isGiftCardJournal($get('journal_id')) && $get('gift_card_id')),

                        Forms\Components\Hidden::make('gift_card_id'),

                        Forms\Components\TextInput::make('amount_minor')
                            ->label(__('billing::billing.record_payment.payment_amount'))
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->maxValue(function (Get $get) {
                                if ($this->isGiftCardJournal($get('journal_id')) && $get('gift_card_id')) {
                                    $card = GiftCard::find($get('gift_card_id'));
                                    if ($card) {
                                        return min($card->remaining_value_minor, $this->record->remaining_minor) / 100;
                                    }
                                }
                                return $this->record->remaining_minor / 100;
                            })
                            ->prefix(current_currency())
                            ->helperText(function (Get $get) {
                                if ($this->isGiftCardJournal($get('journal_id')) && $get('gift_card_id')) {
                                    $card = GiftCard::find($get('gift_card_id'));
                                    if ($card) {
                                        $maxAmount = min($card->remaining_value_minor, $this->record->remaining_minor);
                                        return __('billing::billing.record_payment.max_from_gift_card', ['amount' => format_money($maxAmount)]);
                                    }
                                }
                                return __('billing::billing.record_payment.max_amount', ['amount' => format_money($this->record->remaining_minor)]);
                            }),

                        Forms\Components\DateTimePicker::make('paid_at')
                            ->label(__('billing::billing.fields.paid_at'))
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('reference_number')
                            ->label(__('billing::billing.fields.reference'))
                            ->maxLength(255)
                            ->helperText(__('billing::billing.record_payment.reference_help'))
                            ->visible(fn (Get $get) => !$this->isGiftCardJournal($get('journal_id'))),

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

        $journal = Journal::find($data['journal_id']);
        $isGiftCard = $journal?->type === Journal::TYPE_GIFT_CARD;

        // For gift card payments, validate and redeem from the card
        if ($isGiftCard) {
            if (empty($data['gift_card_id'])) {
                Notification::make()
                    ->title(__('billing::billing.record_payment.select_gift_card'))
                    ->danger()
                    ->send();
                return;
            }

            $card = GiftCard::find($data['gift_card_id']);
            if (!$card || !$card->canRedeem()) {
                Notification::make()
                    ->title(__('billing::billing.record_payment.invalid_gift_card'))
                    ->danger()
                    ->send();
                return;
            }

            if ($amountMinor > $card->remaining_value_minor) {
                Notification::make()
                    ->title(__('billing::billing.record_payment.amount_exceeds_balance'))
                    ->danger()
                    ->send();
                return;
            }

            // Create payment first
            $payment = Payment::create([
                'invoice_id' => $this->record->id,
                'journal_id' => $data['journal_id'],
                'amount_minor' => $amountMinor,
                'paid_at' => $data['paid_at'],
                'reference_number' => $card->code,
                'gift_card_id' => $card->id,
                'notes' => $data['notes'] ?? __('giftcards::giftcards.redemption_for_invoice', ['invoice' => $this->record->code]),
                'received_by_user_id' => auth()->id(),
            ]);

            // Redeem from gift card (this handles GL entry)
            $giftCardService = app(GiftCardService::class);
            $result = $giftCardService->redeem($card, $amountMinor, $this->record, $payment);

            if (!$result['success']) {
                // Payment was created but redemption failed - this shouldn't happen
                // but we log it for debugging
                \Log::error('Gift card redemption failed after payment created', [
                    'payment_id' => $payment->id,
                    'card_id' => $card->id,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
            }

            Notification::make()
                ->title(__('billing::billing.record_payment.payment_recorded'))
                ->body(__('billing::billing.record_payment.gift_card_redeemed', [
                    'amount' => format_money($amountMinor),
                    'remaining' => format_money($result['remaining_balance'] ?? 0),
                ]))
                ->success()
                ->send();
        } else {
            // Regular payment (cash/bank)
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
        }

        $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
    }

    protected function isGiftCardJournal(?string $journalId): bool
    {
        if (!$journalId) {
            return false;
        }
        $journal = Journal::find($journalId);
        return $journal?->type === Journal::TYPE_GIFT_CARD;
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
