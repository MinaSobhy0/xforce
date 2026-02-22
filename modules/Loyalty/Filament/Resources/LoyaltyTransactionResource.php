<?php

namespace Modules\Loyalty\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Loyalty\Filament\Resources\LoyaltyTransactionResource\Pages;
use Modules\Loyalty\Models\LoyaltyTransaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LoyaltyTransactionResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = LoyaltyTransaction::class;

    protected static ?string $moduleCode = 'loyalty';

    protected static ?string $permissionKey = 'memberships';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 21;

    protected static ?string $navigationParentItem = 'Loyalty Program';

    public static function getNavigationLabel(): string
    {
        return __('loyalty::loyalty.loyalty_transactions');
    }

    public static function getModelLabel(): string
    {
        return __('loyalty::loyalty.loyalty_transaction');
    }

    public static function getPluralModelLabel(): string
    {
        return __('loyalty::loyalty.loyalty_transactions');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('loyalty::loyalty.sections.transaction_details'))
                    ->schema([
                        Forms\Components\Select::make('patient_id')
                            ->label(__('loyalty::loyalty.fields.patient'))
                            ->relationship('patient', 'full_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn ($record) => $record !== null),

                        Forms\Components\Select::make('type')
                            ->label(__('loyalty::loyalty.fields.type'))
                            ->options(LoyaltyTransaction::getTypes())
                            ->required()
                            ->disabled(fn ($record) => $record !== null),

                        Forms\Components\TextInput::make('points')
                            ->label(__('loyalty::loyalty.fields.points'))
                            ->numeric()
                            ->required()
                            ->disabled(fn ($record) => $record !== null),

                        Forms\Components\TextInput::make('running_balance')
                            ->label(__('loyalty::loyalty.fields.running_balance'))
                            ->numeric()
                            ->disabled(),

                        Forms\Components\Textarea::make('description')
                            ->label(__('loyalty::loyalty.fields.description'))
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label(__('loyalty::loyalty.fields.expires_at'))
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('created_at')
                            ->label(__('core::core.created_at'))
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label(__('loyalty::loyalty.fields.patient'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('loyalty::loyalty.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => LoyaltyTransaction::getTypes()[$state] ?? $state)
                    ->color(fn ($state) => LoyaltyTransaction::getTypeColors()[$state] ?? 'gray')
                    ->icon(fn ($state) => LoyaltyTransaction::getTypeIcons()[$state] ?? null),

                Tables\Columns\TextColumn::make('points')
                    ->label(__('loyalty::loyalty.fields.points'))
                    ->numeric()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => $state > 0 ? '+' . number_format($state) : number_format($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('running_balance')
                    ->label(__('loyalty::loyalty.fields.running_balance'))
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label(__('loyalty::loyalty.fields.description'))
                    ->limit(30)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('loyaltyRule.name')
                    ->label(__('loyalty::loyalty.loyalty_rule'))
                    ->formatStateUsing(fn ($state) => is_array($state) ? ($state['en'] ?? '') : $state)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label(__('loyalty::loyalty.fields.created_by'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('loyalty::loyalty.fields.expires_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('core::core.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('loyalty::loyalty.fields.type'))
                    ->options(LoyaltyTransaction::getTypes()),

                Tables\Filters\SelectFilter::make('patient_id')
                    ->label(__('loyalty::loyalty.fields.patient'))
                    ->relationship('patient', 'full_name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('credits')
                    ->label('Credits Only')
                    ->query(fn ($query) => $query->credits()),

                Tables\Filters\Filter::make('debits')
                    ->label('Debits Only')
                    ->query(fn ($query) => $query->debits()),

                Tables\Filters\Filter::make('today')
                    ->label('Today')
                    ->query(fn ($query) => $query->whereDate('created_at', today())),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoyaltyTransactions::route('/'),
            'view' => Pages\ViewLoyaltyTransaction::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Transactions are created via service
    }
}
