<?php

namespace Modules\Booking\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Booking\Filament\Resources\WorkScheduleResource\Pages;
use Modules\Booking\Filament\Resources\WorkScheduleResource\RelationManagers;
use Modules\Booking\Models\WorkSchedule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Models\Branch;

class WorkScheduleResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = WorkSchedule::class;

    protected static ?string $moduleCode = 'booking';

    protected static ?string $permissionKey = 'staff';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('booking::schedules.work_schedules');
    }

    public static function getModelLabel(): string
    {
        return __('booking::schedules.work_schedule');
    }

    public static function getPluralModelLabel(): string
    {
        return __('booking::schedules.work_schedules');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('booking::schedules.sections.basic_info'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('booking::schedules.fields.name'))
                                    ->placeholder('e.g., Morning Shift, Evening Shift, Full Day')
                                    ->required()
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('code')
                                    ->label(__('booking::schedules.fields.code'))
                                    ->placeholder('e.g., morning, evening, full')
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('branch_id')
                                    ->label(__('booking::schedules.fields.branch'))
                                    ->relationship('branch', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->default(function () {
                                        // Use current branch if set, otherwise get main/first active branch
                                        if ($branchId = current_branch_id()) {
                                            return $branchId;
                                        }

                                        return Branch::active()->main()->value('id')
                                            ?? Branch::active()->ordered()->value('id');
                                    })
                                    ->disabled(fn () => current_branch_id() !== null)
                                    ->dehydrated(),

                                Forms\Components\Select::make('schedule_type')
                                    ->label(__('booking::schedules.fields.schedule_type'))
                                    ->options(WorkSchedule::SCHEDULE_TYPES)
                                    ->default(WorkSchedule::TYPE_FIXED)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Forms\Set $set) => $set('weekly_hours', null)),

                                Forms\Components\ColorPicker::make('color')
                                    ->label(__('booking::schedules.fields.color')),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label(__('booking::schedules.fields.description'))
                            ->rows(2)
                            ->maxLength(500),
                    ]),

                // Flexible Schedule Section
                Forms\Components\Section::make(__('booking::schedules.sections.flexible_hours'))
                    ->description(__('booking::schedules.flexible_hours_help'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('required_hours_per_day')
                                    ->label(__('booking::schedules.fields.required_hours_per_day'))
                                    ->numeric()
                                    ->minValue(0.5)
                                    ->maxValue(24)
                                    ->step(0.5)
                                    ->suffix('hours')
                                    ->helperText(__('booking::schedules.required_hours_per_day_help')),

                                Forms\Components\TextInput::make('required_hours_per_week')
                                    ->label(__('booking::schedules.fields.required_hours_per_week'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(168)
                                    ->step(0.5)
                                    ->suffix('hours')
                                    ->helperText(__('booking::schedules.required_hours_per_week_help')),
                            ]),

                        Forms\Components\CheckboxList::make('working_days')
                            ->label(__('booking::schedules.fields.working_days'))
                            ->options(WorkSchedule::DAYS)
                            ->columns(7)
                            ->gridDirection('row')
                            ->default([0, 1, 2, 3, 4, 6]) // All days except Friday
                            ->helperText(__('booking::schedules.working_days_help')),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('schedule_type') === WorkSchedule::TYPE_FLEXIBLE),

                Forms\Components\Section::make(__('booking::schedules.sections.quick_fill'))
                    ->description(__('booking::schedules.quick_fill_help'))
                    ->schema([
                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\CheckboxList::make('quick_fill_days')
                                    ->label(__('booking::schedules.fields.select_days'))
                                    ->options(WorkSchedule::DAYS)
                                    ->columns(7)
                                    ->gridDirection('row')
                                    ->columnSpanFull()
                                    ->live()
                                    ->afterStateHydrated(function (Forms\Components\CheckboxList $component) {
                                        // Default to all days except Friday (index 5)
                                        $component->state([0, 1, 2, 3, 4, 6]);
                                    }),

                                Forms\Components\TimePicker::make('quick_fill_start')
                                    ->label(__('booking::schedules.fields.start_time'))
                                    ->seconds(false)
                                    ->default('09:00')
                                    ->live(),

                                Forms\Components\TimePicker::make('quick_fill_end')
                                    ->label(__('booking::schedules.fields.end_time'))
                                    ->seconds(false)
                                    ->default('17:00')
                                    ->live(),

                                Forms\Components\TimePicker::make('quick_fill_break_start')
                                    ->label(__('booking::schedules.fields.break_start'))
                                    ->seconds(false),

                                Forms\Components\TimePicker::make('quick_fill_break_end')
                                    ->label(__('booking::schedules.fields.break_end'))
                                    ->seconds(false),
                            ]),

                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('apply_to_days')
                                ->label(__('booking::schedules.actions.apply_to_days'))
                                ->icon('heroicon-o-check')
                                ->color('primary')
                                ->action(function (Forms\Get $get, Forms\Set $set) {
                                    $selectedDays = $get('quick_fill_days') ?? [];
                                    $startTime = $get('quick_fill_start');
                                    $endTime = $get('quick_fill_end');
                                    $breakStart = $get('quick_fill_break_start');
                                    $breakEnd = $get('quick_fill_break_end');

                                    foreach ($selectedDays as $dayIndex) {
                                        $set("weekly_hours.{$dayIndex}.is_working", true);
                                        $set("weekly_hours.{$dayIndex}.start_time", $startTime);
                                        $set("weekly_hours.{$dayIndex}.end_time", $endTime);
                                        $set("weekly_hours.{$dayIndex}.break_start", $breakStart);
                                        $set("weekly_hours.{$dayIndex}.break_end", $breakEnd);
                                    }

                                    // Set non-selected days as not working
                                    foreach (array_keys(WorkSchedule::DAYS) as $dayIndex) {
                                        if (!in_array($dayIndex, $selectedDays)) {
                                            $set("weekly_hours.{$dayIndex}.is_working", false);
                                        }
                                    }
                                }),
                        ])->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(fn ($operation) => $operation === 'edit')
                    ->visible(fn (Forms\Get $get) => $get('schedule_type') !== WorkSchedule::TYPE_FLEXIBLE),

                Forms\Components\Section::make(__('booking::schedules.sections.weekly_schedule'))
                    ->description(__('booking::schedules.weekly_schedule_help'))
                    ->schema(
                        collect(WorkSchedule::DAYS)->map(fn ($dayName, $dayIndex) =>
                            Forms\Components\Fieldset::make($dayName)
                                ->schema([
                                    Forms\Components\Toggle::make("weekly_hours.{$dayIndex}.is_working")
                                        ->label(__('booking::schedules.fields.is_working'))
                                        ->default($dayIndex !== 5) // Friday off by default
                                        ->live(),

                                    Forms\Components\TimePicker::make("weekly_hours.{$dayIndex}.start_time")
                                        ->label(__('booking::schedules.fields.start_time'))
                                        ->seconds(false)
                                        ->default('09:00')
                                        ->visible(fn (Forms\Get $get) => $get("weekly_hours.{$dayIndex}.is_working")),

                                    Forms\Components\TimePicker::make("weekly_hours.{$dayIndex}.end_time")
                                        ->label(__('booking::schedules.fields.end_time'))
                                        ->seconds(false)
                                        ->default('17:00')
                                        ->visible(fn (Forms\Get $get) => $get("weekly_hours.{$dayIndex}.is_working")),

                                    Forms\Components\TimePicker::make("weekly_hours.{$dayIndex}.break_start")
                                        ->label(__('booking::schedules.fields.break_start'))
                                        ->seconds(false)
                                        ->visible(fn (Forms\Get $get) => $get("weekly_hours.{$dayIndex}.is_working")),

                                    Forms\Components\TimePicker::make("weekly_hours.{$dayIndex}.break_end")
                                        ->label(__('booking::schedules.fields.break_end'))
                                        ->seconds(false)
                                        ->visible(fn (Forms\Get $get) => $get("weekly_hours.{$dayIndex}.is_working")),
                                ])
                                ->columns(5)
                        )->toArray()
                    )
                    ->visible(fn (Forms\Get $get) => $get('schedule_type') !== WorkSchedule::TYPE_FLEXIBLE),

                Forms\Components\Section::make(__('booking::schedules.sections.status'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('is_active')
                                    ->label(__('booking::schedules.fields.is_active'))
                                    ->default(true),

                                Forms\Components\TextInput::make('sort_order')
                                    ->label(__('booking::schedules.fields.sort_order'))
                                    ->numeric()
                                    ->default(0),
                            ]),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ColorColumn::make('color')
                    ->label('')
                    ->width(10),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('booking::schedules.fields.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('code')
                    ->label(__('booking::schedules.fields.code'))
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('schedule_type')
                    ->label(__('booking::schedules.fields.schedule_type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => WorkSchedule::SCHEDULE_TYPES[$state] ?? $state)
                    ->color(fn ($state) => $state === WorkSchedule::TYPE_FLEXIBLE ? 'success' : 'primary'),

                Tables\Columns\TextColumn::make('schedule_summary')
                    ->label(__('booking::schedules.fields.schedule'))
                    ->wrap(),

                Tables\Columns\TextColumn::make('total_weekly_hours')
                    ->label(__('booking::schedules.fields.hours_week'))
                    ->suffix('h')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('booking::schedules.fields.branch'))
                    ->placeholder(__('booking::schedules.all_branches'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('assignments_count')
                    ->label(__('booking::schedules.fields.practitioners'))
                    ->counts('assignments')
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('booking::schedules.fields.is_active'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('booking::schedules.fields.branch'))
                    ->relationship('branch', 'name'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('booking::schedules.fields.is_active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('duplicate')
                    ->label(__('booking::schedules.actions.duplicate'))
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->action(function (WorkSchedule $record) {
                        $new = $record->replicate();
                        $new->name = $record->name . ' (Copy)';
                        $new->code = null;
                        $new->save();

                        return redirect(static::getUrl('edit', ['record' => $new]));
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PractitionersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkSchedules::route('/'),
            'create' => Pages\CreateWorkSchedule::route('/create'),
            'edit' => Pages\EditWorkSchedule::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['branch']);
    }
}
