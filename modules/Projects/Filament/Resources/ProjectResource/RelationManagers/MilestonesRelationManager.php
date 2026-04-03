<?php

namespace Modules\Projects\Filament\Resources\ProjectResource\RelationManagers;

use Modules\Projects\Models\ProjectMilestone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MilestonesRelationManager extends RelationManager
{
    protected static string $relationship = 'milestones';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name.en')
                    ->label(__('projects::projects.fields.name_en'))
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('name.ar')
                    ->label(__('projects::projects.fields.name_ar'))
                    ->maxLength(255),

                Forms\Components\DatePicker::make('target_date')
                    ->label(__('projects::projects.fields.target_date')),

                Forms\Components\Textarea::make('description.en')
                    ->label(__('projects::projects.fields.description_en'))
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('projects::projects.fields.name'))
                    ->formatStateUsing(fn (ProjectMilestone $record) => $record->display_name),

                Tables\Columns\TextColumn::make('target_date')
                    ->label(__('projects::projects.fields.target_date'))
                    ->date()
                    ->color(fn (ProjectMilestone $record) => $record->is_overdue ? 'danger' : null),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('projects::projects.fields.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        ProjectMilestone::STATUS_PENDING => 'warning',
                        ProjectMilestone::STATUS_COMPLETED => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ProjectMilestone::STATUSES[$state] ?? $state),

                Tables\Columns\TextColumn::make('tasks_count')
                    ->label(__('projects::projects.fields.tasks'))
                    ->counts('tasks'),

                Tables\Columns\TextColumn::make('progress_percent')
                    ->label(__('projects::projects.fields.progress'))
                    ->suffix('%'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('projects::projects.fields.status'))
                    ->options(ProjectMilestone::STATUSES),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = $this->getOwnerRecord()->tenant_id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('complete')
                    ->label(__('projects::projects.actions.complete'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ProjectMilestone $record) => !$record->is_completed)
                    ->action(fn (ProjectMilestone $record) => $record->markComplete()),

                Tables\Actions\Action::make('reopen')
                    ->label(__('projects::projects.actions.reopen'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (ProjectMilestone $record) => $record->is_completed)
                    ->action(fn (ProjectMilestone $record) => $record->reopen()),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }
}
