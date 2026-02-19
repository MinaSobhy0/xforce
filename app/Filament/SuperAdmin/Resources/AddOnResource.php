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

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationGroup = 'Plans & Modules';

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

                Forms\Components\Section::make('Pricing')
                    ->schema([
                        Forms\Components\TextInput::make('monthly_price')
                            ->label('Monthly Price (EGP)')
                            ->numeric()
                            ->prefix('EGP')
                            ->required()
                            ->default(0),

                        Forms\Components\TextInput::make('yearly_price')
                            ->label('Yearly Price (EGP)')
                            ->numeric()
                            ->prefix('EGP')
                            ->required()
                            ->default(0)
                            ->helperText('Usually 10-20% discount from monthly * 12'),

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

                Tables\Columns\TextColumn::make('monthly_price')
                    ->label('Monthly')
                    ->money('EGP')
                    ->sortable(),

                Tables\Columns\TextColumn::make('yearly_price')
                    ->label('Yearly')
                    ->money('EGP')
                    ->sortable(),

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
