<?php

namespace Modules\Inventory\Filament\Resources;

use App\Traits\ChecksTenantModuleAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Modules\Core\Models\Branch;
use Modules\Inventory\Models\PurchaseOrder;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Filament\Resources\PurchaseOrderResource\Pages;
use Modules\Inventory\Filament\Resources\PurchaseOrderResource\RelationManagers;

class PurchaseOrderResource extends Resource
{
    use ChecksTenantModuleAccess;

    protected static ?string $model = PurchaseOrder::class;

    protected static ?string $moduleCode = 'inventory';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 31;

    public static function getNavigationLabel(): string
    {
        return __('inventory::inventory.navigation.purchase_orders');
    }

    public static function getModelLabel(): string
    {
        return __('inventory::inventory.labels.purchase_order');
    }

    public static function getPluralModelLabel(): string
    {
        return __('inventory::inventory.labels.purchase_orders');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('inventory::inventory.sections.order_info'))
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('order_number')
                                    ->label(__('inventory::inventory.fields.order_number'))
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->placeholder('Auto-generated'),

                                Forms\Components\Select::make('status')
                                    ->label(__('inventory::inventory.fields.status'))
                                    ->options(PurchaseOrder::STATUSES)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->default(PurchaseOrder::STATUS_DRAFT),

