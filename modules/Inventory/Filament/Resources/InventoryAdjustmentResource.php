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

                        Forms\Components\Textarea::make('notes')
                            ->label(__('inventory::inventory.fields.notes'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
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
