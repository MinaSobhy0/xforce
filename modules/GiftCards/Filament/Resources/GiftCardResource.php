<?php

namespace Modules\GiftCards\Filament\Resources;

use App\Traits\ChecksTenantModuleAccess;
use Modules\GiftCards\Models\GiftCard;
use Modules\Patients\Models\Patient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class GiftCardResource extends Resource
{
    use ChecksTenantModuleAccess;

    protected static ?string $model = GiftCard::class;

    protected static ?string $moduleCode = 'giftcards';

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 12;

    public static function getNavigationLabel(): string
    {
        return __('giftcards::giftcards.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('giftcards::giftcards.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('giftcards::giftcards.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('giftcards::giftcards.sections.basic_info'))
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label(__('giftcards::giftcards.fields.code'))
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('initial_value_minor')
                                    ->label(__('giftcards::giftcards.fields.value'))
                                    ->required()
                                    ->numeric()
                                    ->prefix(current_currency())
                                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                                    ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0)
                                    ->disabled(fn ($record) => $record && !$record->isDraft()),

                                Forms\Components\DateTimePicker::make('expires_at')
                                    ->label(__('giftcards::giftcards.fields.expires_at'))
                                    ->nullable()
                                    ->default(now()->addDays(config('giftcards.default_expiry_days', 365))),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('purchaser_patient_id')
                                    ->label(__('giftcards::giftcards.fields.purchaser'))
                                    ->options(fn () => Patient::all()->pluck('full_name', 'id'))
                                    ->searchable()
                                    ->nullable(),

                                Forms\Components\Select::make('recipient_patient_id')
                                    ->label(__('giftcards::giftcards.fields.recipient'))
                                    ->options(fn () => Patient::all()->pluck('full_name', 'id'))
                                    ->searchable()
                                    ->nullable(),
                            ]),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('giftcards::giftcards.fields.notes'))
                            ->rows(2),
                    ]),

                Forms\Components\Section::make(__('giftcards::giftcards.sections.status'))
                    ->schema([
                        Forms\Components\Placeholder::make('status_display')
                            ->label(__('giftcards::giftcards.fields.status'))
                            ->content(fn (GiftCard $record): string => $record->status_label),

                        Forms\Components\Placeholder::make('remaining_display')
                            ->label(__('giftcards::giftcards.fields.remaining_value'))
                            ->content(fn (GiftCard $record): string => $record->formatted_remaining_value),

                        Forms\Components\Placeholder::make('usage_display')
                            ->label(__('giftcards::giftcards.fields.usage'))
                            ->content(fn (GiftCard $record): string => $record->usage_percentage . '% used'),
                    ])
                    ->columns(3)
                    ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('giftcards::giftcards.fields.code'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('initial_value_minor')
                    ->label(__('giftcards::giftcards.fields.initial_value'))
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->suffix(' ' . current_currency())
                    ->sortable(),

                Tables\Columns\TextColumn::make('remaining_value_minor')
                    ->label(__('giftcards::giftcards.fields.remaining_value'))
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->suffix(' ' . current_currency())
                    ->color(fn (GiftCard $record) => $record->remaining_value_minor > 0 ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('giftcards::giftcards.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => GiftCard::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => GiftCard::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('purchaser.full_name')
                    ->label(__('giftcards::giftcards.fields.purchaser'))
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('recipient.full_name')
                    ->label(__('giftcards::giftcards.fields.recipient'))
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('giftcards::giftcards.fields.expires_at'))
                    ->dateTime()
                    ->sortable()
                    ->color(fn (GiftCard $record) => $record->isExpiringSoon() ? 'danger' : null),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('giftcards::giftcards.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('giftcards::giftcards.fields.status'))
                    ->options(GiftCard::STATUSES),

                Tables\Filters\Filter::make('has_balance')
                    ->label(__('giftcards::giftcards.filters.has_balance'))
                    ->query(fn ($query) => $query->where('remaining_value_minor', '>', 0)),

                Tables\Filters\Filter::make('expiring_soon')
                    ->label(__('giftcards::giftcards.filters.expiring_soon'))
                    ->query(fn ($query) => $query->expiringSoon()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (GiftCard $record) => $record->isDraft()),

                Tables\Actions\Action::make('activate')
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

                Tables\Actions\Action::make('redeem')
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
                            ->default(fn (GiftCard $record) => $record->remaining_value_minor / 100),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('giftcards::giftcards.fields.notes'))
                            ->rows(2),
                    ])
                    ->action(function (GiftCard $record, array $data) {
                        $amountMinor = (int) ($data['amount'] * 100);
                        if ($record->redeem($amountMinor, null, null, $data['notes'])) {
                            Notification::make()
                                ->title(__('giftcards::giftcards.messages.redeemed'))
                                ->success()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('cancel')
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
                        }
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            \Modules\GiftCards\Filament\Resources\GiftCardResource\RelationManagers\TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \Modules\GiftCards\Filament\Resources\GiftCardResource\Pages\ListGiftCards::route('/'),
            'create' => \Modules\GiftCards\Filament\Resources\GiftCardResource\Pages\CreateGiftCard::route('/create'),
            'view' => \Modules\GiftCards\Filament\Resources\GiftCardResource\Pages\ViewGiftCard::route('/{record}'),
            'edit' => \Modules\GiftCards\Filament\Resources\GiftCardResource\Pages\EditGiftCard::route('/{record}/edit'),
        ];
    }
}
