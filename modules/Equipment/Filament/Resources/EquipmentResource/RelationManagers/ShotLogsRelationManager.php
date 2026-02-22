<?php

namespace Modules\Equipment\Filament\Resources\EquipmentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ShotLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'shotLogs';

    protected static ?string $title = 'Shot Logs';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('shots_count')
                    ->label(__('equipment::equipment.shots_count'))
                    ->numeric()
                    ->required()
                    ->minValue(1),

                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\TextInput::make('energy_setting')
                            ->label(__('equipment::equipment.energy_setting')),

                        Forms\Components\TextInput::make('spot_size')
                            ->label(__('equipment::equipment.spot_size')),

                        Forms\Components\TextInput::make('pulse_duration')
                            ->label(__('equipment::equipment.pulse_duration')),
                    ]),

                Forms\Components\Textarea::make('notes')
                    ->label(__('equipment::equipment.notes'))
                    ->rows(2)
                    ->columnSpanFull(),

                Forms\Components\DateTimePicker::make('logged_at')
                    ->label(__('equipment::equipment.logged_at'))
                    ->required()
                    ->default(now()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('shots_count')
                    ->label(__('equipment::equipment.shots'))
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('energy_setting')
                    ->label(__('equipment::equipment.energy'))
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('spot_size')
                    ->label(__('equipment::equipment.spot_size'))
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('pulse_duration')
                    ->label(__('equipment::equipment.pulse'))
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('logged_at')
                    ->label(__('equipment::equipment.logged_at'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('notes')
                    ->label(__('equipment::equipment.notes'))
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(function ($record) {
                        // Update equipment total shots
                        $equipment = $this->getOwnerRecord();
                        $equipment->increment('total_shots_fired', $record->shots_count);
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        // Decrement equipment total shots when deleting
                        $equipment = $this->getOwnerRecord();
                        $equipment->decrement('total_shots_fired', $record->shots_count);
                    }),
            ])
            ->defaultSort('logged_at', 'desc');
    }
}
