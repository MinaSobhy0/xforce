<?php

namespace Modules\Attendance\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Attendance\Models\Attendance;
use Modules\Attendance\Filament\Resources\AttendanceResource\Pages;
use Modules\Attendance\Filament\Resources\AttendanceResource\RelationManagers;
use Modules\Booking\Models\WorkSchedule;
use Modules\Core\Models\Branch;
use Modules\Staff\Models\StaffProfile;

class AttendanceResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = Attendance::class;

    protected static ?string $moduleCode = 'attendance';

    protected static ?string $permissionKey = 'attendance';

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getNavigationLabel(): string
    {
        return __('attendance::attendance.attendances');
    }

    public static function getModelLabel(): string
    {
        return __('attendance::attendance.attendance');
    }

    public static function getPluralModelLabel(): string
    {
        return __('attendance::attendance.attendances');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('attendance::attendance.staff'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('staff_profile_id')
                                    ->label(__('attendance::attendance.staff_profile'))
                                    ->options(function () {
                                        $user = auth()->user();

                                        // Check if user has HR/admin permissions
                                        $isAdmin = $user->hasRole(['super-admin', 'super_admin', 'tenant-owner', 'tenant_owner', 'owner', 'admin'])
                                            || $user->can('staff.view');

                                        if ($isAdmin) {
                                            // Admin can see all staff
                                            return StaffProfile::with('user')
                                                ->get()
                                                ->mapWithKeys(fn ($staff) => [$staff->id => $staff->user?->name ?? "Staff #{$staff->id}"]);
                                        }

                                        // Normal user can only see themselves
                                        $ownStaffProfile = StaffProfile::where('user_id', $user->id)->first();
                                        if ($ownStaffProfile) {
                                            return [$ownStaffProfile->id => $user->name];
                                        }

                                        return [];
                                    })
                                    ->default(function () {
                                        $user = auth()->user();
                                        $ownStaffProfile = StaffProfile::where('user_id', $user->id)->first();
                                        return $ownStaffProfile?->id;
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if ($state) {
                                            $staff = StaffProfile::find($state);
                                            if ($staff) {
                                                // Auto-fill branch from staff profile
                                                $set('branch_id', $staff->branch_id);

                                                // Auto-fill working schedule from staff's primary schedule
                                                $primarySchedule = $staff->primaryWorkSchedule();
                                                if ($primarySchedule) {
                                                    $set('working_schedule_id', $primarySchedule->id);
                                                }
                                            }
                                        }
                                    }),

                                Forms\Components\Select::make('branch_id')
                                    ->label(__('attendance::attendance.branch'))
                                    ->relationship('branch', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->disabled()
                                    ->dehydrated()
                                    ->helperText(__('Auto-detected from staff profile')),
                            ]),
                    ]),

                Forms\Components\Section::make(__('attendance::attendance.attendance_date'))
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\DatePicker::make('attendance_date')
                                    ->label(__('attendance::attendance.date'))
                                    ->required()
                                    ->default(now()),

                                Forms\Components\TimePicker::make('check_in_time')
                                    ->label(__('attendance::attendance.check_in_time'))
                                    ->seconds(false),

                                Forms\Components\TimePicker::make('check_out_time')
                                    ->label(__('attendance::attendance.check_out_time'))
                                    ->seconds(false),
                            ]),
                    ]),

                Forms\Components\Section::make(__('attendance::attendance.status'))
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('attendance_type')
                                    ->label(__('attendance::attendance.type'))
                                    ->options(Attendance::TYPES)
                                    ->default(Attendance::TYPE_MANUAL)
                                    ->required(),

                                Forms\Components\Select::make('status')
                                    ->label(__('attendance::attendance.status'))
                                    ->options(Attendance::STATUSES)
                                    ->default(Attendance::STATUS_PRESENT)
                                    ->required(),

                                Forms\Components\Select::make('working_schedule_id')
                                    ->label(__('attendance::attendance.working_schedule'))
                                    ->relationship('workingSchedule', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->disabled()
                                    ->dehydrated()
                                    ->helperText(__('Auto-detected from staff schedule')),
                            ]),
                    ]),

                Forms\Components\Section::make(__('attendance::attendance.notes'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Textarea::make('late_reason')
                                    ->label(__('attendance::attendance.late_reason'))
                                    ->rows(2),

                                Forms\Components\Textarea::make('early_checkout_reason')
                                    ->label(__('attendance::attendance.early_checkout_reason'))
                                    ->rows(2),
                            ]),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('attendance::attendance.notes'))
                            ->rows(3),
                    ])
                    ->collapsed(),

                Forms\Components\Section::make(__('attendance::attendance.working_hours'))
                    ->schema([
                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\Placeholder::make('working_hours_display')
                                    ->label(__('attendance::attendance.working_hours'))
                                    ->content(fn (?Attendance $record) => $record ? $record->working_hours . ' hrs' : '-'),

                                Forms\Components\Placeholder::make('late_hours_display')
                                    ->label(__('attendance::attendance.late_hours'))
                                    ->content(fn (?Attendance $record) => $record ? $record->late_hours . ' hrs' : '-'),

                                Forms\Components\Placeholder::make('early_hours_display')
                                    ->label(__('attendance::attendance.early_hours'))
                                    ->content(fn (?Attendance $record) => $record ? $record->early_hours . ' hrs' : '-'),

                                Forms\Components\Placeholder::make('overtime_hours_display')
                                    ->label(__('attendance::attendance.overtime_hours'))
                                    ->content(fn (?Attendance $record) => $record ? $record->overtime_hours . ' hrs' : '-'),
                            ]),
                    ])
                    ->visible(fn (?Attendance $record) => $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('attendance_date')
                    ->label(__('attendance::attendance.date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('staffProfile.user.full_name')
                    ->label(__('attendance::attendance.staff'))
                    ->searchable(query: function ($query, string $search) {
                        return $query->whereHas('staffProfile.user', function ($q) use ($search) {
                            $q->where('first_name', 'ilike', "%{$search}%")
                              ->orWhere('last_name', 'ilike', "%{$search}%");
                        });
                    })
                    ->sortable(query: function ($query, string $direction) {
                        return $query->join('staff_profiles', 'attendances.staff_profile_id', '=', 'staff_profiles.id')
                            ->join('users', 'staff_profiles.user_id', '=', 'users.id')
                            ->orderBy('users.first_name', $direction);
                    }),

                Tables\Columns\TextColumn::make('check_in_time')
                    ->label(__('attendance::attendance.check_in'))
                    ->time('h:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('check_out_time')
                    ->label(__('attendance::attendance.check_out'))
                    ->time('h:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('working_hours')
                    ->label(__('attendance::attendance.working_hours'))
                    ->suffix(' hrs')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('attendance::attendance.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => Attendance::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => Attendance::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('attendance_type')
                    ->label(__('attendance::attendance.type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => Attendance::TYPES[$state] ?? $state)
                    ->color(fn ($state) => Attendance::TYPE_COLORS[$state] ?? 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('violations_count')
                    ->label(__('attendance::attendance.violations'))
                    ->badge()
                    ->color('danger')
                    ->getStateUsing(fn (?Attendance $record) => $record?->violations()->count() ?? 0),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('attendance::attendance.branch'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('attendance::attendance.status'))
                    ->options(Attendance::STATUSES),

                Tables\Filters\SelectFilter::make('attendance_type')
                    ->label(__('attendance::attendance.type'))
                    ->options(Attendance::TYPES),

                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('attendance::attendance.branch'))
                    ->relationship('branch', 'name'),

                Tables\Filters\Filter::make('attendance_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label(__('attendance::attendance.date') . ' From'),
                        Forms\Components\DatePicker::make('until')
                            ->label(__('attendance::attendance.date') . ' Until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('attendance_date', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('attendance_date', '<=', $data['until']));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('attendance_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LogsRelationManager::class,
            RelationManagers\BreaksRelationManager::class,
            RelationManagers\ViolationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendances::route('/'),
            'create' => Pages\CreateAttendance::route('/create'),
            'view' => Pages\ViewAttendance::route('/{record}'),
            'edit' => Pages\EditAttendance::route('/{record}/edit'),
        ];
    }
}
