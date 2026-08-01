<?php

namespace Modules\Memberships\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Memberships\Models\Membership;
use Modules\Services\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use XLinic\Framework\Core\Filament\RelationManagers\ActivityLogRelationManager;

class MembershipResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = Membership::class;

    protected static ?string $moduleCode = 'memberships';

    protected static ?string $permissionKey = 'memberships';

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationGroup = 'Marketing';

    public static function getNavigationParentItem(): ?string
    {
        return __('core::core.nav_folders.loyalty_gifts');
    }

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('memberships::memberships.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('memberships::memberships.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('memberships::memberships.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('memberships::memberships.sections.basic_info'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name.en')
                                    ->label(__('memberships::memberships.fields.name_en'))
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('name.ar')
                                    ->label(__('memberships::memberships.fields.name_ar'))
                                    ->maxLength(255),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Textarea::make('description.en')
                                    ->label(__('memberships::memberships.fields.description_en'))
                                    ->rows(3),

                                Forms\Components\Textarea::make('description.ar')
                                    ->label(__('memberships::memberships.fields.description_ar'))
                                    ->rows(3),
                            ]),

                        Forms\Components\Select::make('tier')
                            ->label(__('memberships::memberships.fields.tier'))
                            ->options(Membership::TIERS)
                            ->required()
                            ->default(Membership::TIER_SILVER),
                    ]),

                Forms\Components\Section::make(__('memberships::memberships.sections.pricing'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('price_monthly_minor')
                                    ->label(__('memberships::memberships.fields.price_monthly'))
                                    ->required()
                                    ->numeric()
                                    ->prefix(current_currency())
                                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                                    ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0),

                                Forms\Components\TextInput::make('price_yearly_minor')
                                    ->label(__('memberships::memberships.fields.price_yearly'))
                                    ->required()
                                    ->numeric()
                                    ->prefix(current_currency())
                                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                                    ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0),
                            ]),
                    ]),

                Forms\Components\Section::make(__('memberships::memberships.sections.benefits'))
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('discount_percentage')
                                    ->label(__('memberships::memberships.fields.discount_percentage'))
                                    ->required()
                                    ->numeric()
                                    ->suffix('%')
                                    ->default(0)
                                    ->minValue(0)
                                    ->maxValue(100),

                                Forms\Components\TextInput::make('loyalty_multiplier')
                                    ->label(__('memberships::memberships.fields.loyalty_multiplier'))
                                    ->required()
                                    ->numeric()
                                    ->default(1.0)
                                    ->minValue(1.0)
                                    ->step(0.1),

                                Forms\Components\Toggle::make('priority_booking')
                                    ->label(__('memberships::memberships.fields.priority_booking'))
                                    ->default(false),
                            ]),

                        Forms\Components\KeyValue::make('included_sessions_monthly')
                            ->label(__('memberships::memberships.fields.included_sessions'))
                            ->keyLabel(__('memberships::memberships.fields.service_id'))
                            ->valueLabel(__('memberships::memberships.fields.sessions_per_month'))
                            ->addActionLabel(__('memberships::memberships.actions.add_session'))
                            ->reorderable(false),
                    ]),

                Forms\Components\Section::make(__('memberships::memberships.sections.settings'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('is_active')
                                    ->label(__('memberships::memberships.fields.is_active'))
                                    ->default(true),

                                Forms\Components\TextInput::make('sort_order')
                                    ->label(__('memberships::memberships.fields.sort_order'))
                                    ->numeric()
                                    ->default(0),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name.en')
                    ->label(__('memberships::memberships.fields.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tier')
                    ->label(__('memberships::memberships.fields.tier'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => Membership::TIERS[$state] ?? $state)
                    ->color(fn ($state) => Membership::TIER_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('price_monthly_minor')
                    ->label(__('memberships::memberships.fields.monthly'))
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->suffix(' ' . current_currency())
                    ->sortable(),

                Tables\Columns\TextColumn::make('price_yearly_minor')
                    ->label(__('memberships::memberships.fields.yearly'))
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->suffix(' ' . current_currency())
                    ->sortable(),

                Tables\Columns\TextColumn::make('discount_percentage')
                    ->label(__('memberships::memberships.fields.discount'))
                    ->suffix('%')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('loyalty_multiplier')
                    ->label(__('memberships::memberships.fields.loyalty'))
                    ->suffix('x'),

                Tables\Columns\IconColumn::make('priority_booking')
                    ->label(__('memberships::memberships.fields.priority'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('active_subscriptions_count')
                    ->label(__('memberships::memberships.fields.members'))
                    ->getStateUsing(fn (Membership $record) => $record->active_subscriptions_count)
                    ->badge()
                    ->color('info'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label(__('memberships::memberships.fields.active')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tier')
                    ->label(__('memberships::memberships.fields.tier'))
                    ->options(Membership::TIERS),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('memberships::memberships.fields.active')),
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
            ])
            ->defaultSort('sort_order');
    }

    public static function getRelations(): array
    {
        return [
            \Modules\Memberships\Filament\Resources\MembershipResource\RelationManagers\SubscriptionsRelationManager::class,
            ActivityLogRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \Modules\Memberships\Filament\Resources\MembershipResource\Pages\ListMemberships::route('/'),
            'create' => \Modules\Memberships\Filament\Resources\MembershipResource\Pages\CreateMembership::route('/create'),
            'view' => \Modules\Memberships\Filament\Resources\MembershipResource\Pages\ViewMembership::route('/{record}'),
            'edit' => \Modules\Memberships\Filament\Resources\MembershipResource\Pages\EditMembership::route('/{record}/edit'),
        ];
    }
}
