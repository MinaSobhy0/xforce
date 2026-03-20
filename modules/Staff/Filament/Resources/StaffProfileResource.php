<?php

namespace Modules\Staff\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Auth\Models\User;
use Modules\Core\Models\Branch;
use Modules\Staff\Models\CommissionPlan;
use Modules\Staff\Models\StaffProfile;
use Modules\Staff\Filament\Resources\StaffProfileResource\Pages;
use Modules\Staff\Filament\Resources\StaffProfileResource\RelationManagers;
use XLinic\Framework\Core\Filament\RelationManagers\ActivityLogRelationManager;

class StaffProfileResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = StaffProfile::class;

    protected static ?string $moduleCode = 'staff';

    protected static ?string $permissionKey = 'staff';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'employee_number';

    public static function getNavigationLabel(): string
    {
        return __('staff::staff.navigation.profiles');
    }

    public static function getModelLabel(): string
    {
        return __('staff::staff.labels.profile');
    }

    public static function getPluralModelLabel(): string
    {
        return __('staff::staff.labels.profiles');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('staff::staff.sections.basic_info'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('user_id')
                                    ->label(__('staff::staff.fields.user'))
                                    ->relationship('user', 'email')
                                    ->getOptionLabelFromRecordUsing(fn (User $record) => "{$record->first_name} {$record->last_name} ({$record->email})")
                                    ->required()
                                    ->searchable(['first_name', 'last_name', 'email'])
                                    ->preload(),

                                Forms\Components\Select::make('branch_id')
                                    ->label(__('staff::staff.fields.branch'))
                                    ->relationship('branch', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->default(function () {
                                        if ($branchId = current_branch_id()) {
                                            return $branchId;
                                        }
                                        return Branch::active()->main()->value('id')
                                            ?? Branch::active()->ordered()->value('id');
                                    })
                                    ->disabled(fn () => current_branch_id() !== null)
                                    ->dehydrated(),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('employee_number')
                                    ->label(__('staff::staff.fields.employee_number'))
                                    ->maxLength(30)
                                    ->disabled(fn (?StaffProfile $record) => $record === null)
                                    ->placeholder(fn (?StaffProfile $record) => $record === null ? __('staff::staff.fields.auto_generated') : null)
                                    ->helperText(fn (?StaffProfile $record) => $record === null ? __('staff::staff.fields.employee_number_auto') : null),

                                Forms\Components\TextInput::make('job_title')
                                    ->label(__('staff::staff.fields.job_title'))
                                    ->maxLength(100),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('hire_date')
                                    ->label(__('staff::staff.fields.hire_date')),

                                Forms\Components\DatePicker::make('contract_end_date')
                                    ->label(__('staff::staff.fields.contract_end_date')),
                            ]),
                    ]),

                Forms\Components\Section::make(__('staff::staff.sections.bio'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Textarea::make('bio.en')
                                    ->label(__('staff::staff.fields.bio') . ' (English)')
                                    ->rows(3),

                                Forms\Components\Textarea::make('bio.ar')
                                    ->label(__('staff::staff.fields.bio') . ' (Arabic)')
                                    ->rows(3),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TagsInput::make('specializations.en')
                                    ->label(__('staff::staff.fields.specializations') . ' (English)'),

                                Forms\Components\TagsInput::make('specializations.ar')
                                    ->label(__('staff::staff.fields.specializations') . ' (Arabic)'),
                            ]),
                    ])
                    ->collapsed(),

                Forms\Components\Section::make(__('staff::staff.sections.commission'))
                    ->description(__('staff::staff.sections.commission_description'))
                    ->schema([
                        Forms\Components\Select::make('commission_plan_id')
                            ->label(__('staff::commission.labels.plan'))
                            ->relationship('commissionPlan', 'name')
                            ->getOptionLabelFromRecordUsing(fn (CommissionPlan $record) => "{$record->name} ({$record->formatted_default})")
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('staff::commission.fields.name'))
                                    ->required()
                                    ->maxLength(100),

                                Forms\Components\Select::make('commission_type')
                                    ->label(__('staff::commission.fields.commission_type'))
                                    ->options(CommissionPlan::TYPES)
                                    ->default(CommissionPlan::TYPE_PERCENTAGE)
                                    ->required()
                                    ->reactive(),

                                Forms\Components\TextInput::make('default_percentage')
                                    ->label(__('staff::commission.fields.percentage'))
                                    ->numeric()
                                    ->suffix('%')
                                    ->default(10)
                                    ->visible(fn (Forms\Get $get) => $get('commission_type') === CommissionPlan::TYPE_PERCENTAGE),

                                Forms\Components\TextInput::make('default_flat_amount_minor')
                                    ->label(__('staff::commission.fields.flat_amount'))
                                    ->numeric()
                                    ->prefix(current_currency())
                                    ->default(0)
                                    ->visible(fn (Forms\Get $get) => $get('commission_type') === CommissionPlan::TYPE_FLAT)
                                    ->dehydrateStateUsing(fn ($state) => (int) round(($state ?? 0) * 100)),

                                Forms\Components\Toggle::make('is_active')
                                    ->label(__('staff::commission.fields.is_active'))
                                    ->default(true),
                            ])
                            ->createOptionUsing(function (array $data): string {
                                $data['created_by'] = auth()->id();
                                return CommissionPlan::create($data)->id;
                            }),
                    ]),

                Forms\Components\Section::make(__('staff::staff.sections.attendance_settings'))
                    ->description(__('staff::staff.sections.attendance_settings_description'))
                    ->schema([
                        Forms\Components\CheckboxList::make('allowed_check_in_methods')
                            ->label(__('staff::staff.fields.allowed_check_in_methods'))
                            ->helperText(__('staff::staff.fields.allowed_check_in_methods_help'))
                            ->options(StaffProfile::CHECK_IN_METHODS)
                            ->columns(3)
                            ->nullable(),

                        Forms\Components\Select::make('allowed_geofence_locations')
                            ->label(__('staff::staff.fields.allowed_geofence_locations'))
                            ->helperText(__('staff::staff.fields.allowed_geofence_locations_help'))
                            ->multiple()
                            ->options(fn () => Branch::active()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->visible(fn (Forms\Get $get) =>
                                $get('allowed_check_in_methods') === null ||
                                in_array('geofence', $get('allowed_check_in_methods') ?? [])
                            ),
                    ])
                    ->collapsed(),

                Forms\Components\Section::make(__('staff::staff.sections.settings'))
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('staff::staff.fields.is_active'))
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.full_name')
                    ->label(__('staff::staff.fields.name'))
                    ->searchable(query: function ($query, string $search) {
                        return $query->whereHas('user', function ($q) use ($search) {
                            $q->where('first_name', 'ilike', "%{$search}%")
                              ->orWhere('last_name', 'ilike', "%{$search}%");
                        });
                    })
                    ->sortable(query: function ($query, string $direction) {
                        return $query->join('users', 'staff_profiles.user_id', '=', 'users.id')
                            ->orderBy('users.first_name', $direction);
                    }),

                Tables\Columns\TextColumn::make('employee_number')
                    ->label(__('staff::staff.fields.employee_number'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('job_title')
                    ->label(__('staff::staff.fields.job_title'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('staff::staff.fields.branch'))
                    ->getStateUsing(fn (StaffProfile $record) => $record->branch?->name),

                Tables\Columns\TextColumn::make('commissionPlan.name')
                    ->label(__('staff::commission.labels.plan'))
                    ->placeholder(__('staff::commission.messages.no_plan_assigned'))
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('commission_value')
                    ->label(__('staff::staff.fields.commission'))
                    ->getStateUsing(fn (StaffProfile $record) => $record->commissionPlan?->formatted_default ?? '-'),

                Tables\Columns\TextColumn::make('pending_earnings')
                    ->label(__('staff::staff.fields.pending_earnings'))
                    ->money(current_currency())
                    ->getStateUsing(fn (StaffProfile $record) => $record->pending_earnings / 100),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('staff::staff.fields.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('hire_date')
                    ->label(__('staff::staff.fields.hire_date'))
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('staff::staff.fields.branch'))
                    ->relationship('branch', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Branch $record) => $record->name),

                Tables\Filters\SelectFilter::make('commission_plan_id')
                    ->label(__('staff::commission.labels.plan'))
                    ->relationship('commissionPlan', 'name'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('staff::staff.fields.is_active')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
            RelationManagers\ScheduleAssignmentsRelationManager::class,
            RelationManagers\CommissionRecordsRelationManager::class,
            RelationManagers\SalaryStructuresRelationManager::class,
            RelationManagers\SalaryComponentsRelationManager::class,
            ActivityLogRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaffProfiles::route('/'),
            'create' => Pages\CreateStaffProfile::route('/create'),
            'view' => Pages\ViewStaffProfile::route('/{record}'),
            'edit' => Pages\EditStaffProfile::route('/{record}/edit'),
        ];
    }
}
