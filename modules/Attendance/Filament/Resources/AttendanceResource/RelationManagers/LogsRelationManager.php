<?php

namespace Modules\Attendance\Filament\Resources\AttendanceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Attendance\Models\AttendanceLog;

class LogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static ?string $title = 'Attendance Logs';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->label(__('attendance::attendance.log_type'))
                    ->options(AttendanceLog::TYPES)
                    ->required(),

                Forms\Components\Select::make('source')
                    ->label(__('attendance::attendance.source'))
                    ->options(AttendanceLog::SOURCES)
                    ->default(AttendanceLog::SOURCE_MANUAL)
                    ->required(),

                Forms\Components\TextInput::make('latitude')
                    ->label(__('attendance::attendance.latitude'))
                    ->numeric(),

                Forms\Components\TextInput::make('longitude')
                    ->label(__('attendance::attendance.longitude'))
                    ->numeric(),

                Forms\Components\Textarea::make('address')
                    ->label(__('attendance::attendance.address'))
                    ->rows(2),

                Forms\Components\Textarea::make('notes')
                    ->label(__('attendance::attendance.notes'))
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label(__('attendance::attendance.log_type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => AttendanceLog::TYPES[$state] ?? $state)
                    ->color(fn ($state) => AttendanceLog::TYPE_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('attendance::attendance.date'))
                    ->dateTime('M d, Y h:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('source')
                    ->label(__('attendance::attendance.source'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => AttendanceLog::SOURCES[$state] ?? $state)
                    ->color(fn ($state) => AttendanceLog::SOURCE_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('formatted_location')
                    ->label(__('attendance::attendance.latitude') . ' / ' . __('attendance::attendance.longitude'))
                    ->default('N/A'),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label(__('attendance::attendance.created_by'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = auth()->user()->tenant_id;
                        $data['created_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }
}
