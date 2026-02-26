<?php

namespace Modules\Booking\Filament\Resources\AppointmentResource\Pages;

use Modules\Booking\Filament\Resources\AppointmentResource;
use Modules\Booking\Models\Appointment;
use Modules\Billing\Models\Payment;
use Modules\Accounting\Models\Journal;
use Modules\GiftCards\Models\GiftCard;
use Modules\GiftCards\Services\GiftCardService;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use App\Filament\Resources\Pages\BaseViewRecord;

class ViewAppointment extends BaseViewRecord
{
    protected static string $resource = AppointmentResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make('record_payment')
                ->label(__('billing::billing.actions.record_payment'))
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn (): bool => $this->canRecordPayment())
                ->form([
                    Forms\Components\Placeholder::make('appointment_info')
                        ->label(__('booking::appointments.singular'))
                        ->content(fn () => "{$this->record->code} - {$this->record->patient?->full_name}"),

                    Forms\Components\Placeholder::make('amount_due')
                        ->label(__('booking::appointments.fields.net_price'))
                        ->content(fn () => format_money($this->record->net_price)),

                    Forms\Components\Placeholder::make('paid_amount')
                        ->label(__('billing::billing.fields.paid'))
                        ->content(fn () => format_money($this->getAppointmentPaidAmount())),

                    Forms\Components\Placeholder::make('remaining_amount')
                        ->label(__('billing::billing.fields.remaining'))
                        ->content(fn () => format_money($this->getAppointmentRemainingAmount())),

                    Forms\Components\Select::make('journal_id')
                        ->label(__('billing::billing.fields.payment_method'))
                        ->options(fn () => Journal::active()
                            ->whereIn('type', [Journal::TYPE_CASH, Journal::TYPE_BANK, Journal::TYPE_GIFT_CARD])
                            ->get()
                            ->pluck('display_name', 'id'))
                        ->required()
                        ->native(false)
                        ->searchable()
                        ->default(fn () => Journal::getCashJournal()?->id)
                        ->live()
                        ->afterStateUpdated(function (Set $set, Get $get, $state) {
                            $journal = Journal::find($state);
                            if ($journal?->type === Journal::TYPE_GIFT_CARD) {
                                $set('gift_card_id', null);
                                $set('amount_minor', null);
                            } else {
                                $set('gift_card_id', null);
                                $set('gift_card_code', null);
                                $set('amount_minor', $this->getAppointmentRemainingAmount() / 100);
                            }
                        }),

                    // Gift card code input
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

                            // Set amount to minimum of card balance and remaining
                            $maxAmount = min($card->remaining_value_minor, $this->getAppointmentRemainingAmount());
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
                        ->maxValue(fn (Get $get) => $this->getMaxPaymentAmount($get) / 100)
                        ->prefix(current_currency())
                        ->default(fn () => $this->getAppointmentRemainingAmount() / 100)
                        ->helperText(fn (Get $get) => __('billing::billing.record_payment.max_amount', [
                            'amount' => format_money($this->getMaxPaymentAmount($get))
                        ])),

                    Forms\Components\DateTimePicker::make('paid_at')
                        ->label(__('billing::billing.fields.paid_at'))
                        ->required()
                        ->default(now()),

                    Forms\Components\TextInput::make('reference_number')
                        ->label(__('billing::billing.fields.reference'))
                        ->maxLength(255)
                        ->visible(fn (Get $get) => !$this->isGiftCardJournal($get('journal_id'))),

                    Forms\Components\Textarea::make('notes')
                        ->label(__('billing::billing.fields.notes'))
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    $this->recordPayment($data);
                }),

            Actions\Action::make('confirm')
                ->label(__('booking::appointments.actions.confirm'))
                ->icon('heroicon-o-check')
                ->color('primary')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->canTransitionTo(Appointment::STATUS_CONFIRMED))
                ->action(fn () => $this->record->confirm()),

            Actions\Action::make('check_in')
                ->label(__('booking::appointments.actions.check_in'))
                ->icon('heroicon-o-arrow-right-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->canTransitionTo(Appointment::STATUS_CHECKED_IN))
                ->action(fn () => $this->record->checkIn()),

            Actions\Action::make('complete')
                ->label(__('booking::appointments.actions.complete'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->canTransitionTo(Appointment::STATUS_COMPLETED))
                ->action(fn () => $this->record->complete()),

            Actions\Action::make('cancel')
                ->label(__('booking::appointments.actions.cancel'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->form([
                    Forms\Components\Textarea::make('cancellation_reason')
                        ->label(__('booking::appointments.fields.cancellation_reason'))
                        ->required(),
                ])
                ->visible(fn (): bool => $this->record->canTransitionTo(Appointment::STATUS_CANCELLED))
                ->action(fn (array $data) => $this->record->cancel($data['cancellation_reason'])),
        ];
    }

    protected function canRecordPayment(): bool
    {
        // Can record payment if appointment is not cancelled and has remaining amount
        return !$this->record->isCancelled() && $this->getAppointmentRemainingAmount() > 0;
    }

    protected function getAppointmentPaidAmount(): int
    {
        return Payment::forAppointment($this->record->id)
            ->completed()
            ->sum('amount_minor');
    }

    protected function getAppointmentRemainingAmount(): int
    {
        $netPrice = $this->record->net_price ?? 0;
        $paid = $this->getAppointmentPaidAmount();
        return max(0, $netPrice - $paid);
    }

    protected function isGiftCardJournal(?string $journalId): bool
    {
        if (!$journalId) {
            return false;
        }
        $journal = Journal::find($journalId);
        return $journal?->type === Journal::TYPE_GIFT_CARD;
    }

    protected function getMaxPaymentAmount(Get $get): int
    {
        if ($this->isGiftCardJournal($get('journal_id')) && $get('gift_card_id')) {
            $card = GiftCard::find($get('gift_card_id'));
            if ($card) {
                return min($card->remaining_value_minor, $this->getAppointmentRemainingAmount());
            }
        }
        return $this->getAppointmentRemainingAmount();
    }

    protected function recordPayment(array $data): void
    {
        $amountMinor = (int) ($data['amount_minor'] * 100);
        $journal = Journal::find($data['journal_id']);
        $isGiftCard = $journal?->type === Journal::TYPE_GIFT_CARD;

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

            // Create payment
            $payment = Payment::create([
                'appointment_id' => $this->record->id,
                'patient_id' => $this->record->patient_id,
                'branch_id' => $this->record->branch_id,
                'journal_id' => $data['journal_id'],
                'amount_minor' => $amountMinor,
                'paid_at' => $data['paid_at'],
                'reference_number' => $card->code,
                'gift_card_id' => $card->id,
                'notes' => $data['notes'] ?? __('booking::appointments.payment_for_appointment', ['code' => $this->record->code]),
                'received_by_user_id' => auth()->id(),
            ]);

            // Redeem from gift card
            $giftCardService = app(GiftCardService::class);
            $result = $giftCardService->redeem($card, $amountMinor, null, $payment);

            Notification::make()
                ->title(__('billing::billing.record_payment.payment_recorded'))
                ->body(__('billing::billing.record_payment.gift_card_redeemed', [
                    'amount' => format_money($amountMinor),
                    'remaining' => format_money($result['remaining_balance'] ?? 0),
                ]))
                ->success()
                ->send();
        } else {
            // Regular payment
            Payment::create([
                'appointment_id' => $this->record->id,
                'patient_id' => $this->record->patient_id,
                'branch_id' => $this->record->branch_id,
                'journal_id' => $data['journal_id'],
                'amount_minor' => $amountMinor,
                'paid_at' => $data['paid_at'],
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? __('booking::appointments.payment_for_appointment', ['code' => $this->record->code]),
                'received_by_user_id' => auth()->id(),
            ]);

            Notification::make()
                ->title(__('billing::billing.record_payment.payment_recorded'))
                ->body(__('billing::billing.record_payment.amount_label', ['amount' => format_money($amountMinor)]))
                ->success()
                ->send();
        }

        // Refresh the page to update the record
        $this->redirect(request()->header('Referer'));
    }
}
