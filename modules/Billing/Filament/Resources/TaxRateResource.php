<?php

namespace Modules\Billing\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Billing\Filament\Resources\TaxRateResource\Pages;
use Modules\Billing\Models\TaxRate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TaxRateResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = TaxRate::class;

    protected static ?string $moduleCode = 'billing';

    protected static ?string $permissionKey = 'settings';

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 50;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('billing::billing.tax_rates');
    }

    public static function getModelLabel(): string
    {
        return __('billing::billing.tax_rate');
    }

    public static function getPluralModelLabel(): string
    {
        return __('billing::billing.tax_rates');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name.en')
                                    ->label(__('billing::billing.tax_resource.tax_name') . ' (English)')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('name.ar')
                                    ->label(__('billing::billing.tax_resource.tax_name') . ' (Arabic)')
                                    ->maxLength(255),
                            ]),

                        Forms\Components\TextInput::make('rate')
                            ->label(__('billing::billing.tax_resource.rate'))
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->step(0.01),

                        Forms\Components\Toggle::make('is_default')
                            ->label(__('billing::billing.tax_resource.default_tax_rate'))
                            ->helperText(__('billing::billing.tax_resource.default_help')),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('billing::billing.tax_resource.active'))
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('translated_name')
                    ->label(__('billing::billing.tax_resource.tax_name'))
                    ->searchable(['name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('rate')
                    ->label(__('billing::billing.tax_resource.rate'))
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_default')
                    ->label(__('billing::billing.tax_resource.default'))
                    ->boolean(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label(__('billing::billing.tax_resource.active')),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('billing::billing.tax_resource.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('billing::billing.tax_resource.active')),
            ])
            ->actions([
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
            'index' => Pages\ListTaxRates::route('/'),
            'create' => Pages\CreateTaxRate::route('/create'),
            'edit' => Pages\EditTaxRate::route('/{record}/edit'),
        ];
    }
}
