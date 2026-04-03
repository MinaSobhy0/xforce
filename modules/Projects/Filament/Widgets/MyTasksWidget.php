<?php

namespace Modules\Projects\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Modules\Projects\Models\ProjectTask;

class MyTasksWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public function getTableHeading(): string
    {
        return __('projects::tasks.my_upcoming_tasks');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ProjectTask::query()
                    ->assignedTo(auth()->id())
                    ->incomplete()
                    ->orderBy('deadline')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('projects::tasks.fields.code'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('projects::tasks.fields.name'))
                    ->formatStateUsing(fn (ProjectTask $record) => $record->display_name)
                    ->limit(30),

                Tables\Columns\TextColumn::make('project.code')
                    ->label(__('projects::tasks.fields.project')),

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
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(fn (ProjectTask $record) => route('filament.tenant.resources.project-tasks.view', $record)),
            ])
            ->paginated(false);
    }
}
