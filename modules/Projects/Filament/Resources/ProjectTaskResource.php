<?php

namespace Modules\Projects\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Projects\Filament\Resources\ProjectTaskResource\Pages;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectTask;
use Modules\Projects\Models\ProjectStage;
use Modules\Projects\Models\ProjectTag;
use Modules\Projects\Models\ProjectMilestone;
use Modules\Auth\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProjectTaskResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = ProjectTask::class;

    protected static ?string $moduleCode = 'projects';

    protected static ?string $permissionKey = 'tasks';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Projects';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getNavigationLabel(): string
    {
        return __('projects::tasks.tasks');
    }

    public static function getModelLabel(): string
    {
        return __('projects::tasks.task');
    }

    public static function getPluralModelLabel(): string
    {
        return __('projects::tasks.tasks');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::incomplete()->assignedTo(auth()->id())->count();
        return $count ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make(__('projects::tasks.sections.task_details'))
                            ->schema([
                                Forms\Components\Select::make('project_id')
                                    ->label(__('projects::tasks.fields.project'))
                                    ->relationship('project', 'code')
                                    ->getOptionLabelFromRecordUsing(fn (Project $record) => "{$record->code} - {$record->display_name}")
                                    ->searchable(['code', 'name'])
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if ($state) {
                                            $project = Project::find($state);
                                            $firstStage = $project?->stages()->orderBy('sort_order')->first();
                                            if ($firstStage) {
                                                $set('stage_id', $firstStage->id);
                                            }
                                        }
                                    }),

                                Forms\Components\Select::make('stage_id')
                                    ->label(__('projects::tasks.fields.stage'))
                                    ->options(function (Forms\Get $get) {
                                        $projectId = $get('project_id');
                                        if (!$projectId) {
                                            return [];
                                        }
                                        return ProjectStage::where('project_id', $projectId)
                                            ->orderBy('sort_order')
                                            ->get()
                                            ->mapWithKeys(fn ($stage) => [$stage->id => $stage->display_name]);
                                    })
                                    ->required()
                                    ->live(),

                                Forms\Components\TextInput::make('name.en')
                                    ->label(__('projects::tasks.fields.name_en'))
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('name.ar')
                                    ->label(__('projects::tasks.fields.name_ar'))
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

                                Forms\Components\Select::make('milestone_id')
                                    ->label(__('projects::tasks.fields.milestone'))
                                    ->options(function (Forms\Get $get) {
                                        $projectId = $get('project_id');
                                        if (!$projectId) {
                                            return [];
                                        }
                                        return ProjectMilestone::where('project_id', $projectId)
                                            ->orderBy('sort_order')
                                            ->get()
                                            ->mapWithKeys(fn ($m) => [$m->id => $m->display_name]);
                                    }),

                                Forms\Components\Select::make('parent_task_id')
                                    ->label(__('projects::tasks.fields.parent_task'))
                                    ->options(function (Forms\Get $get, ?ProjectTask $record) {
                                        $projectId = $get('project_id');
                                        if (!$projectId) {
                                            return [];
                                        }
                                        $query = ProjectTask::where('project_id', $projectId)
                                            ->whereNull('parent_task_id');
                                        if ($record) {
                                            $query->where('id', '!=', $record->id);
                                        }
                                        return $query->get()
                                            ->mapWithKeys(fn ($t) => [$t->id => "{$t->code} - {$t->display_name}"]);
                                    }),

                                Forms\Components\Select::make('tags')
                                    ->label(__('projects::tasks.fields.tags'))
                                    ->relationship('tags', 'id')
                                    ->getOptionLabelFromRecordUsing(fn (ProjectTag $record) => $record->display_name)
                                    ->multiple()
                                    ->preload(),
                            ])
                            ->columns(2),

                        Forms\Components\Section::make(__('projects::tasks.sections.description'))
                            ->schema([
                                Forms\Components\RichEditor::make('description.en')
                                    ->label(__('projects::tasks.fields.description_en'))
                                    ->columnSpanFull(),

                                Forms\Components\RichEditor::make('description.ar')
                                    ->label(__('projects::tasks.fields.description_ar'))
                                    ->columnSpanFull(),
                            ])
                            ->collapsed(),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make(__('projects::tasks.sections.dates'))
                            ->schema([
                                Forms\Components\DatePicker::make('planned_start_date')
                                    ->label(__('projects::tasks.fields.planned_start_date')),

                                Forms\Components\DatePicker::make('planned_end_date')
                                    ->label(__('projects::tasks.fields.planned_end_date'))
                                    ->afterOrEqual('planned_start_date'),

                                Forms\Components\DatePicker::make('deadline')
                                    ->label(__('projects::tasks.fields.deadline')),
                            ]),

                        Forms\Components\Section::make(__('projects::tasks.sections.time'))
                            ->schema([
                                Forms\Components\TextInput::make('estimated_hours')
                                    ->label(__('projects::tasks.fields.estimated_hours'))
                                    ->numeric()
                                    ->suffix(__('projects::tasks.fields.hours')),

                                Forms\Components\Placeholder::make('actual_hours_display')
                                    ->label(__('projects::tasks.fields.actual_hours'))
                                    ->content(fn (?ProjectTask $record) => $record ? $record->logged_hours . ' ' . __('projects::tasks.fields.hours') : '-'),

                                Forms\Components\TextInput::make('progress_percent')
                                    ->label(__('projects::tasks.fields.progress'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->default(0),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
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
                    ->searchable(['name'])
                    ->limit(40)
                    ->tooltip(fn (ProjectTask $record) => $record->display_name),

                Tables\Columns\TextColumn::make('project.code')
                    ->label(__('projects::tasks.fields.project'))
                    ->sortable()
                    ->toggleable(),

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
                    ->formatStateUsing(fn ($record) => $record->assignedTo?->name)
                    ->sortable()
                    ->toggleable(),

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
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_completed')
                    ->label(__('projects::tasks.fields.completed'))
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('projects::tasks.fields.created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('project_id')
                    ->label(__('projects::tasks.fields.project'))
                    ->relationship('project', 'code')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('stage_id')
                    ->label(__('projects::tasks.fields.stage'))
                    ->options(function () {
                        return ProjectStage::query()
                            ->get()
                            ->mapWithKeys(fn ($stage) => [$stage->id => $stage->display_name]);
                    }),

                Tables\Filters\SelectFilter::make('assigned_to_id')
                    ->label(__('projects::tasks.fields.assigned_to'))
                    ->relationship('assignedTo', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name),

                Tables\Filters\SelectFilter::make('priority')
                    ->label(__('projects::tasks.fields.priority'))
                    ->options(ProjectTask::PRIORITIES),

                Tables\Filters\Filter::make('my_tasks')
                    ->label(__('projects::tasks.filters.my_tasks'))
                    ->query(fn (Builder $query) => $query->assignedTo(auth()->id())),

                Tables\Filters\Filter::make('overdue')
                    ->label(__('projects::tasks.filters.overdue'))
                    ->query(fn (Builder $query) => $query->overdue()),

                Tables\Filters\Filter::make('incomplete')
                    ->label(__('projects::tasks.filters.incomplete'))
                    ->query(fn (Builder $query) => $query->incomplete())
                    ->default(true),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

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
                            Forms\Components\Toggle::make('is_billable')
                                ->label(__('projects::tasks.fields.billable'))
                                ->default(false),
                        ])
                        ->action(function (ProjectTask $record, array $data) {
                            $service = app(\Modules\Projects\Services\TimeTrackingService::class);
                            $service->logTime(
                                $record->project_id,
                                $data['hours'],
                                $record->id,
                                auth()->id(),
                                $data['date'],
                                $data['description'] ? ['en' => $data['description']] : null,
                                $data['is_billable']
                            );
                        }),

                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjectTasks::route('/'),
            'create' => Pages\CreateProjectTask::route('/create'),
            'view' => Pages\ViewProjectTask::route('/{record}'),
            'edit' => Pages\EditProjectTask::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['project', 'stage', 'assignedTo', 'tags']);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            __('projects::tasks.fields.project') => $record->project?->code,
            __('projects::tasks.fields.assigned_to') => $record->assignedTo?->name,
        ];
    }
}
