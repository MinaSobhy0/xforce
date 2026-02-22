<?php

namespace Modules\Attendance\Filament\Resources\AttendanceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class BreaksRelationManager extends RelationManager
{
    protected static string $relationship = 'breaks';

    protected static ?string $title = 'Breaks';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DateTimePicker::make('start_time')
                    ->label('Start Time')
                    ->required(),

                Forms\Components\DateTimePicker::make('end_time')
                    ->label('End Time'),

                Forms\Components\Textarea::make('reason')
                    ->label('Reason')
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('start_time')
            ->columns([
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Start Time')
                    ->dateTime('h:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_time')
                    ->label('End Time')
                    ->dateTime('h:i A')
                    ->placeholder('Ongoing'),

                Tables\Columns\TextColumn::make('formatted_duration')
                    ->label('Duration'),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_ongoing')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-clock')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('success')
                    ->getStateUsing(fn ($record) => $record->isOngoing()),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = auth()->user()->tenant_id;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('end_break')
                    ->label('End Break')
                    ->icon('heroicon-o-stop')
                    ->color('success')
                    ->visible(fn ($record) => $record->isOngoing())
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->endBreak()),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('start_time', 'desc');
    }
}
