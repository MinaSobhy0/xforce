<?php

namespace Modules\Projects\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;
use Modules\Projects\Models\ProjectTask;

class MyTasks extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationGroup = 'Projects';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'projects::filament.pages.my-tasks';

    public static function getNavigationLabel(): string
    {
        return __('projects::tasks.my_tasks');
    }

    public static function getNavigationBadge(): ?string
    {
        return ProjectTask::incomplete()->assignedTo(auth()->id())->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function getTitle(): string
    {
        return __('projects::tasks.my_tasks');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ProjectTask::query()->assignedTo(auth()->id()))
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

                Tables\Columns\TextColumn::make('project.code')
                    ->label(__('projects::tasks.fields.project'))
                    ->sortable(),

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
                    })
                    ->formatStateUsing(fn (string $state) => ProjectTask::PRIORITIES[$state] ?? $state),

                Tables\Columns\TextColumn::make('deadline')
                    ->label(__('projects::tasks.fields.deadline'))
                    ->date()
                    ->sortable()
                    ->color(fn (ProjectTask $record) => $record->is_overdue ? 'danger' : null),

                Tables\Columns\TextColumn::make('progress_percent')
                    ->label(__('projects::tasks.fields.progress'))
                    ->suffix('%'),

                Tables\Columns\IconColumn::make('is_completed')
                    ->label(__('projects::tasks.fields.completed'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('project_id')
                    ->label(__('projects::tasks.fields.project'))
                    ->relationship('project', 'code')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('priority')
                    ->label(__('projects::tasks.fields.priority'))
                    ->options(ProjectTask::PRIORITIES),

                Tables\Filters\Filter::make('overdue')
                    ->label(__('projects::tasks.filters.overdue'))
                    ->query(fn (Builder $query) => $query->overdue()),

                Tables\Filters\Filter::make('incomplete')
                    ->label(__('projects::tasks.filters.incomplete'))
                    ->query(fn (Builder $query) => $query->incomplete())
                    ->default(true),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label(__('projects::tasks.actions.view'))
                    ->icon('heroicon-o-eye')
                    ->url(fn (ProjectTask $record) => route('filament.tenant.resources.project-tasks.view', $record)),

                Tables\Actions\Action::make('log_time')
                    ->label(__('projects::tasks.actions.log_time'))
                    ->icon('heroicon-o-clock')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('hours')
                            ->label(__('projects::tasks.fields.hours'))
                            ->numeric()
                            ->required()
                            ->step(0.25),
                        Forms\Components\DatePicker::make('date')
                            ->label(__('projects::tasks.fields.date'))
                            ->default(now()),
                        Forms\Components\Textarea::make('description')
                            ->label(__('projects::tasks.fields.description'))
                            ->rows(2),
                    ])
                    ->action(function (ProjectTask $record, array $data) {
                        $service = app(\Modules\Projects\Services\TimeTrackingService::class);
                        $service->logTime(
                            $record->project_id,
                            $data['hours'],
                            $record->id,
                            auth()->id(),
                            $data['date'],
                            $data['description'] ? ['en' => $data['description']] : null
                        );
                    }),
            ])
            ->defaultSort('deadline', 'asc');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('tasks.view_any') ?? false;
    }
}
