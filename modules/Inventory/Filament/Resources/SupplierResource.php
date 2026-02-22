<?php

namespace Modules\Inventory\Filament\Resources;

use App\Traits\ChecksTenantModuleAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Filament\Resources\SupplierResource\Pages;
use Modules\Inventory\Filament\Resources\SupplierResource\RelationManagers;

class SupplierResource extends Resource
{
    use ChecksTenantModuleAccess;

    protected static ?string $model = Supplier::class;

    protected static ?string $moduleCode = 'inventory';

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('inventory::inventory.navigation.suppliers');
    }

    public static function getModelLabel(): string
    {
        return __('inventory::inventory.labels.supplier');
    }

    public static function getPluralModelLabel(): string
    {
        return __('inventory::inventory.labels.suppliers');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('inventory::inventory.sections.basic_info'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('code')
                                    ->label(__('inventory::inventory.fields.code'))
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->placeholder('Auto-generated'),

                                Forms\Components\Toggle::make('is_active')
                                    ->label(__('inventory::inventory.fields.is_active'))
                                    ->default(true),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name.en')
                                    ->label(__('inventory::inventory.fields.name') . ' (English)')
                                    ->required()
                                    ->maxLength(200),

                                Forms\Components\TextInput::make('name.ar')
                                    ->label(__('inventory::inventory.fields.name') . ' (Arabic)')
                                    ->required()
                                    ->maxLength(200),
                            ]),
                    ]),

                Forms\Components\Section::make(__('inventory::inventory.sections.contact'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('contact_person')
                                    ->label(__('inventory::inventory.fields.contact_person'))
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('email')
                                    ->label(__('inventory::inventory.fields.email'))
                                    ->email()
                                    ->maxLength(100),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('phone')
                                    ->label(__('inventory::inventory.fields.phone'))
                                    ->tel()
                                    ->maxLength(30),

                                Forms\Components\TextInput::make('mobile')
                                    ->label(__('inventory::inventory.fields.mobile'))
                                    ->tel()
                                    ->maxLength(30),
                            ]),
                    ]),

                Forms\Components\Section::make(__('inventory::inventory.sections.address'))
                    ->schema([
                        Forms\Components\Textarea::make('address')
                            ->label(__('inventory::inventory.fields.address'))
                            ->rows(2),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('city')
                                    ->label(__('inventory::inventory.fields.city'))
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('country')
                                    ->label(__('inventory::inventory.fields.country'))
                                    ->maxLength(100)
                                    ->default('Egypt'),
                            ]),
                    ]),

                Forms\Components\Section::make(__('inventory::inventory.sections.financial'))
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('tax_number')
                                    ->label(__('inventory::inventory.fields.tax_number'))
                                    ->maxLength(50),

                                Forms\Components\TextInput::make('payment_terms_days')
                                    ->label(__('inventory::inventory.fields.payment_terms'))
                                    ->numeric()
                                    ->default(30)
                                    ->suffix('days'),

                                Forms\Components\Select::make('currency_code')
                                    ->label(__('inventory::inventory.fields.currency'))
                                    ->options([
                                        'EGP' => 'EGP - Egyptian Pound',
                                        'USD' => 'USD - US Dollar',
                                        'EUR' => 'EUR - Euro',
                                        'SAR' => 'SAR - Saudi Riyal',
                                        'AED' => 'AED - UAE Dirham',
                                    ])
                                    ->default('EGP'),
                            ]),
                    ]),

                Forms\Components\Section::make(__('inventory::inventory.sections.notes'))
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label(__('inventory::inventory.fields.notes'))
                            ->rows(3),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('inventory::inventory.fields.code'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('inventory::inventory.fields.name'))
                    ->getStateUsing(fn (Supplier $record) => $record->getTranslation('name', app()->getLocale()))
                    ->searchable(['name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('contact_person')
                    ->label(__('inventory::inventory.fields.contact_person'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label(__('inventory::inventory.fields.phone'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label(__('inventory::inventory.fields.email'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('purchase_orders_count')
                    ->label(__('inventory::inventory.fields.orders'))
                    ->counts('purchaseOrders')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('inventory::inventory.fields.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('inventory::inventory.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('inventory::inventory.fields.is_active')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PurchaseOrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'view' => Pages\ViewSupplier::route('/{record}'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
