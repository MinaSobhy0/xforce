<?php

namespace Modules\Projects\Filament\Resources\ProjectResource\RelationManagers;

use Modules\Projects\Models\ProjectTask;
use Modules\Projects\Models\ProjectStage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;

class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $recordTitleAttribute = 'code';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('stage_id')
                    ->label(__('projects::tasks.fields.stage'))
                    ->options(function () {
                        return $this->getOwnerRecord()
                            ->stages()
                            ->orderBy('sort_order')
                            ->get()
                            ->mapWithKeys(fn ($stage) => [$stage->id => $stage->display_name]);
                    })
                    ->required(),

                Forms\Components\TextInput::make('name.en')
                    ->label(__('projects::tasks.fields.name_en'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Forms\Components\Select::make('assigned_to_id')
                    ->label(__('projects::tasks.fields.assigned_to'))
                    ->relationship('assignedTo', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('priority')
                    ->label(__('projects::tasks.fields.priority'))
                    ->options(ProjectTask::PRIORITIES)
                    ->default(ProjectTask::PRIORITY_MEDIUM)
                    ->required(),

                Forms\Components\DatePicker::make('deadline')
                    ->label(__('projects::tasks.fields.deadline')),

                Forms\Components\TextInput::make('estimated_hours')
                    ->label(__('projects::tasks.fields.estimated_hours'))
                    ->numeric(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('projects::tasks.fields.code'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('projects::tasks.fields.name'))
                    ->formatStateUsing(fn (ProjectTask $record) => $record->display_name)
                    ->limit(40),

                Tables\Columns\TextColumn::make('stage.name')
                    ->label(__('projects::tasks.fields.stage'))
                    ->formatStateUsing(fn (ProjectTask $record) => $record->stage->display_name)
                    ->badge()
                    ->color(fn (ProjectTask $record) => match ($record->stage->status_type) {
                        'todo' => 'gray',
                        'in_progress' => 'warning',
                        'review' => 'info',
                        'done' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('assignedTo.first_name')
                    ->label(__('projects::tasks.fields.assigned_to'))
                    ->formatStateUsing(fn ($record) => $record->assignedTo?->name),

                Tables\Columns\TextColumn::make('priority')
                    ->label(__('projects::tasks.fields.priority'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        ProjectTask::PRIORITY_LOW => 'gray',
                        ProjectTask::PRIORITY_MEDIUM => 'info',
                        ProjectTask::PRIORITY_HIGH => 'warning',
                        ProjectTask::PRIORITY_URGENT => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('deadline')
                    ->label(__('projects::tasks.fields.deadline'))
                    ->date()
                    ->color(fn (ProjectTask $record) => $record->is_overdue ? 'danger' : null),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('stage_id')
                    ->label(__('projects::tasks.fields.stage'))
                    ->options(function () {
                        return $this->getOwnerRecord()
                            ->stages()
                            ->orderBy('sort_order')
                            ->get()
                            ->mapWithKeys(fn ($stage) => [$stage->id => $stage->display_name]);
                    }),

                Tables\Filters\SelectFilter::make('priority')
                    ->label(__('projects::tasks.fields.priority'))
                    ->options(ProjectTask::PRIORITIES),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = $this->getOwnerRecord()->tenant_id;
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
            ->defaultSort('created_at', 'desc');
    }
}
