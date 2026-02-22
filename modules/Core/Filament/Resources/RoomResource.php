<?php

namespace Modules\Core\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Core\Filament\Resources\RoomResource\Pages;
use Modules\Core\Models\Room;
use Modules\Core\Models\Branch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Resources\Concerns\Translatable;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RoomResource extends Resource
{
    use ChecksResourcePermissions;
    use Translatable;

    protected static ?string $model = Room::class;

    protected static ?string $moduleCode = 'core';

    protected static ?string $permissionKey = 'rooms';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('core::core.rooms');
    }

    public static function getModelLabel(): string
    {
        return __('core::core.room');
    }

    public static function getPluralModelLabel(): string
    {
        return __('core::core.rooms');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('core::core.room_details'))
                    ->schema([
                        Forms\Components\Select::make('branch_id')
                            ->label(__('core::core.branch'))
                            ->relationship('branch', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->default(fn () => current_branch_id())
                            ->disabled(fn () => current_branch_id() !== null)
                            ->dehydrated()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('code')
                            ->label(__('core::core.code'))
                            ->maxLength(20)
                            ->unique(ignoreRecord: true)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('name')
                            ->label(__('core::core.name'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Forms\Components\Textarea::make('description')
                            ->label(__('core::core.description'))
                            ->rows(2)
                            ->columnSpan(2),

                        Forms\Components\Select::make('room_type')
                            ->label(__('core::core.room_type'))
                            ->options([
                                'treatment' => __('core::core.room_types.treatment'),
                                'consultation' => __('core::core.room_types.consultation'),
                                'waiting' => __('core::core.room_types.waiting'),
                                'reception' => __('core::core.room_types.reception'),
                                'storage' => __('core::core.room_types.storage'),
                                'staff' => __('core::core.room_types.staff'),
                                'other' => __('core::core.room_types.other'),
                            ])
                            ->default('treatment')
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('capacity')
                            ->label(__('core::core.capacity'))
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(100)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('floor')
                            ->label(__('core::core.floor'))
                            ->maxLength(20)
                            ->columnSpan(1),

                        Forms\Components\ColorPicker::make('color')
                            ->label(__('core::core.color'))
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('core::core.active'))
                            ->default(true)
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('is_bookable')
                            ->label(__('core::core.bookable'))
                            ->default(true)
                            ->helperText(__('core::core.bookable_help'))
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('sort_order')
                            ->label(__('core::core.sort_order'))
                            ->numeric()
                            ->default(0)
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('core::core.additional_settings'))
                    ->schema([
                        Forms\Components\KeyValue::make('settings')
                            ->label(__('core::core.settings'))
                            ->keyLabel(__('core::core.setting_key'))
                            ->valueLabel(__('core::core.setting_value'))
                            ->columnSpan(2),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('core::core.code'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('core::core.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('core::core.branch'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('room_type')
                    ->label(__('core::core.room_type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("core::core.room_types.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'treatment' => 'success',
                        'consultation' => 'info',
                        'waiting' => 'warning',
                        'reception' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('capacity')
                    ->label(__('core::core.capacity'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('floor')
                    ->label(__('core::core.floor'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\ColorColumn::make('color')
                    ->label(__('core::core.color')),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('core::core.active'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_bookable')
                    ->label(__('core::core.bookable'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('core::core.sort_order'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('core::core.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('core::core.branch'))
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('room_type')
                    ->label(__('core::core.room_type'))
                    ->options([
                        'treatment' => __('core::core.room_types.treatment'),
                        'consultation' => __('core::core.room_types.consultation'),
                        'waiting' => __('core::core.room_types.waiting'),
                        'reception' => __('core::core.room_types.reception'),
                        'storage' => __('core::core.room_types.storage'),
                        'staff' => __('core::core.room_types.staff'),
                        'other' => __('core::core.room_types.other'),
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('core::core.active')),

                Tables\Filters\TernaryFilter::make('is_bookable')
                    ->label(__('core::core.bookable')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListRooms::route('/'),
            'create' => Pages\CreateRoom::route('/create'),
            'view' => Pages\ViewRoom::route('/{record}'),
            'edit' => Pages\EditRoom::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
