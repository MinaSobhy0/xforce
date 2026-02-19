<?php

namespace Modules\Patients\Filament\Resources\PatientResource\RelationManagers;

use Modules\Patients\Models\PatientNote;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    protected static ?string $title = 'Notes';

    protected static ?string $icon = 'heroicon-o-document-text';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->label(__('patients::patients.notes.type'))
                    ->options(PatientNote::TYPES)
                    ->required()
                    ->default('clinical'),

                Forms\Components\TextInput::make('subject')
                    ->label('Subject')
                    ->maxLength(255),

                Forms\Components\RichEditor::make('content')
                    ->label(__('patients::patients.notes.content'))
                    ->required()
                    ->columnSpanFull(),

                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\Toggle::make('is_pinned')
                            ->label('Pin Note'),

                        Forms\Components\Toggle::make('is_private')
                            ->label('Private'),

                        Forms\Components\Toggle::make('is_alert')
                            ->label('Show as Alert')
                            ->reactive(),
                    ]),

                Forms\Components\DateTimePicker::make('alert_until')
                    ->label('Alert Until')
                    ->visible(fn ($get) => $get('is_alert')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\IconColumn::make('is_pinned')
                    ->label('')
                    ->boolean()
                    ->trueIcon('heroicon-s-bookmark')
                    ->falseIcon('')
                    ->width(40),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('patients::patients.notes.type'))
                    ->badge()
                    ->color(fn ($record) => $record->type_color),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->limit(30)
                    ->searchable(),

                Tables\Columns\TextColumn::make('preview')
                    ->label(__('patients::patients.notes.content'))
                    ->limit(50)
                    ->wrap(),

                Tables\Columns\IconColumn::make('is_alert')
                    ->label('Alert')
                    ->boolean()
                    ->trueIcon('heroicon-s-exclamation-triangle')
                    ->trueColor('warning')
                    ->falseIcon(''),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label(__('patients::patients.notes.created_by')),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(PatientNote::TYPES),

                Tables\Filters\TernaryFilter::make('is_pinned')
                    ->label('Pinned'),

                Tables\Filters\TernaryFilter::make('is_alert')
                    ->label('Alerts'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('pin')
                    ->icon('heroicon-o-bookmark')
                    ->action(fn ($record) => $record->is_pinned ? $record->unpin() : $record->pin())
                    ->label(fn ($record) => $record->is_pinned ? 'Unpin' : 'Pin'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
