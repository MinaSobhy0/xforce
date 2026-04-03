<?php

namespace Modules\Projects\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Projects\Filament\Resources\ProjectResource\Pages;
use Modules\Projects\Filament\Resources\ProjectResource\RelationManagers;
use Modules\Projects\Models\Project;
use Modules\Core\Models\Branch;
use Modules\Auth\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use XLinic\Framework\Core\Filament\RelationManagers\ActivityLogRelationManager;

class ProjectResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = Project::class;

    protected static ?string $moduleCode = 'projects';

    protected static ?string $permissionKey = 'projects';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Projects';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getNavigationLabel(): string
    {
        return __('projects::projects.projects');
    }

    public static function getModelLabel(): string
    {
        return __('projects::projects.project');
    }

    public static function getPluralModelLabel(): string
    {
        return __('projects::projects.projects');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::active()->notTemplate()->byStatus(Project::STATUS_ACTIVE)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make(__('projects::projects.sections.project_details'))
                            ->schema([
                                Forms\Components\TextInput::make('name.en')
                                    ->label(__('projects::projects.fields.name_en'))
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('name.ar')
                                    ->label(__('projects::projects.fields.name_ar'))
                                    ->maxLength(255),

                                Forms\Components\Select::make('branch_id')
                                    ->label(__('projects::projects.fields.branch'))
                                    ->relationship('branch', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->default(fn () => current_branch_id())
                                    ->disabled(fn () => current_branch_id() !== null)
                                    ->dehydrated(),

                                Forms\Components\Select::make('manager_id')
                                    ->label(__('projects::projects.fields.manager'))
                                    ->relationship('manager', 'first_name')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                                    ->searchable()
                                    ->preload(),

                                Forms\Components\Select::make('status')
                                    ->label(__('projects::projects.fields.status'))
                                    ->options(Project::STATUSES)
                                    ->default(Project::STATUS_PLANNING)
                                    ->required(),

                                Forms\Components\Select::make('priority')
                                    ->label(__('projects::projects.fields.priority'))
                                    ->options(Project::PRIORITIES)
                                    ->default(Project::PRIORITY_MEDIUM)
                                    ->required(),

                                Forms\Components\ColorPicker::make('color')
                                    ->label(__('projects::projects.fields.color'))
                                    ->default('#3B82F6'),
                            ])
                            ->columns(2),

                        Forms\Components\Section::make(__('projects::projects.sections.dates'))
                            ->schema([
                                Forms\Components\DatePicker::make('start_date')
                                    ->label(__('projects::projects.fields.start_date')),

                                Forms\Components\DatePicker::make('end_date')
                                    ->label(__('projects::projects.fields.end_date'))
                                    ->afterOrEqual('start_date'),

                                Forms\Components\DatePicker::make('deadline')
                                    ->label(__('projects::projects.fields.deadline')),
                            ])
                            ->columns(3),

                        Forms\Components\Section::make(__('projects::projects.sections.description'))
                            ->schema([
                                Forms\Components\RichEditor::make('description.en')
                                    ->label(__('projects::projects.fields.description_en'))
                                    ->columnSpanFull(),

                                Forms\Components\RichEditor::make('description.ar')
                                    ->label(__('projects::projects.fields.description_ar'))
                                    ->columnSpanFull(),
                            ])
                            ->collapsed(),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make(__('projects::projects.sections.budget'))
                            ->schema([
                                Forms\Components\TextInput::make('budget_minor')
                                    ->label(__('projects::projects.fields.budget'))
                                    ->numeric()
                                    ->prefix(current_currency())
                                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                                    ->dehydrateStateUsing(fn ($state) => $state ? (int) ((float) $state * 100) : 0),

                                Forms\Components\Placeholder::make('actual_cost_display')
                                    ->label(__('projects::projects.fields.actual_cost'))
                                    ->content(fn (?Project $record) => $record ? format_money($record->actual_cost_minor) : '-'),

                                Forms\Components\Placeholder::make('progress_display')
                                    ->label(__('projects::projects.fields.progress'))
                                    ->content(fn (?Project $record) => $record ? $record->progress_percent . '%' : '0%'),
                            ]),

                        Forms\Components\Section::make(__('projects::projects.sections.settings'))
                            ->schema([
                                Forms\Components\Toggle::make('allow_timesheets')
                                    ->label(__('projects::projects.fields.allow_timesheets'))
                                    ->default(true),

                                Forms\Components\Toggle::make('is_template')
                                    ->label(__('projects::projects.fields.is_template'))
                                    ->default(false)
                                    ->helperText(__('projects::projects.helpers.is_template')),

                                Forms\Components\Toggle::make('is_active')
                                    ->label(__('projects::projects.fields.is_active'))
                                    ->default(true),
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
                    ->label(__('projects::projects.fields.code'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\ColorColumn::make('color')
                    ->label(''),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('projects::projects.fields.name'))
                    ->formatStateUsing(fn (Project $record) => $record->display_name)
                    ->searchable(['name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('manager.first_name')
                    ->label(__('projects::projects.fields.manager'))
                    ->formatStateUsing(fn ($record) => $record->manager?->name)
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('projects::projects.fields.branch'))
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('projects::projects.fields.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Project::STATUS_PLANNING => 'gray',
                        Project::STATUS_ACTIVE => 'info',
                        Project::STATUS_ON_HOLD => 'warning',
                        Project::STATUS_COMPLETED => 'success',
                        Project::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => Project::STATUSES[$state] ?? $state),

                Tables\Columns\TextColumn::make('priority')
                    ->label(__('projects::projects.fields.priority'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Project::PRIORITY_LOW => 'gray',
                        Project::PRIORITY_MEDIUM => 'info',
                        Project::PRIORITY_HIGH => 'warning',
                        Project::PRIORITY_URGENT => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => Project::PRIORITIES[$state] ?? $state),

                Tables\Columns\TextColumn::make('tasks_count')
                    ->label(__('projects::projects.fields.tasks'))
                    ->counts('tasks')
                    ->sortable(),

                Tables\Columns\TextColumn::make('deadline')
                    ->label(__('projects::projects.fields.deadline'))
                    ->date()
                    ->sortable()
                    ->color(fn (Project $record) => $record->deadline && $record->deadline->isPast() && !$record->isCompleted() ? 'danger' : null),

                Tables\Columns\IconColumn::make('is_template')
                    ->label(__('projects::projects.fields.template'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('projects::projects.fields.created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('projects::projects.fields.status'))
                    ->options(Project::STATUSES)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('priority')
                    ->label(__('projects::projects.fields.priority'))
                    ->options(Project::PRIORITIES),

                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('projects::projects.fields.branch'))
                    ->relationship('branch', 'name'),

                Tables\Filters\SelectFilter::make('manager_id')
                    ->label(__('projects::projects.fields.manager'))
                    ->relationship('manager', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name),

                Tables\Filters\TernaryFilter::make('is_template')
                    ->label(__('projects::projects.filters.templates')),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('projects::projects.filters.active')),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

                    Tables\Actions\Action::make('kanban')
                        ->label(__('projects::projects.actions.view_kanban'))
                        ->icon('heroicon-o-view-columns')
                        ->url(fn (Project $record) => route('filament.tenant.pages.project-kanban', ['project' => $record->id])),

                    Tables\Actions\Action::make('clone')
                        ->label(__('projects::projects.actions.clone'))
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->visible(fn (Project $record) => $record->is_template)
                        ->form([
                            Forms\Components\TextInput::make('name')
                                ->label(__('projects::projects.fields.name'))
                                ->required(),
                        ])
                        ->action(function (Project $record, array $data) {
                            $service = app(\Modules\Projects\Services\ProjectService::class);
                            $service->cloneFromTemplate($record, ['name' => ['en' => $data['name']]]);
                        }),

                    Tables\Actions\Action::make('activate')
                        ->label(__('projects::projects.actions.activate'))
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->visible(fn (Project $record) => $record->canTransitionTo(Project::STATUS_ACTIVE))
                        ->action(fn (Project $record) => $record->transitionTo(Project::STATUS_ACTIVE)),

                    Tables\Actions\Action::make('complete')
                        ->label(__('projects::projects.actions.complete'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (Project $record) => $record->canTransitionTo(Project::STATUS_COMPLETED))
                        ->action(fn (Project $record) => $record->transitionTo(Project::STATUS_COMPLETED)),

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

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make()
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\Group::make([
                                    Infolists\Components\TextEntry::make('code')
                                        ->label(__('projects::projects.fields.code'))
                                        ->weight(FontWeight::Bold)
                                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                                    Infolists\Components\TextEntry::make('display_name')
                                        ->label(__('projects::projects.fields.name')),
                                ]),

                                Infolists\Components\Group::make([
                                    Infolists\Components\TextEntry::make('manager.first_name')
                                        ->label(__('projects::projects.fields.manager'))
                                        ->formatStateUsing(fn ($record) => $record->manager?->name)
                                        ->icon('heroicon-o-user')
                                        ->placeholder('-'),
                                    Infolists\Components\TextEntry::make('branch.name')
                                        ->label(__('projects::projects.fields.branch'))
                                        ->icon('heroicon-o-building-storefront'),
                                ]),

                                Infolists\Components\Group::make([
                                    Infolists\Components\TextEntry::make('start_date')
                                        ->label(__('projects::projects.fields.start_date'))
                                        ->date()
                                        ->placeholder('-'),
                                    Infolists\Components\TextEntry::make('deadline')
                                        ->label(__('projects::projects.fields.deadline'))
                                        ->date()
                                        ->color(fn (Project $record) => $record->deadline && $record->deadline->isPast() && !$record->isCompleted() ? 'danger' : null)
                                        ->placeholder('-'),
                                ]),

                                Infolists\Components\Group::make([
                                    Infolists\Components\TextEntry::make('status')
                                        ->label(__('projects::projects.fields.status'))
                                        ->badge()
                                        ->formatStateUsing(fn (string $state) => Project::STATUSES[$state] ?? $state)
                                        ->color(fn (string $state) => match ($state) {
                                            Project::STATUS_PLANNING => 'gray',
                                            Project::STATUS_ACTIVE => 'info',
                                            Project::STATUS_ON_HOLD => 'warning',
                                            Project::STATUS_COMPLETED => 'success',
                                            Project::STATUS_CANCELLED => 'danger',
                                            default => 'gray',
                                        }),
                                    Infolists\Components\TextEntry::make('priority')
                                        ->label(__('projects::projects.fields.priority'))
                                        ->badge()
                                        ->formatStateUsing(fn (string $state) => Project::PRIORITIES[$state] ?? $state)
                                        ->color(fn (string $state) => match ($state) {
                                            Project::PRIORITY_LOW => 'gray',
                                            Project::PRIORITY_MEDIUM => 'info',
                                            Project::PRIORITY_HIGH => 'warning',
                                            Project::PRIORITY_URGENT => 'danger',
                                            default => 'gray',
                                        }),
                                ]),
                            ]),
                    ])
                    ->compact(),

                Infolists\Components\Section::make(__('projects::projects.sections.statistics'))
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('progress_percent')
                                    ->label(__('projects::projects.fields.progress'))
                                    ->suffix('%'),
                                Infolists\Components\TextEntry::make('tasks_count')
                                    ->label(__('projects::projects.fields.total_tasks'))
                                    ->state(fn (Project $record) => $record->tasks()->count()),
                                Infolists\Components\TextEntry::make('total_hours')
                                    ->label(__('projects::projects.fields.logged_hours'))
                                    ->state(fn (Project $record) => $record->total_actual_hours),
                                Infolists\Components\TextEntry::make('actual_cost_minor')
                                    ->label(__('projects::projects.fields.actual_cost'))
                                    ->formatStateUsing(fn ($state) => format_money($state)),
                            ]),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\StagesRelationManager::class,
            RelationManagers\TasksRelationManager::class,
            RelationManagers\MilestonesRelationManager::class,
            RelationManagers\MembersRelationManager::class,
            RelationManagers\TimeEntriesRelationManager::class,
            ActivityLogRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'view' => Pages\ViewProject::route('/{record}'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['manager', 'branch']);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            __('projects::projects.fields.status') => Project::STATUSES[$record->status] ?? $record->status,
            __('projects::projects.fields.manager') => $record->manager?->name,
        ];
    }
}