                                Forms\Components\DatePicker::make('order_date')
                                    ->label(__('inventory::inventory.fields.order_date'))
                                    ->required()
                                    ->default(now()),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('supplier_id')
                                    ->label(__('inventory::inventory.fields.supplier'))
                                    ->relationship('supplier', 'id')
                                    ->getOptionLabelFromRecordUsing(fn (Supplier $record) => $record->getTranslation('name', app()->getLocale()))
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name.en')
                                            ->label('Name (English)')
                                            ->required(),
                                        Forms\Components\TextInput::make('name.ar')
                                            ->label('Name (Arabic)')
                                            ->required(),
                                        Forms\Components\TextInput::make('phone')
                                            ->label('Phone'),
                                    ]),

                                Forms\Components\Select::make('branch_id')
                                    ->label(__('inventory::inventory.fields.branch'))
                                    ->relationship('branch', 'id')
                                    ->getOptionLabelFromRecordUsing(fn (Branch $record) => $record->name)
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->default(fn () => current_branch_id())
                                    ->disabled(fn () => current_branch_id() !== null)
                                    ->dehydrated(),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('expected_date')
                                    ->label(__('inventory::inventory.fields.expected_date')),

                                Forms\Components\DatePicker::make('received_date')
                                    ->label(__('inventory::inventory.fields.received_date'))
                                    ->disabled(),
                            ]),
                    ]),

                Forms\Components\Section::make(__('inventory::inventory.sections.items'))
                    ->schema([
                        Forms\Components\Repeater::make('lines')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label(__('inventory::inventory.fields.product'))
                                    ->relationship('product', 'id')
                                    ->getOptionLabelFromRecordUsing(fn (Product $record) => "[{$record->sku}] " . $record->getTranslation('name', app()->getLocale()))
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            if ($product) {
                                                $set('unit_price_minor', $product->cost_price_minor);
                                            }
                                        }
                                    })
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('quantity')
                                    ->label(__('inventory::inventory.fields.quantity'))
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->minValue(1)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('unit_price_minor')
                                    ->label(__('inventory::inventory.fields.unit_price'))
                                    ->numeric()
                                    ->required()
                                    ->suffix('cents')
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('quantity_received')
                                    ->label(__('inventory::inventory.fields.received'))
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->default(0)
                                    ->columnSpan(1),

                                Forms\Components\Textarea::make('notes')
                                    ->label(__('inventory::inventory.fields.notes'))
                                    ->rows(1)
                                    ->columnSpan(5),
                            ])
                            ->columns(7)
                            ->defaultItems(1)
                            ->addActionLabel(__('inventory::inventory.actions.add_item'))
                            ->reorderable(false),
                    ]),

                Forms\Components\Section::make(__('inventory::inventory.sections.totals'))
                    ->schema([
                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\TextInput::make('tax_amount_minor')
                                    ->label(__('inventory::inventory.fields.tax'))
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('cents'),

                                Forms\Components\TextInput::make('discount_amount_minor')
                                    ->label(__('inventory::inventory.fields.discount'))
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('cents'),

                                Forms\Components\TextInput::make('shipping_amount_minor')
                                    ->label(__('inventory::inventory.fields.shipping'))
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('cents'),

                                Forms\Components\Placeholder::make('total_display')
                                    ->label(__('inventory::inventory.fields.total'))
                                    ->content(fn (?PurchaseOrder $record) => $record ? number_format($record->total_amount, 2) . ' EGP' : '-'),
                            ]),
                    ]),

                Forms\Components\Section::make(__('inventory::inventory.sections.notes'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Textarea::make('notes')
                                    ->label(__('inventory::inventory.fields.notes'))
                                    ->helperText('Visible to supplier')
                                    ->rows(3),

                                Forms\Components\Textarea::make('internal_notes')
                                    ->label(__('inventory::inventory.fields.internal_notes'))
                                    ->helperText('Internal use only')
                                    ->rows(3),
                            ]),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label(__('inventory::inventory.fields.order_number'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label(__('inventory::inventory.fields.supplier'))
                    ->getStateUsing(fn (PurchaseOrder $record) => $record->supplier?->getTranslation('name', app()->getLocale()))
                    ->searchable(['name']),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('inventory::inventory.fields.branch'))
                    ->getStateUsing(fn (PurchaseOrder $record) => $record->branch?->name),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('inventory::inventory.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => PurchaseOrder::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => PurchaseOrder::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('order_date')
                    ->label(__('inventory::inventory.fields.order_date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('expected_date')
                    ->label(__('inventory::inventory.fields.expected_date'))
                    ->date()
                    ->sortable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label(__('inventory::inventory.fields.total'))
                    ->money(current_currency())
                    ->sortable(),

                Tables\Columns\TextColumn::make('lines_count')
                    ->label(__('inventory::inventory.fields.items'))
                    ->counts('lines'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('inventory::inventory.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('inventory::inventory.fields.status'))
                    ->options(PurchaseOrder::STATUSES),

                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label(__('inventory::inventory.fields.supplier'))
                    ->relationship('supplier', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Supplier $record) => $record->getTranslation('name', app()->getLocale())),

                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('inventory::inventory.fields.branch'))
                    ->relationship('branch', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Branch $record) => $record->name),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (PurchaseOrder $record) => $record->isEditable()),

                Tables\Actions\Action::make('send')
                    ->label(__('inventory::inventory.actions.send'))
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn (PurchaseOrder $record) => $record->canTransitionTo(PurchaseOrder::STATUS_SENT))
                    ->requiresConfirmation()
                    ->action(function (PurchaseOrder $record) {
                        if ($record->send(auth()->id())) {
                            Notification::make()
                                ->title(__('inventory::inventory.messages.order_sent'))
                                ->success()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label(__('inventory::inventory.actions.cancel'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (PurchaseOrder $record) => $record->canTransitionTo(PurchaseOrder::STATUS_CANCELLED))
                    ->requiresConfirmation()
                    ->action(function (PurchaseOrder $record) {
                        if ($record->cancel()) {
                            Notification::make()
                                ->title(__('inventory::inventory.messages.order_cancelled'))
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => false), // Disable bulk delete for POs
                ]),
            ])
            ->defaultSort('order_date', 'desc');
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
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'view' => Pages\ViewPurchaseOrder::route('/{record}'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
            'receive' => Pages\ReceivePurchaseOrder::route('/{record}/receive'),
        ];
    }
}
