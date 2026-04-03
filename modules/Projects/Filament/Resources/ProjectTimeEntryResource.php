<?php

namespace Modules\Projects\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Projects\Filament\Resources\ProjectTimeEntryResource\Pages;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectTask;
use Modules\Projects\Models\ProjectTimeEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectTimeEntryResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = ProjectTimeEntry::class;

    protected static ?string $moduleCode = 'projects';

    protected static ?string $permissionKey = 'time_entries';

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Projects';

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('projects::projects.time_entries');
    }

    public static function getModelLabel(): string
    {
        return __('projects::projects.time_entry');
    }

    public static function getPluralModelLabel(): string
    {
        return __('projects::projects.time_entries');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Select::make('project_id')
                            ->label(__('projects::projects.fields.project'))
                            ->relationship('project', 'code')
                            ->getOptionLabelFromRecordUsing(fn (Project $record) => "{$record->code} - {$record->display_name}")
                            ->searchable(['code', 'name'])
                            ->preload()
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('task_id')
                            ->label(__('projects::tasks.task'))
                            ->options(function (Forms\Get $get) {
                                $projectId = $get('project_id');
                                if (!$projectId) {
                                    return [];
                                }
                                return ProjectTask::where('project_id', $projectId)
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
                            ->step(0.25)
                            ->minValue(0.25)
                            ->maxValue(24),

                        Forms\Components\Textarea::make('description.en')
                            ->label(__('projects::projects.fields.description_en'))
                            ->rows(3)
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
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label(__('projects::projects.fields.date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('project.code')
                    ->label(__('projects::projects.project'))
                    ->sortable()
                    ->description(fn (ProjectTimeEntry $record) => $record->project->display_name),

                Tables\Columns\TextColumn::make('task.code')
                    ->label(__('projects::tasks.task'))
                    ->sortable()
                    ->placeholder('-')
                    ->description(fn (ProjectTimeEntry $record) => $record->task?->display_name),

                Tables\Columns\TextColumn::make('user.first_name')
                    ->label(__('projects::projects.fields.user'))
                    ->formatStateUsing(fn ($record) => $record->user?->name)
                    ->sortable(),

                Tables\Columns\TextColumn::make('hours')
                    ->label(__('projects::tasks.fields.hours'))
                    ->suffix(' h')
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label(__('projects::projects.fields.description'))
                    ->formatStateUsing(fn (ProjectTimeEntry $record) => $record->display_description)
                    ->limit(50)
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_billable')
                    ->label(__('projects::projects.fields.billable'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('billable_amount_minor')
                    ->label(__('projects::projects.fields.amount'))
                    ->formatStateUsing(fn ($state) => $state > 0 ? format_money($state) : '-')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_timer_running')
                    ->label(__('projects::projects.fields.timer'))
                    ->boolean()
                    ->trueIcon('heroicon-o-play')
                    ->falseIcon('heroicon-o-stop')
                    ->trueColor('success')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('projects::projects.fields.created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('project_id')
                    ->label(__('projects::projects.project'))
                    ->relationship('project', 'code')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label(__('projects::projects.fields.user'))
                    ->relationship('user', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name),

                Tables\Filters\TernaryFilter::make('is_billable')
                    ->label(__('projects::projects.fields.billable')),

                Tables\Filters\Filter::make('this_week')
                    ->label(__('projects::projects.filters.this_week'))
                    ->query(fn (Builder $query) => $query->thisWeek()),

                Tables\Filters\Filter::make('this_month')
                    ->label(__('projects::projects.filters.this_month'))
                    ->query(fn (Builder $query) => $query->thisMonth()),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label(__('projects::projects.filters.from')),
                        Forms\Components\DatePicker::make('until')
                            ->label(__('projects::projects.filters.until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->where('date', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->where('date', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),

                Tables\Actions\Action::make('stop_timer')
                    ->label(__('projects::projects.actions.stop_timer'))
                    ->icon('heroicon-o-stop')
                    ->color('warning')
                    ->visible(fn (ProjectTimeEntry $record) => $record->is_timer_running)
                    ->action(function (ProjectTimeEntry $record) {
                        $service = app(\Modules\Projects\Services\TimeTrackingService::class);
                        $service->stopActiveTimer($record->user_id);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc');
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
            'index' => Pages\ListProjectTimeEntries::route('/'),
            'create' => Pages\CreateProjectTimeEntry::route('/create'),
            'edit' => Pages\EditProjectTimeEntry::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['project', 'task', 'user']);

        // If user can only view own time entries, filter by user
        if (!auth()->user()?->can('time_entries.view_any')) {
            $query->where('user_id', auth()->id());
        }

        return $query;
    }
}
