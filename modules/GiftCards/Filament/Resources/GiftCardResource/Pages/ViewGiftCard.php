<?php

namespace Modules\GiftCards\Filament\Resources\GiftCardResource\Pages;

use Modules\GiftCards\Filament\Resources\GiftCardResource;
use Modules\GiftCards\Models\GiftCard;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms;
use Filament\Notifications\Notification;

class ViewGiftCard extends ViewRecord
{
    protected static string $resource = GiftCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn (GiftCard $record) => $record->isDraft()),

            Actions\Action::make('activate')
                ->label(__('giftcards::giftcards.actions.activate'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (GiftCard $record) => $record->isDraft())
                ->requiresConfirmation()
                ->action(function (GiftCard $record) {
                    if ($record->activate()) {
                        Notification::make()
                            ->title(__('giftcards::giftcards.messages.activated'))
                            ->success()
                            ->send();
                    }
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
                        ->prefix(config('app.currency_symbol', 'EGP'))
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
                        ->prefix(config('app.currency_symbol', 'EGP')),

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
        ];
    }
}
