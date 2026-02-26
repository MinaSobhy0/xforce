<?php

namespace Modules\Booking\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Booking\Filament\Resources\TimeOffTypeResource\Pages;
use Modules\Booking\Models\TimeOffType;

class TimeOffTypeResource extends Resource
{
    protected static ?string $model = TimeOffType::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 13;

    public static function getNavigationLabel(): string
    {
        return __('booking::time_off.types.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('booking::time_off.types.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('booking::time_off.types.plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('booking::time_off.types.sections.basic'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('booking::time_off.types.fields.name'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('code')
                            ->label(__('booking::time_off.types.fields.code'))
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->helperText(__('booking::time_off.types.help.code')),

                        Forms\Components\Textarea::make('description')
                            ->label(__('booking::time_off.types.fields.description'))
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('color')
                            ->label(__('booking::time_off.types.fields.color'))
                            ->options(TimeOffType::COLORS)
                            ->default('gray')
                            ->required(),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('booking::time_off.types.fields.is_active'))
                            ->default(true),

                        Forms\Components\TextInput::make('sort_order')
                            ->label(__('booking::time_off.types.fields.sort_order'))
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('booking::time_off.types.sections.settings'))
                    ->schema([
                        Forms\Components\Toggle::make('is_paid')
                            ->label(__('booking::time_off.types.fields.is_paid'))
                            ->default(true)
                            ->helperText(__('booking::time_off.types.help.is_paid')),

                        Forms\Components\Toggle::make('requires_approval')
                            ->label(__('booking::time_off.types.fields.requires_approval'))
                            ->default(true),

                        Forms\Components\TextInput::make('default_days_per_year')
                            ->label(__('booking::time_off.types.fields.default_days'))
                            ->numeric()
                            ->default(0)
                            ->helperText(__('booking::time_off.types.help.default_days')),

                        Forms\Components\TextInput::make('max_days_per_request')
                            ->label(__('booking::time_off.types.fields.max_days_per_request'))
                            ->numeric()
                            ->nullable()
                            ->helperText(__('booking::time_off.types.help.max_days')),

                        Forms\Components\TextInput::make('min_days_notice')
                            ->label(__('booking::time_off.types.fields.min_days_notice'))
                            ->numeric()
                            ->default(0)
                            ->helperText(__('booking::time_off.types.help.min_notice')),

                        Forms\Components\Toggle::make('allow_half_day')
                            ->label(__('booking::time_off.types.fields.allow_half_day'))
                            ->default(true),

                        Forms\Components\Toggle::make('allow_partial_day')
                            ->label(__('booking::time_off.types.fields.allow_partial_day'))
                            ->default(false)
                            ->helperText(__('booking::time_off.types.help.partial_day')),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('booking::time_off.types.fields.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label(__('booking::time_off.types.fields.code'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('color')
                    ->label(__('booking::time_off.types.fields.color'))
                    ->badge()
                    ->color(fn (string $state): string => $state),

                Tables\Columns\IconColumn::make('is_paid')
                    ->label(__('booking::time_off.types.fields.is_paid'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('default_days_per_year')
                    ->label(__('booking::time_off.types.fields.default_days'))
                    ->numeric()
                    ->alignEnd(),

                Tables\Columns\IconColumn::make('requires_approval')
                    ->label(__('booking::time_off.types.fields.requires_approval'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('booking::time_off.types.fields.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('booking::time_off.types.fields.sort_order'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('booking::time_off.types.fields.is_active')),

                Tables\Filters\TernaryFilter::make('is_paid')
                    ->label(__('booking::time_off.types.fields.is_paid')),
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
            'index' => Pages\ListTimeOffTypes::route('/'),
            'create' => Pages\CreateTimeOffType::route('/create'),
            'edit' => Pages\EditTimeOffType::route('/{record}/edit'),
        ];
    }
}
