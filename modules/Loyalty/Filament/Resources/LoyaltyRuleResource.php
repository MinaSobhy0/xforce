<?php

namespace Modules\Loyalty\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Loyalty\Filament\Resources\LoyaltyRuleResource\Pages;
use Modules\Loyalty\Models\LoyaltyRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Resources\Concerns\Translatable;

class LoyaltyRuleResource extends Resource
{
    use Translatable;
    use ChecksResourcePermissions;

    protected static ?string $model = LoyaltyRule::class;

    protected static ?string $moduleCode = 'loyalty';

    protected static ?string $permissionKey = 'loyalty_rules';

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationGroup = 'Marketing';

    public static function getNavigationParentItem(): ?string
    {
        return __('core::core.nav_folders.loyalty_gifts');
    }

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('loyalty::loyalty.loyalty_rules');
    }

    public static function getModelLabel(): string
    {
        return __('loyalty::loyalty.loyalty_rule');
    }

    public static function getPluralModelLabel(): string
    {
        return __('loyalty::loyalty.loyalty_rules');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('loyalty::loyalty.sections.basic_info'))
                    ->schema([
                        Forms\Components\TextInput::make('name.en')
                            ->label(__('loyalty::loyalty.fields.name') . ' (EN)')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('name.ar')
                            ->label(__('loyalty::loyalty.fields.name') . ' (AR)')
                            ->maxLength(255)
                            ->columnSpan(1),

                        Forms\Components\Select::make('type')
                            ->label(__('loyalty::loyalty.fields.type'))
                            ->options(LoyaltyRule::getTypes())
                            ->required()
                            ->reactive()
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('loyalty::loyalty.fields.is_active'))
                            ->default(true)
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('description.en')
                            ->label(__('loyalty::loyalty.fields.description') . ' (EN)')
                            ->rows(2)
                            ->columnSpan(2),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('loyalty::loyalty.sections.points_config'))
                    ->schema([
                        Forms\Components\TextInput::make('points_amount')
                            ->label(__('loyalty::loyalty.fields.points_amount'))
                            ->numeric()
                            ->minValue(0)
                            ->visible(fn (Forms\Get $get) => in_array($get('type'), [
                                LoyaltyRule::TYPE_PER_VISIT,
                                LoyaltyRule::TYPE_BIRTHDAY,
                                LoyaltyRule::TYPE_SIGNUP,
                                LoyaltyRule::TYPE_FIRST_PURCHASE,
                                LoyaltyRule::TYPE_REFERRAL,
                            ]))
                            ->helperText('Fixed points amount for this rule type'),

                        Forms\Components\TextInput::make('points_per_currency_unit')
                            ->label(__('loyalty::loyalty.fields.points_per_currency_unit'))
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->visible(fn (Forms\Get $get) => $get('type') === LoyaltyRule::TYPE_PER_SPEND)
                            ->helperText(fn () => 'Points earned per 1 ' . current_currency() . ' spent'),

                        Forms\Components\TextInput::make('min_spend_minor')
                            ->label(__('loyalty::loyalty.fields.min_spend'))
                            ->numeric()
                            ->minValue(0)
                            ->suffix(fn () => current_currency())
                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                            ->dehydrateStateUsing(fn ($state) => $state ? $state * 100 : null)
                            ->visible(fn (Forms\Get $get) => $get('type') === LoyaltyRule::TYPE_PER_SPEND),

                        Forms\Components\TextInput::make('max_points_per_transaction')
                            ->label(__('loyalty::loyalty.fields.max_points'))
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Leave empty for no cap'),

                        Forms\Components\TextInput::make('multiplier')
                            ->label(__('loyalty::loyalty.fields.multiplier'))
                            ->numeric()
                            ->minValue(0.1)
                            ->maxValue(10)
                            ->step(0.1)
                            ->default(1.0)
                            ->helperText('1.0 = normal, 2.0 = double points'),

                        Forms\Components\TextInput::make('priority')
                            ->label(__('loyalty::loyalty.fields.priority'))
                            ->numeric()
                            ->default(0)
                            ->helperText('Higher priority rules are applied first'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('loyalty::loyalty.sections.targeting'))
                    ->schema([
                        Forms\Components\Select::make('service_id')
                            ->label(__('loyalty::loyalty.fields.service'))
                            ->relationship('service', 'name->en')
                            ->searchable()
                            ->preload()
                            ->helperText('Leave empty to apply to all services'),

                        Forms\Components\Select::make('service_category_id')
                            ->label(__('loyalty::loyalty.fields.service_category'))
                            ->relationship('serviceCategory', 'name->en')
                            ->searchable()
                            ->preload()
                            ->helperText('Leave empty to apply to all categories'),
                    ])
                    ->columns(2)
                    ->visible(fn (Forms\Get $get) => $get('type') === LoyaltyRule::TYPE_PER_SPEND),

                Forms\Components\Section::make(__('loyalty::loyalty.sections.validity'))
                    ->schema([
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label(__('loyalty::loyalty.fields.starts_at'))
                            ->helperText('Leave empty to start immediately'),

                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label(__('loyalty::loyalty.fields.ends_at'))
                            ->helperText('Leave empty for no end date'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('loyalty::loyalty.fields.name'))
                    ->formatStateUsing(fn ($state) => is_array($state) ? ($state['en'] ?? $state['ar'] ?? '') : $state)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('loyalty::loyalty.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => LoyaltyRule::getTypes()[$state] ?? $state)
                    ->color(fn ($state) => LoyaltyRule::getTypeColors()[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('points_amount')
                    ->label(__('loyalty::loyalty.fields.points_amount'))
                    ->numeric()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('points_per_currency_unit')
                    ->label(__('loyalty::loyalty.fields.points_per_currency_unit'))
                    ->numeric()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('multiplier')
                    ->label(__('loyalty::loyalty.fields.multiplier'))
                    ->numeric()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('priority')
                    ->label(__('loyalty::loyalty.fields.priority'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('loyalty::loyalty.fields.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label(__('loyalty::loyalty.fields.starts_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label(__('loyalty::loyalty.fields.ends_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('core::core.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('priority', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('loyalty::loyalty.fields.type'))
                    ->options(LoyaltyRule::getTypes()),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('loyalty::loyalty.fields.is_active')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoyaltyRules::route('/'),
            'create' => Pages\CreateLoyaltyRule::route('/create'),
            'view' => Pages\ViewLoyaltyRule::route('/{record}'),
            'edit' => Pages\EditLoyaltyRule::route('/{record}/edit'),
        ];
    }
}
