<?php

namespace Modules\GiftCards\Filament\Resources\GiftCardResource\Pages;

use Modules\GiftCards\Filament\Resources\GiftCardResource;
use Modules\GiftCards\Models\GiftCard;
use Modules\GiftCards\Services\GiftCardService;
use Modules\Patients\Models\Patient;
use Modules\Accounting\Models\Journal;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;
use Filament\Forms;
use Filament\Notifications\Notification;

class ViewGiftCard extends BaseViewRecord
{
    protected static string $resource = GiftCardResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn (GiftCard $record) => $record->isDraft()),

            Actions\Action::make('activate')
                ->label(__('giftcards::giftcards.actions.activate'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (GiftCard $record) => $record->isDraft())
                ->modalHeading(__('giftcards::giftcards.staff_dashboard.sell_card'))
                ->modalWidth('lg')
                ->form(fn (GiftCard $record) => [
                    Forms\Components\Section::make(__('giftcards::giftcards.fields.pricing'))
                        ->schema([
                            Forms\Components\Grid::make(3)
                                ->schema([
                                    Forms\Components\Placeholder::make('face_value')
                                        ->label(__('giftcards::giftcards.fields.face_value'))
                                        ->content(fn () => format_money($record->initial_value_minor)),

                                    Forms\Components\Placeholder::make('template_discount_display')
                                        ->label(__('giftcards::giftcards.fields.template_discount'))
                                        ->content(function () use ($record) {
                                            $discount = $record->calculateTemplateDiscount();
                                            if ($discount <= 0) {
                                                return '-';
                                            }
                                            $template = $record->template;
                                            $discountLabel = $template->discount_type === 'percentage'
                                                ? "{$template->discount_value}%"
                                                : format_money($template->discount_value);
                                            return "- " . format_money($discount) . " ({$discountLabel})";
                                        }),

                                    Forms\Components\Placeholder::make('price_after_template_discount')
                                        ->label(__('giftcards::giftcards.fields.price_after_discount'))
                                        ->content(fn () => format_money($record->getTemplateDiscountedPrice())),
                                ]),

                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('extra_discount')
                                        ->label(__('giftcards::giftcards.fields.extra_discount'))
                                        ->numeric()
                                        ->default(0)
                                        ->prefix(current_currency())
                                        ->live(onBlur: true)
                                        ->minValue(0)
                                        ->maxValue(fn () => $record->getTemplateDiscountedPrice() / 100),

                                    Forms\Components\Placeholder::make('final_price')
                                        ->label(__('giftcards::giftcards.fields.final_price'))
                                        ->content(function (Forms\Get $get) use ($record) {
                                            $extraDiscount = (float) ($get('extra_discount') ?? 0) * 100;
                                            $finalPrice = $record->getTemplateDiscountedPrice() - $extraDiscount;
                                            return format_money(max(0, (int) $finalPrice));
                                        })
                                        ->extraAttributes(['class' => 'text-lg font-bold text-primary-600']),
                                ]),
                        ]),

                    Forms\Components\Radio::make('patient_type')
                        ->label(__('giftcards::giftcards.staff_dashboard.patient_type'))
                        ->options([
                            'existing' => __('giftcards::giftcards.staff_dashboard.existing_patient'),
                            'new' => __('giftcards::giftcards.staff_dashboard.new_patient'),
                        ])
                        ->default('existing')
                        ->live()
                        ->required()
                        ->afterStateUpdated(function (Forms\Set $set, $state) {
                            if ($state === 'new') {
                                $set('purchaser_patient_id', null);
                            } else {
                                $set('new_patient_first_name', null);
                                $set('new_patient_last_name', null);
                                $set('new_patient_phone', null);
                                $set('new_patient_email', null);
                            }
                        }),

                    Forms\Components\Select::make('purchaser_patient_id')
                        ->label(__('giftcards::giftcards.fields.purchaser'))
                        ->options(fn () => Patient::orderBy('first_name')->get()->pluck('full_name', 'id'))
                        ->searchable()
                        ->required(fn (Forms\Get $get) => $get('patient_type') === 'existing')
                        ->visible(fn (Forms\Get $get) => $get('patient_type') === 'existing')
                        ->dehydratedWhenHidden(false),

                    Forms\Components\Section::make(__('giftcards::giftcards.staff_dashboard.new_patient'))
                        ->schema([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('new_patient_first_name')
                                        ->label(__('patients::patients.fields.first_name'))
                                        ->required(fn (Forms\Get $get) => $get('patient_type') === 'new'),

                                    Forms\Components\TextInput::make('new_patient_last_name')
                                        ->label(__('patients::patients.fields.last_name')),
                                ]),

                            Forms\Components\TextInput::make('new_patient_phone')
                                ->label(__('patients::patients.fields.phone'))
                                ->tel()
                                ->required(fn (Forms\Get $get) => $get('patient_type') === 'new'),

                            Forms\Components\TextInput::make('new_patient_email')
                                ->label(__('patients::patients.fields.email'))
                                ->email(),
                        ])
                        ->visible(fn (Forms\Get $get) => $get('patient_type') === 'new'),

                    Forms\Components\Select::make('journal_id')
                        ->label(__('giftcards::giftcards.staff_dashboard.payment_method'))
                        ->options(fn () => Journal::where('is_active', true)
                            ->whereIn('type', [Journal::TYPE_CASH, Journal::TYPE_BANK])
                            ->get()
                            ->pluck('name', 'id'))
                        ->required()
                        ->searchable(),

                    Forms\Components\Select::make('recipient_patient_id')
                        ->label(__('giftcards::giftcards.fields.recipient'))
                        ->helperText(__('giftcards::giftcards.staff_dashboard.recipient_hint'))
                        ->options(fn () => Patient::orderBy('first_name')->get()->pluck('full_name', 'id'))
                        ->searchable()
                        ->nullable(),

                    Forms\Components\Textarea::make('notes')
                        ->label(__('giftcards::giftcards.fields.notes'))
                        ->rows(2),
                ])
                ->action(function (GiftCard $record, array $data) {
                    $purchaserData = null;

                    if (($data['patient_type'] ?? '') === 'new') {
                        if (empty($data['new_patient_first_name'])) {
                            Notification::make()
                                ->title('First name is required for new patient')
                                ->danger()
                                ->send();
                            return;
                        }

                        $purchaserData = [
                            'first_name' => $data['new_patient_first_name'],
                            'last_name' => $data['new_patient_last_name'] ?? '',
                            'phone' => $data['new_patient_phone'] ?? null,
                            'email' => $data['new_patient_email'] ?? null,
                        ];
                    } else {
                        if (empty($data['purchaser_patient_id'])) {
                            Notification::make()
                                ->title('Please select a patient')
                                ->danger()
                                ->send();
                            return;
                        }

                        $purchaserData = $data['purchaser_patient_id'];
                    }

                    $extraDiscountMinor = (int) (($data['extra_discount'] ?? 0) * 100);

                    $result = app(GiftCardService::class)->processSale(
                        $record,
                        $data['journal_id'],
                        $purchaserData,
                        $data['recipient_patient_id'] ?? null,
                        $data['notes'] ?? null,
                        $extraDiscountMinor
                    );

                    if (!$result['success']) {
                        Notification::make()
                            ->title($result['error'] ?? 'Failed to activate card')
                            ->danger()
                            ->send();
                        return;
                    }

                    Notification::make()
                        ->title(__('giftcards::giftcards.messages.activated'))
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'purchaser_patient_id', 'recipient_patient_id', 'activated_at', 'sold_price_minor', 'total_discount_minor']);
                }),

            Actions\Action::make('redeem')
                ->label(__('giftcards::giftcards.actions.redeem'))
                ->icon('heroicon-o-currency-dollar')
                ->color('warning')
                ->visible(fn (GiftCard $record) => $record->canRedeem())
                ->form([
                    Forms\Components\TextInput::make('amount')
                        ->label(__('giftcards::giftcards.fields.amount'))
                        ->required()
                        ->numeric()
                        ->prefix(current_currency())
                        ->default(fn () => $this->record->remaining_value_minor / 100),

                    Forms\Components\Textarea::make('notes')
                        ->label(__('giftcards::giftcards.fields.notes'))
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    $amountMinor = (int) ($data['amount'] * 100);
                    if ($this->record->redeem($amountMinor, null, null, $data['notes'])) {
                        Notification::make()
                            ->title(__('giftcards::giftcards.messages.redeemed'))
                            ->success()
                            ->send();
                    }
                }),

            Actions\Action::make('refund')
                ->label(__('giftcards::giftcards.actions.refund'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('info')
                ->visible(fn (GiftCard $record) => $record->used_value_minor > 0)
                ->form([
                    Forms\Components\TextInput::make('amount')
                        ->label(__('giftcards::giftcards.fields.amount'))
                        ->required()
                        ->numeric()
                        ->prefix(current_currency()),

                    Forms\Components\Textarea::make('notes')
                        ->label(__('giftcards::giftcards.fields.reason'))
                        ->required()
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    $amountMinor = (int) ($data['amount'] * 100);
                    if ($this->record->refund($amountMinor, $data['notes'])) {
                        Notification::make()
                            ->title(__('giftcards::giftcards.messages.refunded'))
                            ->success()
                            ->send();
                    }
                }),

            Actions\Action::make('cancel')
                ->label(__('giftcards::giftcards.actions.cancel'))
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn (GiftCard $record) => in_array($record->status, [GiftCard::STATUS_DRAFT, GiftCard::STATUS_ACTIVE, GiftCard::STATUS_PARTIALLY_USED]))
                ->requiresConfirmation()
                ->action(function (GiftCard $record) {
                    if ($record->cancel()) {
                        Notification::make()
                            ->title(__('giftcards::giftcards.messages.cancelled'))
                            ->success()
                            ->send();

                        $this->refreshFormData(['status']);
                    }
                }),

            Actions\Action::make('print')
                ->label(__('giftcards::giftcards.actions.print'))
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (GiftCard $record) => route('giftcards.print', $record->id))
                ->openUrlInNewTab(),
        ];
    }
}
