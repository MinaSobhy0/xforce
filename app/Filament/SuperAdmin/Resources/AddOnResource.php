<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\AddOnResource\Pages;
use App\Models\AddOn;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AddOnResource extends Resource
{
    protected static ?string $model = AddOn::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-plus';

    protected static ?string $navigationGroup = 'Financials';

    protected static ?string $navigationLabel = 'Add-Ons';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->helperText('Unique identifier for the add-on'),

                        Forms\Components\TextInput::make('name')
                            ->label('Name (English)')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('name_ar')
                            ->label('Name (Arabic)')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('icon')
                            ->label('Icon (Heroicon name)')
                            ->placeholder('heroicon-o-puzzle-piece')
                            ->maxLength(100),

                        Forms\Components\Textarea::make('description')
                            ->label('Description (English)')
                            ->rows(3),

                        Forms\Components\Textarea::make('description_ar')
                            ->label('Description (Arabic)')
                            ->rows(3),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Billing Settings')
                    ->schema([
                        Forms\Components\Toggle::make('is_recurring')
                            ->label('Recurring billing')
                            ->default(true)
                            ->helperText('Charge periodically or one-time'),

                        Forms\Components\Select::make('billing_interval')
                            ->options([
                                'monthly' => 'Monthly',
                                'yearly' => 'Yearly',
                                'one-time' => 'One-time',
                            ])
                            ->default('monthly'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Country Pricing')
                    ->description('Set prices for each country. Leave empty to use Egypt (EGP) price as fallback.')
                    ->schema([
                        Forms\Components\Tabs::make('Country Prices')
                            ->tabs([
                                Forms\Components\Tabs\Tab::make('Egypt (EGP)')
                                    ->schema([
                                        Forms\Components\TextInput::make('prices.EG.monthly')
                                            ->label('Monthly')
                                            ->numeric()
                                            ->prefix('EGP')
                                            ->required()
                                            ->default(0),
                                        Forms\Components\TextInput::make('prices.EG.yearly')
                                            ->label('Yearly')
                                            ->numeric()
                                            ->prefix('EGP')
                                            ->required()
                                            ->default(0),
                                        Forms\Components\Hidden::make('prices.EG.currency')->default('EGP'),
                                    ])
                                    ->columns(2),

                                Forms\Components\Tabs\Tab::make('Saudi Arabia (SAR)')
                                    ->schema([
                                        Forms\Components\TextInput::make('prices.SA.monthly')
                                            ->label('Monthly')
                                            ->numeric()
                                            ->prefix('SAR'),
                                        Forms\Components\TextInput::make('prices.SA.yearly')
                                            ->label('Yearly')
                                            ->numeric()
                                            ->prefix('SAR'),
                                        Forms\Components\Hidden::make('prices.SA.currency')->default('SAR'),
                                    ])
                                    ->columns(2),

                                Forms\Components\Tabs\Tab::make('UAE (AED)')
                                    ->schema([
                                        Forms\Components\TextInput::make('prices.AE.monthly')
                                            ->label('Monthly')
                                            ->numeric()
                                            ->prefix('AED'),
                                        Forms\Components\TextInput::make('prices.AE.yearly')
                                            ->label('Yearly')
                                            ->numeric()
                                            ->prefix('AED'),
                                        Forms\Components\Hidden::make('prices.AE.currency')->default('AED'),
                                    ])
                                    ->columns(2),

                                Forms\Components\Tabs\Tab::make('Kuwait (KWD)')
                                    ->schema([
                                        Forms\Components\TextInput::make('prices.KW.monthly')
                                            ->label('Monthly')
                                            ->numeric()
                                            ->prefix('KWD'),
                                        Forms\Components\TextInput::make('prices.KW.yearly')
                                            ->label('Yearly')
                                            ->numeric()
                                            ->prefix('KWD'),
                                        Forms\Components\Hidden::make('prices.KW.currency')->default('KWD'),
                                    ])
                                    ->columns(2),

                                Forms\Components\Tabs\Tab::make('Qatar (QAR)')
                                    ->schema([
                                        Forms\Components\TextInput::make('prices.QA.monthly')
                                            ->label('Monthly')
                                            ->numeric()
                                            ->prefix('QAR'),
                                        Forms\Components\TextInput::make('prices.QA.yearly')
                                            ->label('Yearly')
                                            ->numeric()
                                            ->prefix('QAR'),
                                        Forms\Components\Hidden::make('prices.QA.currency')->default('QAR'),
                                    ])
                                    ->columns(2),

                                Forms\Components\Tabs\Tab::make('Bahrain (BHD)')
                                    ->schema([
                                        Forms\Components\TextInput::make('prices.BH.monthly')
                                            ->label('Monthly')
                                            ->numeric()
                                            ->prefix('BHD'),
                                        Forms\Components\TextInput::make('prices.BH.yearly')
                                            ->label('Yearly')
                                            ->numeric()
                                            ->prefix('BHD'),
                                        Forms\Components\Hidden::make('prices.BH.currency')->default('BHD'),
                                    ])
                                    ->columns(2),

                                Forms\Components\Tabs\Tab::make('Oman (OMR)')
                                    ->schema([
                                        Forms\Components\TextInput::make('prices.OM.monthly')
                                            ->label('Monthly')
                                            ->numeric()
                                            ->prefix('OMR'),
                                        Forms\Components\TextInput::make('prices.OM.yearly')
                                            ->label('Yearly')
                                            ->numeric()
                                            ->prefix('OMR'),
                                        Forms\Components\Hidden::make('prices.OM.currency')->default('OMR'),
                                    ])
                                    ->columns(2),

                                Forms\Components\Tabs\Tab::make('Jordan (JOD)')
                                    ->schema([
                                        Forms\Components\TextInput::make('prices.JO.monthly')
                                            ->label('Monthly')
                                            ->numeric()
                                            ->prefix('JOD'),
                                        Forms\Components\TextInput::make('prices.JO.yearly')
                                            ->label('Yearly')
                                            ->numeric()
                                            ->prefix('JOD'),
                                        Forms\Components\Hidden::make('prices.JO.currency')->default('JOD'),
                                    ])
                                    ->columns(2),

                                Forms\Components\Tabs\Tab::make('Lebanon (USD)')
                                    ->schema([
                                        Forms\Components\TextInput::make('prices.LB.monthly')
                                            ->label('Monthly')
                                            ->numeric()
                                            ->prefix('USD'),
                                        Forms\Components\TextInput::make('prices.LB.yearly')
                                            ->label('Yearly')
                                            ->numeric()
                                            ->prefix('USD'),
                                        Forms\Components\Hidden::make('prices.LB.currency')->default('USD'),
                                    ])
                                    ->columns(2),
                            ])
                            ->columnSpanFull(),
                    ]),

                // Keep legacy fields for backwards compatibility
                Forms\Components\Hidden::make('monthly_price')
                    ->dehydrateStateUsing(fn ($state, Forms\Get $get) => $get('prices.EG.monthly') ?? $state ?? 0),
                Forms\Components\Hidden::make('yearly_price')
                    ->dehydrateStateUsing(fn ($state, Forms\Get $get) => $get('prices.EG.yearly') ?? $state ?? 0),

                Forms\Components\Section::make('Features & Limits')
                    ->schema([
                        Forms\Components\KeyValue::make('features')
                            ->label('Features Provided')
                            ->keyLabel('Feature')
                            ->valueLabel('Value')
                            ->addActionLabel('Add Feature')
                            ->helperText('e.g., "extra_users" => "5"'),

                        Forms\Components\KeyValue::make('limits')
                            ->label('Additional Limits')
                            ->keyLabel('Limit')
                            ->valueLabel('Value')
                            ->addActionLabel('Add Limit')
                            ->helperText('e.g., "max_whatsapp" => "1000"'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('prices.EG.monthly')
                    ->label('Monthly (EGP)')
                    ->money('EGP')
                    // SECURITY: Validate sort direction to prevent SQL injection
                    ->sortable(query: fn ($query, $direction) => $query->orderByRaw(
                        "(prices->>'EG'->>'monthly')::numeric " . (strtolower($direction) === 'desc' ? 'DESC' : 'ASC')
                    )),

                Tables\Columns\TextColumn::make('prices.EG.yearly')
                    ->label('Yearly (EGP)')
                    ->money('EGP')
                    // SECURITY: Validate sort direction to prevent SQL injection
                    ->sortable(query: fn ($query, $direction) => $query->orderByRaw(
                        "(prices->>'EG'->>'yearly')::numeric " . (strtolower($direction) === 'desc' ? 'DESC' : 'ASC')
                    )),

                Tables\Columns\TextColumn::make('prices')
                    ->label('Countries')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state) . ' countries' : '0'),

                Tables\Columns\TextColumn::make('active_subscribers_count')
                    ->label('Subscribers')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('total_revenue')
                    ->label('Total Revenue')
                    ->money('EGP')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_recurring')
                    ->label('Recurring')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\TernaryFilter::make('is_recurring')
                    ->label('Recurring'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('view_subscribers')
                    ->label('View Subscribers')
                    ->icon('heroicon-o-users')
                    ->url(fn (AddOn $record) => route('filament.super-admin.resources.tenants.index', [
                        'tableFilters[add_on][value]' => $record->id,
                    ])),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAddOns::route('/'),
            'create' => Pages\CreateAddOn::route('/create'),
            'edit' => Pages\EditAddOn::route('/{record}/edit'),
        ];
    }
}
