<?php

namespace Modules\Inventory\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Modules\Core\Models\Branch;
use Modules\Inventory\Models\InventoryAdjustment;
use Modules\Inventory\Filament\Resources\InventoryAdjustmentResource\Pages;
use Modules\Inventory\Filament\Resources\InventoryAdjustmentResource\RelationManagers;

class InventoryAdjustmentResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = InventoryAdjustment::class;

    protected static ?string $moduleCode = 'inventory';

    protected static ?string $permissionKey = 'products';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('inventory::inventory.navigation.inventory_adjustments');
    }

    public static function getModelLabel(): string
    {
        return __('inventory::inventory.labels.inventory_adjustment');
    }

    public static function getPluralModelLabel(): string
    {
        return __('inventory::inventory.labels.inventory_adjustments');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('inventory::inventory.sections.adjustment_info'))
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('reference')
                                    ->label(__('inventory::inventory.fields.reference'))
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->placeholder('Auto-generated'),

                                Forms\Components\Select::make('branch_id')
                                    ->label(__('inventory::inventory.fields.branch'))
                                    ->options(Branch::pluck('name', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->disabled(fn (?InventoryAdjustment $record) => $record && !$record->isDraft()),

                                Forms\Components\Select::make('adjustment_type')
                                    ->label(__('inventory::inventory.fields.adjustment_type'))
                                    ->options(InventoryAdjustment::TYPES)
                                    ->default(InventoryAdjustment::TYPE_COUNT)
                                    ->required()
                                    ->disabled(fn (?InventoryAdjustment $record) => $record && !$record->isDraft()),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('adjustment_date')
                                    ->label(__('inventory::inventory.fields.adjustment_date'))
                                    ->default(now())
                                    ->required()
                                    ->disabled(fn (?InventoryAdjustment $record) => $record && !$record->isDraft()),

                                Forms\Components\Placeholder::make('status')
                                    ->label(__('inventory::inventory.fields.status'))
                                    ->content(fn (?InventoryAdjustment $record) => $record ? InventoryAdjustment::STATUSES[$record->status] ?? $record->status : 'Draft'),
                            ]),

                        Forms\Components\Textarea::make('reason')
                            ->label(__('inventory::inventory.fields.reason'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make(__('inventory::inventory.sections.adjustment_lines'))
                    ->schema([
                        Forms\Components\Repeater::make('lines')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label(__('inventory::inventory.fields.product'))
                                    ->relationship('product', 'name')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->sku . ' - ' . ($record->getTranslation('name', app()->getLocale()) ?? $record->name))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, $livewire) {
                                        if ($state) {
                                            $product = \Modules\Inventory\Models\Product::find($state);
                                            $branchId = $livewire->data['branch_id'] ?? null;

                                            if ($branchId) {
                                                $stockLevel = \Modules\Inventory\Models\StockLevel::where('product_id', $state)
                                                    ->where('branch_id', $branchId)
                                                    ->first();

                                                $theoreticalQty = $stockLevel?->quantity_on_hand ?? 0;
                                            } else {
                                                $theoreticalQty = 0;
                                            }

                                            $unitCost = $product?->cost_price_minor ?? 0;

                                            $set('theoretical_qty', $theoreticalQty);
                                            $set('counted_qty', $theoreticalQty);
                                            $set('unit_cost_minor', $unitCost);
                                            $set('difference_qty', 0);
                                            $set('value_adjustment_minor', 0);
                                        }
                                    })
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('theoretical_qty')
                                    ->label(__('inventory::inventory.fields.theoretical_qty'))
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('counted_qty')
                                    ->label(__('inventory::inventory.fields.counted_qty'))
                                    ->numeric()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                        $theoretical = $get('theoretical_qty') ?? 0;
                                        $counted = $state ?? 0;
                                        $unitCost = $get('unit_cost_minor') ?? 0;

                                        $difference = $counted - $theoretical;
                                        $valueAdjustment = $difference * $unitCost;

                                        $set('difference_qty', $difference);
                                        $set('value_adjustment_minor', $valueAdjustment);
                                    })
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('difference_qty')
                                    ->label(__('inventory::inventory.fields.difference'))
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->columnSpan(1),

                                Forms\Components\Hidden::make('unit_cost_minor'),
                                Forms\Components\Hidden::make('value_adjustment_minor'),
                            ])
                            ->columns(6)
                            ->defaultItems(0)
                            ->addActionLabel(__('inventory::inventory.actions.add_product'))
                            ->reorderable(false)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string =>
                                isset($state['product_id'])
                                    ? \Modules\Inventory\Models\Product::find($state['product_id'])?->sku
                                    : null
                            )
                            ->visible(fn (?InventoryAdjustment $record) => $record !== null)
                            ->disabled(fn (?InventoryAdjustment $record) => $record && !$record->isDraft()),
                    ])
                    ->visible(fn (?InventoryAdjustment $record) => $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label(__('inventory::inventory.fields.reference'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('inventory::inventory.fields.branch'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('adjustment_type')
                    ->label(__('inventory::inventory.fields.adjustment_type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => InventoryAdjustment::TYPES[$state] ?? $state)
                    ->color(fn ($state) => InventoryAdjustment::TYPE_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('adjustment_date')
                    ->label(__('inventory::inventory.fields.adjustment_date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('lines_count')
                    ->label(__('inventory::inventory.fields.products'))
                    ->counts('lines')
                    ->badge(),

                Tables\Columns\TextColumn::make('total_value_adjustment')
                    ->label(__('inventory::inventory.fields.value_adjustment'))
                    ->money(current_currency())
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('inventory::inventory.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => InventoryAdjustment::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => InventoryAdjustment::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label(__('inventory::inventory.fields.created_by'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('inventory::inventory.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('inventory::inventory.fields.branch'))
                    ->relationship('branch', 'name'),

                Tables\Filters\SelectFilter::make('adjustment_type')
                    ->label(__('inventory::inventory.fields.adjustment_type'))
                    ->options(InventoryAdjustment::TYPES),

                Tables\Filters\SelectFilter::make('status')
                    ->label(__('inventory::inventory.fields.status'))
                    ->options(InventoryAdjustment::STATUSES),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (InventoryAdjustment $record) => $record->isDraft()),

                Tables\Actions\Action::make('validate')
                    ->label(__('inventory::inventory.actions.validate'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('inventory::inventory.actions.validate_adjustment'))
                    ->modalDescription(__('inventory::inventory.messages.validate_confirmation'))
                    ->visible(fn (InventoryAdjustment $record) => $record->canValidate())
                    ->action(function (InventoryAdjustment $record) {
                        if ($record->validate()) {
                            Notification::make()
                                ->title(__('inventory::inventory.messages.adjustment_validated'))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('inventory::inventory.messages.validation_failed'))
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label(__('inventory::inventory.actions.cancel'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (InventoryAdjustment $record) => $record->canCancel())
                    ->action(function (InventoryAdjustment $record) {
                        $record->cancel();
                        Notification::make()
                            ->title(__('inventory::inventory.messages.adjustment_cancelled'))
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('inventory::inventory.sections.adjustment_info'))
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('reference')
                                    ->label(__('inventory::inventory.fields.reference')),

                                Infolists\Components\TextEntry::make('branch.name')
                                    ->label(__('inventory::inventory.fields.branch')),

                                Infolists\Components\TextEntry::make('adjustment_type')
                                    ->label(__('inventory::inventory.fields.adjustment_type'))
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => InventoryAdjustment::TYPES[$state] ?? $state)
                                    ->color(fn ($state) => InventoryAdjustment::TYPE_COLORS[$state] ?? 'gray'),

                                Infolists\Components\TextEntry::make('status')
                                    ->label(__('inventory::inventory.fields.status'))
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => InventoryAdjustment::STATUSES[$state] ?? $state)
                                    ->color(fn ($state) => InventoryAdjustment::STATUS_COLORS[$state] ?? 'gray'),
                            ]),

                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('adjustment_date')
                                    ->label(__('inventory::inventory.fields.adjustment_date'))
                                    ->date(),

                                Infolists\Components\TextEntry::make('total_value_adjustment')
                                    ->label(__('inventory::inventory.fields.value_adjustment'))
                                    ->money(current_currency())
                                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),

                                Infolists\Components\TextEntry::make('journalEntry.entry_number')
                                    ->label(__('inventory::inventory.fields.journal_entry'))
                                    ->placeholder('Not created'),
                            ]),

                        Infolists\Components\TextEntry::make('reason')
                            ->label(__('inventory::inventory.fields.reason'))
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('notes')
                            ->label(__('inventory::inventory.fields.notes'))
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make(__('inventory::inventory.sections.adjustment_lines'))
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('lines')
                            ->schema([
                                Infolists\Components\TextEntry::make('product.sku')
                                    ->label(__('inventory::inventory.fields.sku')),

                                Infolists\Components\TextEntry::make('product.name')
                                    ->label(__('inventory::inventory.fields.product'))
                                    ->getStateUsing(fn ($record) => $record->product?->getTranslation('name', app()->getLocale())),

                                Infolists\Components\TextEntry::make('theoretical_qty')
                                    ->label(__('inventory::inventory.fields.theoretical_qty'))
                                    ->alignCenter(),

                                Infolists\Components\TextEntry::make('counted_qty')
                                    ->label(__('inventory::inventory.fields.counted_qty'))
                                    ->alignCenter(),

                                Infolists\Components\TextEntry::make('difference_qty')
                                    ->label(__('inventory::inventory.fields.difference'))
                                    ->alignCenter()
                                    ->color(fn ($state) => match(true) {
                                        $state > 0 => 'success',
                                        $state < 0 => 'danger',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn ($state) => $state > 0 ? "+{$state}" : $state),

                                Infolists\Components\TextEntry::make('value_adjustment')
                                    ->label(__('inventory::inventory.fields.value_adjustment'))
                                    ->money(current_currency())
                                    ->color(fn ($record) => match(true) {
                                        $record->value_adjustment_minor > 0 => 'success',
                                        $record->value_adjustment_minor < 0 => 'danger',
                                        default => 'gray',
                                    }),
                            ])
                            ->columns(6),
                    ]),

                Infolists\Components\Section::make(__('inventory::inventory.sections.validation_info'))
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('validatedBy.name')
                                    ->label(__('inventory::inventory.fields.validated_by')),

                                Infolists\Components\TextEntry::make('validated_at')
                                    ->label(__('inventory::inventory.fields.validated_at'))
                                    ->dateTime(),

                                Infolists\Components\TextEntry::make('createdBy.name')
                                    ->label(__('inventory::inventory.fields.created_by')),
                            ]),
                    ])
                    ->visible(fn (InventoryAdjustment $record) => $record->isValidated()),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryAdjustments::route('/'),
            'create' => Pages\CreateInventoryAdjustment::route('/create'),
            'view' => Pages\ViewInventoryAdjustment::route('/{record}'),
            'edit' => Pages\EditInventoryAdjustment::route('/{record}/edit'),
        ];
    }
}
