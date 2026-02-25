<?php

namespace Modules\GiftCards\Filament\Resources\GiftCardTemplateResource\Pages;

use Modules\GiftCards\Filament\Resources\GiftCardTemplateResource;
use Modules\GiftCards\Services\GiftCardService;
use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;

class EditGiftCardTemplate extends BaseEditRecord
{
    protected static string $resource = GiftCardTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate_batch')
                ->label(__('giftcards::giftcards.actions.generate_batch'))
                ->icon('heroicon-o-squares-plus')
                ->color('success')
                ->visible(fn () => $this->record->is_active)
                ->form([
                    Forms\Components\TextInput::make('quantity')
                        ->label(__('giftcards::giftcards.template.quantity'))
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(100)
                        ->default(10),

                    Forms\Components\Toggle::make('generate_pin')
                        ->label(__('giftcards::giftcards.template.generate_pin'))
                        ->default(false),
                ])
                ->action(function (array $data) {
                    try {
                        $cards = app(GiftCardService::class)->generateBatch(
                            $this->record,
                            $data['quantity'],
                            ['generate_pin' => $data['generate_pin'] ?? false]
                        );

                        Notification::make()
                            ->title(__('giftcards::giftcards.messages.batch_generated', ['count' => $cards->count()]))
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title(__('giftcards::giftcards.messages.batch_failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
