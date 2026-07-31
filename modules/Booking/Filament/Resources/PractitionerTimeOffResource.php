<?php

namespace Modules\Booking\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Booking\Filament\Resources\PractitionerTimeOffResource\Pages;
use Modules\Booking\Models\PractitionerTimeOff;
use Modules\Booking\Models\TimeOffAllocation;
use Modules\Booking\Models\TimeOffType;
use Modules\Auth\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PractitionerTimeOffResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = PractitionerTimeOff::class;

    protected static ?string $moduleCode = 'booking';

    protected static ?string $permissionKey = 'practitioner_time_off';

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('booking::time_off.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('booking::time_off.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('booking::time_off.plural');
    }

    /**
     * Recalculate days/hours requested based on dates and times.
     * For day-based types: calendar days between start and end dates
     * For hour-based types: hours between start and end time
     */
    protected static function recalculateDays(Forms\Get $get, Forms\Set $set): void
    {
        $startDate = $get('start_date');
        $endDate = $get('end_date');
        $isFullDay = $get('is_full_day');
        $timeOffTypeId = $get('time_off_type_id');

        if (!$startDate) {
            return;
        }

        // Get the time off type to determine if it's hour-based
        $type = $timeOffTypeId ? TimeOffType::find($timeOffTypeId) : null;
        $isHourBased = $type?->isHourBased() ?? false;

        if ($isHourBased) {
            // Hour-based calculation: hours between start and end time
            $startTime = $get('start_time');
            $endTime = $get('end_time');

            if ($startTime && $endTime) {
                $hours = PractitionerTimeOff::calculateHoursFromTimeRange($startTime, $endTime);
                $set('hours_requested', round($hours, 2));
                // Also set days_requested for backwards compatibility
                $hoursPerDay = $type->hours_per_day ?? 8;
                $set('days_requested', round($hours / $hoursPerDay, 2));
            }
        } else {
            // Day-based calculation
            if (!$endDate) {
                return;
            }

            if ($isFullDay) {
                // Full day calculation: number of calendar days
                $days = \Carbon\Carbon::parse($startDate)->diffInDays(\Carbon\Carbon::parse($endDate)) + 1;
                $set('days_requested', $days);
            } else {
                // Partial day calculation: hours / hours_per_day for each day
                $startTime = $get('start_time');
                $endTime = $get('end_time');

                if (!$startTime || !$endTime) {
                    return;
                }

                $calendarDays = \Carbon\Carbon::parse($startDate)->diffInDays(\Carbon\Carbon::parse($endDate)) + 1;

                // Calculate hours for the partial day portion
                $hours = PractitionerTimeOff::calculateHoursFromTimeRange($startTime, $endTime);

                // Convert hours to fraction of a day
                $hoursPerDay = $type?->hours_per_day ?? 8;
                $fractionPerDay = round($hours / $hoursPerDay, 2);

                // Total days = fraction per day * number of calendar days
                $totalDays = $fractionPerDay * $calendarDays;

                $set('days_requested', max(0.5, round($totalDays, 1)));
            }
        }
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('booking::time_off.sections.request'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('user_id')
                                    ->label(__('booking::time_off.fields.staff'))
                                    ->options(fn () => User::query()->get()->pluck('full_name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if ($state) {
                                            $user = User::find($state);
                                            if ($user && $user->branch_id) {
                                                $set('branch_id', $user->branch_id);
                                            }
                                        }
                                    }),

                                Forms\Components\Select::make('time_off_type_id')
                                    ->label(__('booking::time_off.fields.time_off_type'))
                                    ->options(function (Forms\Get $get) {
                                        $userId = $get('user_id');
                                        if (!$userId) {
                                            return [];
                                        }

                                        // Get only time off types that have allocations for this user
                                        $types = TimeOffType::active()->ordered()->get();

                                        return $types->mapWithKeys(function ($type) use ($userId) {
                                            // Check if allocation exists for current period
                                            $allocation = TimeOffAllocation::getForDate(
                                                $userId,
                                                $type->id,
                                                now()
                                            );

                                            // Skip types without allocations
                                            if (!$allocation) {
                                                return [];
                                            }

                                            $typeName = $type->translated_name;
                                            $remaining = $allocation->display_value;
                                            $total = $allocation->total_display_value;

                                            return [
                                                $type->id => "{$typeName} ({$remaining} / {$total})"
                                            ];
                                        })->filter()->toArray();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                        if (!$state) {
                                            return;
                                        }

                                        $type = TimeOffType::find($state);

                                        // For hour-based types, set is_full_day to false
                                        if ($type?->isHourBased()) {
                                            $set('is_full_day', false);
                                        }

                                        // Recalculate
                                        static::recalculateDays($get, $set);
                                    })
                                    ->helperText(function (Forms\Get $get) {
                                        $userId = $get('user_id');
                                        if (!$userId) {
                                            return __('booking::time_off.fields.select_staff_first');
                                        }

                                        // Check if user has any allocations
                                        $hasAllocations = TimeOffAllocation::where('user_id', $userId)
                                            ->where(function ($query) {
                                                $query->where('year', now()->year)
                                                    ->where(function ($q) {
                                                        $q->whereNull('month')
                                                            ->orWhere('month', now()->month);
                                                    });
                                            })
                                            ->exists();

                                        if (!$hasAllocations) {
                                            return __('booking::time_off.fields.no_allocations');
                                        }

                                        $typeId = $get('time_off_type_id');
                                        if ($userId && $typeId) {
                                            $type = TimeOffType::find($typeId);
                                            $allocation = TimeOffAllocation::getForDate($userId, $typeId, now());

                                            if ($allocation && $type) {
                                                $periodLabel = $type->isMonthly()
                                                    ? __('booking::time_off.fields.remaining_this_month', ['value' => $allocation->display_value])
                                                    : __('booking::time_off.fields.remaining_this_year', ['value' => $allocation->display_value]);
                                                return $periodLabel;
                                            }
                                        }
                                        return null;
                                    }),
                            ]),

                        Forms\Components\Select::make('branch_id')
                            ->label(__('booking::time_off.fields.branch'))
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload()
                            ->disabled()
                            ->dehydrated()
                            ->helperText(__('booking::time_off.fields.branch_auto')),
                    ]),

                Forms\Components\Section::make(__('booking::time_off.sections.period'))
                    ->schema([
                        Forms\Components\Toggle::make('is_full_day')
                            ->label(__('booking::time_off.fields.is_full_day'))
                            ->default(true)
                            ->live()
                            ->visible(fn (Forms\Get $get) => !($get('time_off_type_id') && TimeOffType::find($get('time_off_type_id'))?->isHourBased()))
                            ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                static::recalculateDays($get, $set);
                            }),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\DatePicker::make('start_date')
                                    ->label(__('booking::time_off.fields.start_date'))
                                    ->native(false)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                        // For hour-based types, set end_date same as start_date
                                        $typeId = $get('time_off_type_id');
                                        if ($typeId && TimeOffType::find($typeId)?->isHourBased()) {
                                            $set('end_date', $state);
                                        }
                                        static::recalculateDays($get, $set);
                                    }),

                                Forms\Components\DatePicker::make('end_date')
                                    ->label(__('booking::time_off.fields.end_date'))
                                    ->native(false)
                                    ->required()
                                    ->afterOrEqual('start_date')
                                    ->live()
                                    ->visible(fn (Forms\Get $get) => !($get('time_off_type_id') && TimeOffType::find($get('time_off_type_id'))?->isHourBased()))
                                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                        static::recalculateDays($get, $set);
                                    }),

                                Forms\Components\TextInput::make('days_requested')
                                    ->label(__('booking::time_off.fields.days_requested'))
                                    ->numeric()
                                    ->step(0.5)
                                    ->minValue(0.5)
                                    ->visible(fn (Forms\Get $get) => $get('time_off_type_id') && !TimeOffType::find($get('time_off_type_id'))?->isHourBased())
                                    ->helperText(__('booking::time_off.fields.days_requested_help')),

                                Forms\Components\TextInput::make('hours_requested')
                                    ->label(__('booking::time_off.fields.hours_requested'))
                                    ->numeric()
                                    ->step(0.25)
                                    ->minValue(0.25)
                                    ->visible(fn (Forms\Get $get) => $get('time_off_type_id') && TimeOffType::find($get('time_off_type_id'))?->isHourBased())
                                    ->helperText(__('booking::time_off.fields.hours_requested_help')),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TimePicker::make('start_time')
                                    ->label(__('booking::time_off.fields.start_time'))
                                    ->seconds(false)
                                    ->required(fn (Forms\Get $get) => !$get('is_full_day') || ($get('time_off_type_id') && TimeOffType::find($get('time_off_type_id'))?->isHourBased()))
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                        static::recalculateDays($get, $set);
                                    }),

                                Forms\Components\TimePicker::make('end_time')
                                    ->label(__('booking::time_off.fields.end_time'))
                                    ->seconds(false)
                                    ->required(fn (Forms\Get $get) => !$get('is_full_day') || ($get('time_off_type_id') && TimeOffType::find($get('time_off_type_id'))?->isHourBased()))
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                        static::recalculateDays($get, $set);
                                    }),
                            ])
                            ->visible(fn (Forms\Get $get) => !$get('is_full_day') || ($get('time_off_type_id') && TimeOffType::find($get('time_off_type_id'))?->isHourBased())),
                    ]),

                Forms\Components\Section::make(__('booking::time_off.sections.details'))
                    ->schema([
                        Forms\Components\Textarea::make('reason')
                            ->label(__('booking::time_off.fields.reason'))
                            ->rows(2)
                            ->maxLength(500),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('booking::time_off.fields.notes'))
                            ->rows(2)
                            ->maxLength(500),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('practitioner.first_name')
                    ->label(__('booking::time_off.fields.staff'))
                    ->formatStateUsing(fn ($record) => $record->practitioner?->full_name)
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('timeOffType.translated_name')
                    ->label(__('booking::time_off.fields.time_off_type'))
                    ->badge()
                    ->color(fn ($record) => $record->timeOffType?->color ?? 'gray')
                    ->placeholder(fn ($record) => PractitionerTimeOff::TYPES[$record->type] ?? $record->type)
                    ->sortable(),

                Tables\Columns\TextColumn::make('display_duration')
                    ->label(__('booking::time_off.fields.duration'))
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('formatted_period')
                    ->label(__('booking::time_off.fields.period')),

                Tables\Columns\TextColumn::make('duration_days')
                    ->label(__('booking::time_off.fields.days'))
                    ->suffix(' ' . __('booking::appointments.days')),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('booking::time_off.fields.status'))
                    ->colors([
                        'warning' => PractitionerTimeOff::STATUS_PENDING,
                        'success' => PractitionerTimeOff::STATUS_APPROVED,
                        'danger' => PractitionerTimeOff::STATUS_REJECTED,
                        'gray' => PractitionerTimeOff::STATUS_CANCELLED,
                    ])
                    ->formatStateUsing(fn (string $state): string => PractitionerTimeOff::STATUSES[$state] ?? $state),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('booking::time_off.fields.branch'))
                    ->placeholder(__('booking::time_off.all_branches'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('approvedBy.full_name')
                    ->label(__('booking::time_off.fields.approved_by'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('booking::time_off.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label(__('booking::time_off.fields.staff'))
                    ->options(fn () => User::query()->get()->pluck('full_name', 'id'))
                    ->preload()
                    ->searchable(),

                Tables\Filters\SelectFilter::make('time_off_type_id')
                    ->label(__('booking::time_off.fields.time_off_type'))
                    ->relationship('timeOffType', 'name', fn ($query) => $query->active())
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->translated_name)
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label(__('booking::time_off.fields.status'))
                    ->options(PractitionerTimeOff::STATUSES),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label(__('booking::time_off.filters.from')),
                        Forms\Components\DatePicker::make('until')
                            ->label(__('booking::time_off.filters.until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->where('start_date', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->where('end_date', '<=', $date));
                    }),

                Tables\Filters\Filter::make('pending')
                    ->label(__('booking::time_off.filters.pending_only'))
                    ->query(fn (Builder $query): Builder => $query->pending())
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label(__('booking::time_off.actions.approve'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PractitionerTimeOff $record): bool => $record->isPending())
                    ->action(function (PractitionerTimeOff $record) {
                        if ($record->approve(auth()->id())) {
                            Notification::make()
                                ->title(__('booking::time_off.messages.approved'))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('booking::time_off.messages.approve_failed_balance'))
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('reject')
                    ->label(__('booking::time_off.actions.reject'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label(__('booking::time_off.fields.rejection_reason'))
                            ->required(),
                    ])
                    ->visible(fn (PractitionerTimeOff $record): bool => $record->isPending())
                    ->action(fn (PractitionerTimeOff $record, array $data) => $record->reject(auth()->id(), $data['notes'])),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),

                    Tables\Actions\EditAction::make()
                        ->visible(fn (PractitionerTimeOff $record): bool => $record->isPending()),

                    Tables\Actions\Action::make('cancel')
                        ->label(__('booking::time_off.actions.cancel'))
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->visible(fn (PractitionerTimeOff $record): bool => $record->isApproved() || $record->isPending())
                        ->action(fn (PractitionerTimeOff $record) => $record->cancel()),

                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('start_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPractitionerTimeOff::route('/'),
            'create' => Pages\CreatePractitionerTimeOff::route('/create'),
            'view' => Pages\ViewPractitionerTimeOff::route('/{record}'),
            'edit' => Pages\EditPractitionerTimeOff::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['practitioner', 'branch', 'approvedBy', 'timeOffType']);
    }
}
