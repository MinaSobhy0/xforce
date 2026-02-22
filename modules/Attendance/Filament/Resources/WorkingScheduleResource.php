<?php

namespace Modules\Attendance\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Attendance\Models\WorkingSchedule;
use Modules\Attendance\Filament\Resources\WorkingScheduleResource\Pages;
use Modules\Attendance\Filament\Resources\WorkingScheduleResource\RelationManagers;
use Modules\Attendance\Services\WorkingScheduleService;

class WorkingScheduleResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = WorkingSchedule::class;

    protected static ?string $moduleCode = 'attendance';

    protected static ?string $permissionKey = 'working_schedules';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 21;

    public static function getNavigationLabel(): string
    {
        return __('attendance::attendance.working_schedules');
    }

    public static function getModelLabel(): string
    {
        return __('attendance::attendance.working_schedule');
    }

    public static function getPluralModelLabel(): string
    {
        return __('attendance::attendance.working_schedules');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('attendance::attendance.schedule_name'))
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('code')
                                    ->label(__('attendance::attendance.schedule_code'))
                                    ->required()
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true),

                                Forms\Components\Select::make('type')
                                    ->label(__('attendance::attendance.schedule_type'))
                                    ->options(WorkingSchedule::TYPES)
                                    ->default(WorkingSchedule::TYPE_FIXED)
                                    ->required()
                                    ->live(),
                            ]),

                        Forms\Components\Select::make('branch_id')
                            ->label(__('attendance::attendance.branch'))
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Leave empty for global schedule'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(2),
                    ]),

                Forms\Components\Section::make('Fixed Schedule')
                    ->schema([
                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\TimePicker::make('start_time')
                                    ->label(__('attendance::attendance.start_time'))
                                    ->seconds(false)
                                    ->required(fn (Forms\Get $get) => $get('type') === WorkingSchedule::TYPE_FIXED),

                                Forms\Components\TimePicker::make('end_time')
                                    ->label(__('attendance::attendance.end_time'))
                                    ->seconds(false)
                                    ->required(fn (Forms\Get $get) => $get('type') === WorkingSchedule::TYPE_FIXED),

                                Forms\Components\TextInput::make('hours_per_day')
                                    ->label(__('attendance::attendance.hours_per_day'))
                                    ->numeric()
                                    ->default(8.00)
                                    ->suffix('hours'),

                                Forms\Components\TextInput::make('hours_per_week')
                                    ->label(__('attendance::attendance.hours_per_week'))
                                    ->numeric()
                                    ->default(40.00)
                                    ->suffix('hours'),
                            ]),
                    ])
                    ->visible(fn (Forms\Get $get) => in_array($get('type'), [WorkingSchedule::TYPE_FIXED, WorkingSchedule::TYPE_SHIFT])),

                Forms\Components\Section::make('Grace Periods')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('grace_period_late_minutes')
                                    ->label(__('attendance::attendance.grace_period_late'))
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('minutes')
                                    ->helperText('Minutes allowed late before violation'),

                                Forms\Components\TextInput::make('grace_period_early_minutes')
                                    ->label(__('attendance::attendance.grace_period_early'))
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('minutes')
                                    ->helperText('Minutes allowed early checkout before violation'),
                            ]),
                    ]),

                Forms\Components\Section::make('Flexible Schedule')
                    ->schema([
                        Forms\Components\Toggle::make('is_flexible')
                            ->label(__('attendance::attendance.is_flexible'))
                            ->live(),

                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\TimePicker::make('flexible_start_from')
                                    ->label('Earliest Start')
                                    ->seconds(false),

                                Forms\Components\TimePicker::make('flexible_start_to')
                                    ->label('Latest Start')
                                    ->seconds(false),

                                Forms\Components\TimePicker::make('flexible_end_from')
                                    ->label('Earliest End')
                                    ->seconds(false),

                                Forms\Components\TimePicker::make('flexible_end_to')
                                    ->label('Latest End')
                                    ->seconds(false),
                            ])
                            ->visible(fn (Forms\Get $get) => $get('is_flexible')),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('flexible_min_hours_per_day')
                                    ->label('Min Hours Per Day')
                                    ->numeric()
                                    ->suffix('hours'),

                                Forms\Components\TextInput::make('flexible_max_hours_per_day')
                                    ->label('Max Hours Per Day')
                                    ->numeric()
                                    ->suffix('hours'),
                            ])
                            ->visible(fn (Forms\Get $get) => $get('is_flexible')),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('type') === WorkingSchedule::TYPE_FLEXIBLE)
                    ->collapsed(),

                Forms\Components\Section::make('Core Hours')
                    ->schema([
                        Forms\Components\Toggle::make('core_hours_required')
                            ->label(__('attendance::attendance.core_hours'))
                            ->live(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TimePicker::make('core_hours_start')
                                    ->label(__('attendance::attendance.core_hours_start'))
                                    ->seconds(false),

                                Forms\Components\TimePicker::make('core_hours_end')
                                    ->label(__('attendance::attendance.core_hours_end'))
                                    ->seconds(false),
                            ])
                            ->visible(fn (Forms\Get $get) => $get('core_hours_required')),
                    ])
                    ->collapsed(),

                Forms\Components\Section::make('Working Days')
                    ->schema([
                        Forms\Components\CheckboxList::make('working_days')
                            ->label(__('attendance::attendance.working_days'))
                            ->options([
                                0 => 'Sunday',
                                1 => 'Monday',
                                2 => 'Tuesday',
                                3 => 'Wednesday',
                                4 => 'Thursday',
                                5 => 'Friday',
                                6 => 'Saturday',
                            ])
                            ->columns(7)
                            ->default([0, 1, 2, 3, 4]),

                        Forms\Components\TextInput::make('days_per_week')
                            ->label(__('attendance::attendance.days_per_week'))
                            ->numeric()
                            ->default(5)
                            ->suffix('days'),
                    ]),

                Forms\Components\Section::make('Break Settings')
                    ->schema([
                        Forms\Components\Toggle::make('has_break')
                            ->label(__('attendance::attendance.has_break'))
                            ->default(true)
                            ->live(),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('break_duration_minutes')
                                    ->label(__('attendance::attendance.break_duration'))
                                    ->numeric()
                                    ->default(60)
                                    ->suffix('minutes'),

                                Forms\Components\TimePicker::make('break_start')
                                    ->label(__('attendance::attendance.break_start'))
                                    ->seconds(false),

                                Forms\Components\TimePicker::make('break_end')
                                    ->label(__('attendance::attendance.break_end'))
                                    ->seconds(false),
                            ])
                            ->visible(fn (Forms\Get $get) => $get('has_break')),

                        Forms\Components\Toggle::make('flexible_break')
                            ->label('Flexible Break Time')
                            ->visible(fn (Forms\Get $get) => $get('has_break')),
                    ])
                    ->collapsed(),

                Forms\Components\Section::make('Overtime Settings')
                    ->schema([
                        Forms\Components\Toggle::make('allow_overtime')
                            ->label(__('attendance::attendance.allow_overtime'))
                            ->default(true)
                            ->live(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('max_overtime_per_day')
                                    ->label(__('attendance::attendance.max_overtime_per_day'))
                                    ->numeric()
                                    ->suffix('hours'),

                                Forms\Components\TextInput::make('max_overtime_per_week')
                                    ->label('Max Overtime Per Week')
                                    ->numeric()
                                    ->suffix('hours'),
                            ])
                            ->visible(fn (Forms\Get $get) => $get('allow_overtime')),
                    ])
                    ->collapsed(),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->label(__('attendance::attendance.status'))
                                    ->options(WorkingSchedule::STATUSES)
                                    ->default(WorkingSchedule::STATUS_ACTIVE)
                                    ->required(),

                                Forms\Components\Toggle::make('is_default')
                                    ->label(__('attendance::attendance.is_default'))
                                    ->helperText('Set as the default schedule for new employees'),
                            ]),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('attendance::attendance.notes'))
                            ->rows(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('attendance::attendance.schedule_code'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('attendance::attendance.schedule_name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('attendance::attendance.schedule_type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => WorkingSchedule::TYPES[$state] ?? $state)
                    ->color(fn ($state) => WorkingSchedule::TYPE_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('formatted_time_range')
                    ->label('Working Hours'),

                Tables\Columns\TextColumn::make('working_days_list')
                    ->label(__('attendance::attendance.working_days')),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('attendance::attendance.branch'))
                    ->placeholder('Global')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_default')
                    ->label(__('attendance::attendance.is_default'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('attendance::attendance.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => WorkingSchedule::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => WorkingSchedule::STATUS_COLORS[$state] ?? 'gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(WorkingSchedule::TYPES),

                Tables\Filters\SelectFilter::make('status')
                    ->options(WorkingSchedule::STATUSES),

                Tables\Filters\SelectFilter::make('branch_id')
                    ->relationship('branch', 'name')
                    ->label(__('attendance::attendance.branch')),

                Tables\Filters\TernaryFilter::make('is_default'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('set_default')
                    ->label('Set as Default')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->visible(fn (WorkingSchedule $record) => !$record->is_default && $record->status === WorkingSchedule::STATUS_ACTIVE)
                    ->requiresConfirmation()
                    ->action(function (WorkingSchedule $record) {
                        app(WorkingScheduleService::class)->setAsDefault($record);

                        Notification::make()
                            ->title('Schedule set as default')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('New Schedule Name')
                            ->required(),

                        Forms\Components\TextInput::make('code')
                            ->label('New Schedule Code')
                            ->required()
                            ->unique(WorkingSchedule::class, 'code'),
                    ])
                    ->action(function (WorkingSchedule $record, array $data) {
                        app(WorkingScheduleService::class)->duplicateSchedule(
                            $record,
                            $data['name'],
                            $data['code']
                        );

                        Notification::make()
                            ->title('Schedule duplicated successfully')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->before(function (WorkingSchedule $record, Tables\Actions\DeleteAction $action) {
                        $service = app(WorkingScheduleService::class);
                        if (!$service->canDeleteSchedule($record)) {
                            Notification::make()
                                ->title('Cannot delete schedule')
                                ->body('This schedule is in use or is the only active schedule.')
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RulesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkingSchedules::route('/'),
            'create' => Pages\CreateWorkingSchedule::route('/create'),
            'view' => Pages\ViewWorkingSchedule::route('/{record}'),
            'edit' => Pages\EditWorkingSchedule::route('/{record}/edit'),
        ];
    }
}
