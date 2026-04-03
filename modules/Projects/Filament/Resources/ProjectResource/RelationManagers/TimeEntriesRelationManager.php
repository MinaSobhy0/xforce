<?php

namespace Modules\Projects\Filament\Resources\ProjectResource\RelationManagers;

use Modules\Projects\Models\ProjectTask;
use Modules\Projects\Models\ProjectTimeEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TimeEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'timeEntries';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('task_id')
                    ->label(__('projects::tasks.task'))
                    ->options(function () {
                        return $this->getOwnerRecord()
                            ->tasks()
                            ->get()
                            ->mapWithKeys(fn ($task) => [$task->id => "{$task->code} - {$task->display_name}"]);
                    }),

                Forms\Components\DatePicker::make('date')
                    ->label(__('projects::projects.fields.date'))
                    ->required()
                    ->default(now()),

                Forms\Components\TextInput::make('hours')
                    ->label(__('projects::tasks.fields.hours'))
                    ->numeric()
                    ->required()
                    ->step(0.25),

                Forms\Components\Textarea::make('description.en')
                    ->label(__('projects::projects.fields.description'))
                    ->rows(2)
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_billable')
                    ->label(__('projects::projects.fields.billable'))
                    ->default(false),

                Forms\Components\TextInput::make('hourly_rate_minor')
                    ->label(__('projects::projects.fields.hourly_rate'))
                    ->numeric()
                    ->prefix(current_currency())
                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                    ->dehydrateStateUsing(fn ($state) => $state ? (int) ((float) $state * 100) : 0)
                    ->visible(fn (Forms\Get $get) => $get('is_billable')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label(__('projects::projects.fields.date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('task.code')
                    ->label(__('projects::tasks.task'))
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('user.first_name')
                    ->label(__('projects::projects.fields.user'))
                    ->formatStateUsing(fn ($record) => $record->user?->name)
                    ->sortable(),

                Tables\Columns\TextColumn::make('hours')
                    ->label(__('projects::tasks.fields.hours'))
                    ->suffix(' h')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_billable')
                    ->label(__('projects::projects.fields.billable'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('billable_amount_minor')
                    ->label(__('projects::projects.fields.amount'))
                    ->formatStateUsing(fn ($state) => $state > 0 ? format_money($state) : '-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label(__('projects::projects.fields.user'))
                    ->relationship('user', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name),

                Tables\Filters\TernaryFilter::make('is_billable')
                    ->label(__('projects::projects.fields.billable')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = $this->getOwnerRecord()->tenant_id;
                        $data['user_id'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc');
    }
}
