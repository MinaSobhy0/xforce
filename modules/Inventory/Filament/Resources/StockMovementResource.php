<?php

namespace Modules\Inventory\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Models\Branch;
use Modules\Inventory\Filament\Resources\StockMovementResource\Pages;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMovement;

class StockMovementResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 50;

    protected static ?string $moduleCode = 'inventory';

    protected static ?string $permissionKey = 'stock_movements';

    public static function getNavigationLabel(): string
    {
        return __('inventory::inventory.navigation.stock_movements');
    }

    public static function getModelLabel(): string
    {
        return __('inventory::inventory.labels.stock_movement');
    }

    public static function getPluralModelLabel(): string
    {
        return __('inventory::inventory.navigation.stock_movements');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('inventory::inventory.sections.basic_info'))
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label(__('inventory::inventory.fields.product'))
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('branch_id')
                            ->label(__('inventory::inventory.fields.branch'))
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('movement_type')
                            ->label(__('inventory::inventory.fields.movement_type'))
                            ->options(fn () => collect(StockMovement::TYPES)->mapWithKeys(
                                fn ($label, $value) => [$value => __("inventory::inventory.movement_types.{$value}")]
                            ))
                            ->required(),

                        Forms\Components\TextInput::make('quantity')
                            ->label(__('inventory::inventory.fields.quantity'))
                            ->numeric()
                            ->required(),

                        Forms\Components\TextInput::make('quantity_before')
                            ->label(__('inventory::inventory.fields.before'))
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('quantity_after')
                            ->label(__('inventory::inventory.fields.after'))
                            ->numeric()
                            ->disabled(),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('inventory::inventory.fields.notes'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('inventory::inventory.sections.transfer_info'))
                    ->schema([
                        Forms\Components\Select::make('source_branch_id')
                            ->label(__('inventory::inventory.fields.source_branch'))
                            ->relationship('sourceBranch', 'name')
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('destination_branch_id')
                            ->label(__('inventory::inventory.fields.destination_branch'))
                            ->relationship('destinationBranch', 'name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record && in_array($record->movement_type, [
                        StockMovement::TYPE_TRANSFER_IN,
                        StockMovement::TYPE_TRANSFER_OUT,
                    ])),

                Forms\Components\Section::make(__('inventory::inventory.fields.reference'))
                    ->schema([
                        Forms\Components\TextInput::make('reference_type')
                            ->label(__('inventory::inventory.fields.reference_type'))
                            ->disabled(),

                        Forms\Components\TextInput::make('reference_id')
                            ->label(__('inventory::inventory.fields.reference_id'))
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('inventory::inventory.fields.date'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('inventory::inventory.fields.product'))
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('inventory::inventory.fields.branch'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('movement_type')
                    ->label(__('inventory::inventory.fields.movement_type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("inventory::inventory.movement_types.{$state}"))
                    ->color(fn (string $state): string => StockMovement::TYPE_COLORS[$state] ?? 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label(__('inventory::inventory.fields.quantity'))
                    ->numeric()
                    ->alignEnd()
                    ->color(fn (StockMovement $record): string => $record->isIncoming() ? 'success' : 'danger')
                    ->formatStateUsing(fn (StockMovement $record): string =>
                        ($record->isIncoming() ? '+' : '-') . abs($record->quantity)
                    ),

                Tables\Columns\TextColumn::make('unit_cost_minor')
                    ->label(__('inventory::inventory.fields.unit_cost'))
                    ->formatStateUsing(fn ($state) => number_format(($state ?? 0) / 100, 2) . ' EGP')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('value')
                    ->label(__('inventory::inventory.fields.value'))
                    ->getStateUsing(fn (StockMovement $record) => abs($record->quantity) * ($record->unit_cost_minor ?? 0))
                    ->formatStateUsing(fn ($state) => number_format(($state ?? 0) / 100, 2) . ' EGP')
                    ->alignEnd()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('from_to')
                    ->label(__('inventory::inventory.fields.from_to'))
                    ->getStateUsing(function (StockMovement $record): string {
                        $from = $record->sourceLocation?->getTranslation('name', app()->getLocale())
                            ?? $record->sourceLocation?->code
                            ?? '-';
                        $to = $record->destinationLocation?->getTranslation('name', app()->getLocale())
                            ?? $record->destinationLocation?->code
                            ?? '-';
                        return "{$from} → {$to}";
                    }),

                Tables\Columns\TextColumn::make('quantity_before')
                    ->label(__('inventory::inventory.fields.before'))
                    ->numeric()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('quantity_after')
                    ->label(__('inventory::inventory.fields.after'))
                    ->numeric()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('reference_type')
                    ->label(__('inventory::inventory.fields.reference'))
                    ->formatStateUsing(function (StockMovement $record): string {
                        if (!$record->reference_type) {
                            return '-';
                        }
                        $type = class_basename($record->reference_type);
                        return $type . ($record->reference_id ? " #{$record->reference_id}" : '');
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('notes')
                    ->label(__('inventory::inventory.fields.notes'))
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label(__('inventory::inventory.fields.created_by'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('movement_type')
                    ->label(__('inventory::inventory.fields.movement_type'))
                    ->options(fn () => collect(StockMovement::TYPES)->mapWithKeys(
                        fn ($label, $value) => [$value => __("inventory::inventory.movement_types.{$value}")]
                    ))
                    ->multiple(),

                SelectFilter::make('product_id')
                    ->label(__('inventory::inventory.fields.product'))
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('branch_id')
                    ->label(__('inventory::inventory.fields.branch'))
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('incoming')
                    ->label(__('inventory::inventory.filters.incoming'))
                    ->query(fn (Builder $query): Builder => $query->incoming())
                    ->toggle(),

                Filter::make('outgoing')
                    ->label(__('inventory::inventory.filters.outgoing'))
                    ->query(fn (Builder $query): Builder => $query->outgoing())
                    ->toggle(),

                Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label(__('inventory::inventory.fields.from_date')),
                        Forms\Components\DatePicker::make('until')
                            ->label(__('inventory::inventory.fields.until_date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators['from'] = __('inventory::inventory.fields.from_date') . ': ' . $data['from'];
                        }
                        if ($data['until'] ?? null) {
                            $indicators['until'] = __('inventory::inventory.fields.until_date') . ': ' . $data['until'];
                        }
                        return $indicators;
                    }),
            ])
            ->filtersFormColumns(3)
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(false), // Disable delete for audit trail
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
            'index' => Pages\ListStockMovements::route('/'),
            'view' => Pages\ViewStockMovement::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Stock movements are created by other actions, not directly
    }

    public static function canEdit($record): bool
    {
        return false; // Stock movements should not be edited for audit trail
    }

    public static function canDelete($record): bool
    {
        return false; // Stock movements should not be deleted for audit trail
    }
}
