<?php

namespace Modules\Booking\Filament\Resources;

use App\Traits\ChecksTenantModuleAccess;
use Modules\Booking\Filament\Resources\WorkScheduleResource\Pages;
use Modules\Booking\Filament\Resources\WorkScheduleResource\RelationManagers;
use Modules\Booking\Models\WorkSchedule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkScheduleResource extends Resource
{
    use ChecksTenantModuleAccess;

    protected static ?string $model = WorkSchedule::class;

    protected static ?string $moduleCode = 'booking';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 11;

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

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('branch_id')
                                    ->label(__('booking::schedules.fields.branch'))
                                    ->relationship('branch', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->default(fn () => current_branch_id())
                                    ->disabled(fn () => current_branch_id() !== null)
                                    ->dehydrated(),

                                Forms\Components\ColorPicker::make('color')
                                    ->label(__('booking::schedules.fields.color')),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label(__('booking::schedules.fields.description'))
                            ->rows(2)
                            ->maxLength(500),
                    ]),

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
                    ),

                Forms\Components\Section::make(__('booking::schedules.sections.slot_settings'))
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('slot_duration')
                                    ->label(__('booking::schedules.fields.slot_duration'))
                                    ->numeric()
                                    ->default(30)
                                    ->suffix(__('booking::schedules.minutes'))
                                    ->required()
                                    ->minValue(5)
                                    ->maxValue(240),

                                Forms\Components\TextInput::make('buffer_time')
                                    ->label(__('booking::schedules.fields.buffer_time'))
                                    ->numeric()
                                    ->default(0)
                                    ->suffix(__('booking::schedules.minutes'))
                                    ->helperText(__('booking::schedules.buffer_time_help')),

                                Forms\Components\TextInput::make('max_daily_appointments')
                                    ->label(__('booking::schedules.fields.max_daily'))
                                    ->numeric()
                                    ->placeholder(__('booking::schedules.unlimited')),
                            ]),
                    ]),

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

                Tables\Columns\TextColumn::make('schedule_summary')
                    ->label(__('booking::schedules.fields.schedule'))
                    ->wrap(),

                Tables\Columns\TextColumn::make('total_weekly_hours')
                    ->label(__('booking::schedules.fields.hours_week'))
                    ->suffix('h')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('slot_duration')
                    ->label(__('booking::schedules.fields.slot'))
                    ->suffix(' min')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('booking::schedules.fields.branch'))
                    ->placeholder(__('booking::schedules.all_branches'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('practitioners_count')
                    ->label(__('booking::schedules.fields.practitioners'))
                    ->counts('practitioners')
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
