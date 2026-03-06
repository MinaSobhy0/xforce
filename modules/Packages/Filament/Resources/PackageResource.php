<?php

namespace Modules\Packages\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Packages\Models\Package;
use Modules\Packages\Models\PackageItem;
use Modules\Services\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use XLinic\Framework\Core\Filament\RelationManagers\ActivityLogRelationManager;

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
                                Forms\Components\TextInput::make('validity_days')
                                    ->label(__('packages::packages.fields.validity_days'))
                                    ->required()
                                    ->numeric()
                                    ->default(365)
                                    ->suffix(__('packages::packages.fields.days')),

                                Forms\Components\TextInput::make('min_deposit_percent')
                                    ->label(__('packages::packages.fields.min_deposit_percent'))
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('%')
                                    ->minValue(0)
                                    ->maxValue(100),

                                Forms\Components\Placeholder::make('calculated_total')
                                    ->label(__('packages::packages.fields.total_price'))
                                    ->content(fn (Get $get) => self::calculatePackageTotal($get('items')))
                                    ->live(),
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
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if ($state) {
                                            $service = Service::find($state);
                                            if ($service) {
                                                // Auto-fill unit price from service price
                                                $set('unit_price_minor', $service->base_price_minor / 100);
                                            }
                                        }
                                    })
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('quantity')
                                    ->label(__('packages::packages.fields.quantity'))
                                    ->required()
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->live(onBlur: true)
                                    ->columnSpan(1),

                                Forms\Components\Select::make('consumption_type')
                                    ->label(__('packages::packages.fields.consumption_type'))
                                    ->options([
                                        PackageItem::CONSUMPTION_SESSIONS => __('packages::packages.consumption_types.sessions'),
                                        PackageItem::CONSUMPTION_PULSES => __('packages::packages.consumption_types.pulses'),
                                    ])
                                    ->default(PackageItem::CONSUMPTION_SESSIONS)
                                    ->required()
                                    ->live()
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('unit_price_minor')
                                    ->label(__('packages::packages.fields.unit_price'))
                                    ->required()
                                    ->numeric()
                                    ->prefix(current_currency())
                                    ->live(onBlur: true)
                                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                                    ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('pulses_per_session')
                                    ->label(__('packages::packages.fields.pulses_per_session'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->visible(fn (Get $get) => $get('consumption_type') === PackageItem::CONSUMPTION_PULSES)
                                    ->columnSpan(1),

                                Forms\Components\Placeholder::make('line_total')
                                    ->label(__('packages::packages.fields.line_total'))
                                    ->content(function (Get $get) {
                                        $qty = (int) ($get('quantity') ?? 0);
                                        $price = (float) ($get('unit_price_minor') ?? 0);
                                        $total = $qty * $price * 100;
                                        return format_money((int) $total);
                                    })
                                    ->columnSpan(1),
                            ])
                            ->columns(6)
                            ->defaultItems(1)
                            ->addActionLabel(__('packages::packages.actions.add_item'))
                            ->reorderable()
                            ->collapsible()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                // Recalculate package total when items change
                                $total = self::calculatePackageTotalMinor($get('items'));
                                $set('base_price_minor', $total / 100);
                            })
                            ->itemLabel(function (array $state): ?string {
                                if (!isset($state['service_id'])) {
                                    return null;
                                }
                                $serviceName = Service::find($state['service_id'])?->translated_name ?? 'Service';
                                $qty = (int) ($state['quantity'] ?? 1);
                                $unitPrice = (float) ($state['unit_price_minor'] ?? 0);
                                $consumptionType = ($state['consumption_type'] ?? '') === PackageItem::CONSUMPTION_PULSES ? 'pulses' : 'sessions';
                                $total = (int) ($qty * $unitPrice * 100);

                                return "{$serviceName} × {$qty} ({$consumptionType}) = " . format_money($total);
                            }),

                        // Hidden field to store calculated total
                        Forms\Components\Hidden::make('base_price_minor')
                            ->dehydrateStateUsing(fn ($state, Get $get) => self::calculatePackageTotalMinor($get('items'))),
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

                Tables\Columns\TextColumn::make('effective_price')
                    ->label(__('packages::packages.fields.price'))
                    ->getStateUsing(fn (Package $record) => format_money($record->effective_price_minor))
                    ->sortable(['base_price_minor']),

                Tables\Columns\TextColumn::make('items_count')
                    ->label(__('packages::packages.fields.services'))
                    ->counts('items')
                    ->suffix(' ' . __('packages::packages.fields.services_suffix')),

                Tables\Columns\TextColumn::make('sessions_summary')
                    ->label(__('packages::packages.fields.sessions'))
                    ->getStateUsing(function (Package $record) {
                        $sessions = $record->total_sessions;
                        $pulses = $record->total_pulses;
                        $parts = [];
                        if ($sessions > 0) {
                            $parts[] = "{$sessions} sessions";
                        }
                        if ($pulses > 0) {
                            $parts[] = "{$pulses} pulses";
                        }
                        return implode(', ', $parts) ?: '-';
                    }),

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
            ActivityLogRelationManager::class,
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

    /**
     * Calculate package total from items array (returns formatted string)
     */
    protected static function calculatePackageTotal(?array $items): string
    {
        $total = self::calculatePackageTotalMinor($items);
        return format_money($total);
    }

    /**
     * Calculate package total from items array (returns minor units)
     */
    protected static function calculatePackageTotalMinor(?array $items): int
    {
        if (empty($items)) {
            return 0;
        }

        $total = 0;
        foreach ($items as $item) {
            $qty = (int) ($item['quantity'] ?? 0);
            // unit_price_minor comes in as display value (major units) from the form
            $price = (float) ($item['unit_price_minor'] ?? 0);
            $total += $qty * $price * 100;
        }

        return (int) $total;
    }
}
