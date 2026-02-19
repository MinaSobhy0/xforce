<?php

namespace Modules\Auth\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ActivityLogRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $recordTitleAttribute = 'description';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('description')
                    ->label(__('Activity'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label(__('Performed By'))
                    ->default(__('System')),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label(__('Subject'))
                    ->formatStateUsing(fn ($state) => class_basename($state)),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\ViewColumn::make('properties')
                    ->label(__('Details'))
                    ->view('filament.tables.columns.activity-properties')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('log_name')
                    ->label(__('Log Type'))
                    ->options([
                        'default' => __('Default'),
                        'auth' => __('Authentication'),
                        'profile' => __('Profile Changes'),
                        'admin' => __('Administrative'),
                    ]),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label(__('From Date')),
                        Forms\Components\DatePicker::make('created_until')
                            ->label(__('Until Date')),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['created_from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->form([
                        Forms\Components\TextInput::make('description')
                            ->label(__('Description'))
                            ->disabled(),

                        Forms\Components\TextInput::make('log_name')
                            ->label(__('Log Name'))
                            ->disabled(),

                        Forms\Components\TextInput::make('causer.name')
                            ->label(__('Performed By'))
                            ->disabled(),

                        Forms\Components\Textarea::make('properties')
                            ->label(__('Properties'))
                            ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT))
                            ->disabled()
                            ->rows(10),
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}