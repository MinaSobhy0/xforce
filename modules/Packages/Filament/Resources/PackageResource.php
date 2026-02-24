<?php

namespace Modules\Packages\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Packages\Models\Package;
use Modules\Services\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PackageResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = Package::class;

    protected static ?string $moduleCode = 'packages';

    protected static ?string $permissionKey = 'packages';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('packages::packages.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('packages::packages.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('packages::packages.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('packages::packages.sections.basic_info'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name.en')
                                    ->label(__('packages::packages.fields.name_en'))
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('name.ar')
                                    ->label(__('packages::packages.fields.name_ar'))
                                    ->maxLength(255),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Textarea::make('description.en')
                                    ->label(__('packages::packages.fields.description_en'))
                                    ->rows(3),

                                Forms\Components\Textarea::make('description.ar')
                                    ->label(__('packages::packages.fields.description_ar'))
                                    ->rows(3),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->label(__('packages::packages.fields.type'))
                                    ->options([
                                        Package::TYPE_SESSION_BUNDLE => __('packages::packages.types.session_bundle'),
                                        Package::TYPE_VALUE_BUNDLE => __('packages::packages.types.value_bundle'),
                                    ])
                                    ->required()
                                    ->default(Package::TYPE_SESSION_BUNDLE),

                                Forms\Components\TextInput::make('base_price_minor')
                                    ->label(__('packages::packages.fields.price'))
                                    ->required()
                                    ->numeric()
                                    ->prefix(current_currency())
                                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                                    ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0),

                                Forms\Components\TextInput::make('validity_days')
                                    ->label(__('packages::packages.fields.validity_days'))
                                    ->required()
                                    ->numeric()
                                    ->default(365)
                                    ->suffix(__('packages::packages.fields.days')),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Toggle::make('is_transferable')
                                    ->label(__('packages::packages.fields.is_transferable'))
                                    ->default(false),

                                Forms\Components\Toggle::make('is_active')
                                    ->label(__('packages::packages.fields.is_active'))
                                    ->default(true),

                                Forms\Components\TextInput::make('sort_order')
                                    ->label(__('packages::packages.fields.sort_order'))
                                    ->numeric()
                                    ->default(0),
                            ]),
                    ]),

                Forms\Components\Section::make(__('packages::packages.sections.items'))
                    ->description(__('packages::packages.sections.items_description'))
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Forms\Components\Select::make('service_id')
                                    ->label(__('packages::packages.fields.service'))
                                    ->options(fn () => Service::active()
                                        ->get()
                                        ->mapWithKeys(fn ($service) => [
                                            $service->id => $service->translated_name . ' (' . $service->formatted_price . ')'
                                        ]))
                                    ->required()
                                    ->searchable()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('quantity')
                                    ->label(__('packages::packages.fields.quantity'))
                                    ->required()
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->addActionLabel(__('packages::packages.actions.add_item'))
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string =>
                                isset($state['service_id'])
                                    ? Service::find($state['service_id'])?->translated_name . ' × ' . ($state['quantity'] ?? 1)
                                    : null
                            ),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('translated_name')
                    ->label(__('packages::packages.fields.name'))
                    ->searchable(['name'])
                    ->sortable(['name']),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('packages::packages.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => Package::TYPES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        Package::TYPE_SESSION_BUNDLE => 'info',
                        Package::TYPE_VALUE_BUNDLE => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('base_price_minor')
                    ->label(__('packages::packages.fields.price'))
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->suffix(' ' . current_currency())
                    ->sortable(),

                Tables\Columns\TextColumn::make('items_count')
                    ->label(__('packages::packages.fields.services'))
                    ->counts('items')
                    ->suffix(' ' . __('packages::packages.fields.services_suffix')),

                Tables\Columns\TextColumn::make('total_sessions')
                    ->label(__('packages::packages.fields.sessions'))
                    ->getStateUsing(fn (Package $record) => $record->total_sessions)
                    ->suffix(' ' . __('packages::packages.fields.sessions_suffix')),

                Tables\Columns\TextColumn::make('validity_days')
                    ->label(__('packages::packages.fields.validity'))
                    ->suffix(' ' . __('packages::packages.fields.days')),

                Tables\Columns\TextColumn::make('active_subscriptions_count')
                    ->label(__('packages::packages.fields.subscriptions'))
                    ->getStateUsing(fn (Package $record) => $record->active_subscriptions_count)
                    ->badge()
                    ->color('success'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label(__('packages::packages.fields.active')),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('packages::packages.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('packages::packages.fields.type'))
                    ->options(Package::TYPES),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('packages::packages.fields.active')),
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
            \Modules\Packages\Filament\Resources\PackageResource\RelationManagers\SubscriptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \Modules\Packages\Filament\Resources\PackageResource\Pages\ListPackages::route('/'),
            'create' => \Modules\Packages\Filament\Resources\PackageResource\Pages\CreatePackage::route('/create'),
            'view' => \Modules\Packages\Filament\Resources\PackageResource\Pages\ViewPackage::route('/{record}'),
            'edit' => \Modules\Packages\Filament\Resources\PackageResource\Pages\EditPackage::route('/{record}/edit'),
        ];
    }
}
