<?php

namespace Modules\Billing\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Accounting\Models\ChartOfAccount;
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

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('rate')
                                    ->label(__('billing::billing.tax_resource.rate'))
                                    ->numeric()
                                    ->required()
                                    ->minValue(-100)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->step(0.01)
                                    ->helperText(__('billing::billing.tax_resource.rate_help')),

                                Forms\Components\Select::make('type')
                                    ->label(__('billing::billing.tax_resource.type'))
                                    ->options([
                                        TaxRate::TYPE_SALES => __('billing::billing.tax_resource.type_sales'),
                                        TaxRate::TYPE_PURCHASE => __('billing::billing.tax_resource.type_purchase'),
                                    ])
                                    ->default(TaxRate::TYPE_SALES)
                                    ->required(),
                            ]),

                        Forms\Components\Select::make('account_id')
                            ->label(__('billing::billing.tax_resource.account'))
                            ->relationship('account', 'code', fn ($query) => $query->where('is_active', true)->orderBy('code'))
                            ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->code} - {$record->translated_name}")
                            ->getSearchResultsUsing(function (string $search) {
                                return ChartOfAccount::query()
                                    ->where('is_active', true)
                                    ->where(function ($query) use ($search) {
                                        $query->where('code', 'ilike', "%{$search}%")
                                            ->orWhereRaw("name->>'en' ILIKE ?", ["%{$search}%"])
                                            ->orWhereRaw("name->>'ar' ILIKE ?", ["%{$search}%"]);
                                    })
                                    ->orderBy('code')
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn ($account) => [$account->id => "{$account->code} - {$account->translated_name}"]);
                            })
                            ->searchable()
                            ->preload()
                            ->helperText(__('billing::billing.tax_resource.account_help')),

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
                    ->searchable(query: function ($query, string $search) {
                        $query->where(function ($q) use ($search) {
                            $q->whereRaw("name->>'en' ILIKE ?", ["%{$search}%"])
                              ->orWhereRaw("name->>'ar' ILIKE ?", ["%{$search}%"]);
                        });
                    })
                    ->sortable(query: function ($query, string $direction) {
                        $query->orderBy('rate', $direction);
                    }),

                Tables\Columns\TextColumn::make('rate')
                    ->label(__('billing::billing.tax_resource.rate'))
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('billing::billing.tax_resource.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        TaxRate::TYPE_SALES => __('billing::billing.tax_resource.type_sales'),
                        TaxRate::TYPE_PURCHASE => __('billing::billing.tax_resource.type_purchase'),
                        default => $state,
                    })
                    ->color(fn (string $state): string => match($state) {
                        TaxRate::TYPE_SALES => 'success',
                        TaxRate::TYPE_PURCHASE => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('account.code')
                    ->label(__('billing::billing.tax_resource.account'))
                    ->placeholder('-')
                    ->toggleable(),

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
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('billing::billing.tax_resource.type'))
                    ->options([
                        TaxRate::TYPE_SALES => __('billing::billing.tax_resource.type_sales'),
                        TaxRate::TYPE_PURCHASE => __('billing::billing.tax_resource.type_purchase'),
                    ]),

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
