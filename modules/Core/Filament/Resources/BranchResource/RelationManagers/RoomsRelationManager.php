<?php

namespace Modules\Core\Filament\Resources\BranchResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RoomsRelationManager extends RelationManager
{
    protected static string $relationship = 'rooms';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->label(__('core::core.code'))
                    ->maxLength(20)
                    ->columnSpan(1),

                Forms\Components\TextInput::make('name')
                    ->label(__('core::core.name'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(1),

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
                    ->columnSpan(1),

                Forms\Components\TextInput::make('sort_order')
                    ->label(__('core::core.sort_order'))
                    ->numeric()
                    ->default(0)
                    ->columnSpan(1),
            ])
            ->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('core::core.code'))
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('core::core.name'))
                    ->searchable()
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
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
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
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
}
