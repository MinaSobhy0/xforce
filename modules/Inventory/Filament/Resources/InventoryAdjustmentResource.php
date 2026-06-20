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
use Modules\Inventory\Models\StockLocation;
use Modules\Inventory\Filament\Resources\InventoryAdjustmentResource\Pages;
use Modules\Inventory\Filament\Resources\InventoryAdjustmentResource\RelationManagers;

class InventoryAdjustmentResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = InventoryAdjustment::class;

    protected static ?string $moduleCode = 'inventory';

    protected static ?string $permissionKey = 'inventory_adjustments';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'reference';

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
                        Forms\Components\Grid::make(4)
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
                                    ->default(fn () => current_branch_id())
                                    ->live()
                                    ->afterStateUpdated(fn (Forms\Set $set) => $set('location_id', null))
                                    ->disabled(fn (?InventoryAdjustment $record) => $record !== null),

                                Forms\Components\Select::make('location_id')
                                    ->label(__('inventory::inventory.fields.location'))
                                    ->options(function (Forms\Get $get) {
                                        $branchId = $get('branch_id');
                                        if (!$branchId) return [];
                                        return StockLocation::where('branch_id', $branchId)
                                            ->where('location_type', StockLocation::TYPE_INTERNAL)
                                            ->active()
                                            ->orderBy('sort_order')
                                            ->get()
                                            ->pluck('indented_name', 'id');
                                    })
                                    ->default(function () {
                                        $branchId = current_branch_id();
                                        if (!$branchId) return null;
                                        return StockLocation::where('branch_id', $branchId)
                                            ->where('location_type', StockLocation::TYPE_INTERNAL)
                                            ->active()
                                            ->orderBy('sort_order')
                                            ->first()?->id;
                                    })
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->disabled(fn (?InventoryAdjustment $record) => $record !== null),

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

                // Adjustment lines are managed via the RelationManager (paginated table) on edit page
                // This placeholder shows summary information
                Forms\Components\Section::make(__('inventory::inventory.sections.adjustment_summary'))
                    ->schema([
                        Forms\Components\Placeholder::make('lines_count')
                            ->label(__('inventory::inventory.fields.products'))
                            ->content(fn (?InventoryAdjustment $record) => $record?->lines()->count() ?? 0),

                        Forms\Components\Placeholder::make('total_adjustment')
                            ->label(__('inventory::inventory.fields.total_value_adjustment'))
                            ->content(function (?InventoryAdjustment $record) {
                                if (!$record) return '-';
                                $total = $record->total_value_adjustment;
                                $color = $total > 0 ? 'text-green-600' : ($total < 0 ? 'text-red-600' : 'text-gray-500');
                                $prefix = $total > 0 ? '+' : '';
                                return new \Illuminate\Support\HtmlString(
                                    "<span class=\"text-lg font-bold {$color}\">{$prefix}" . number_format($total, 2) . ' ' . current_currency() . "</span>"
                                );
                            }),
                    ])
                    ->columns(2)
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

                Tables\Columns\TextColumn::make('location.code')
                    ->label(__('inventory::inventory.fields.location'))
                    ->description(fn (InventoryAdjustment $record) => $record->location?->getTranslation('name', app()->getLocale()))
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
                        Infolists\Components\Grid::make(5)
                            ->schema([
                                Infolists\Components\TextEntry::make('reference')
                                    ->label(__('inventory::inventory.fields.reference')),

                                Infolists\Components\TextEntry::make('branch.name')
                                    ->label(__('inventory::inventory.fields.branch')),

                                Infolists\Components\TextEntry::make('location.full_path_name')
                                    ->label(__('inventory::inventory.fields.location')),

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

                                Infolists\Components\TextEntry::make('journalEntry.code')
                                    ->label(__('inventory::inventory.fields.journal_entry'))
                                    ->placeholder(__('inventory::inventory.messages.not_created'))
                                    ->url(fn ($record) => $record->journal_entry_id
                                        ? \Modules\Accounting\Filament\Resources\JournalEntryResource::getUrl('view', ['record' => $record->journal_entry_id])
                                        : null)
                                    ->color('primary'),
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

                                Infolists\Components\TextEntry::make('uom.abbreviation')
                                    ->label(__('inventory::inventory.fields.uom'))
                                    ->placeholder('-'),

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
                            ->columns(7),
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
        return [
            RelationManagers\LinesRelationManager::class,
        ];
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
