<?php

namespace Modules\Staff\Filament\Resources;

use App\Traits\ChecksTenantModuleAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Auth\Models\User;
use Modules\Core\Models\Branch;
use Modules\Staff\Models\StaffProfile;
use Modules\Staff\Filament\Resources\StaffProfileResource\Pages;
use Modules\Staff\Filament\Resources\StaffProfileResource\RelationManagers;

class StaffProfileResource extends Resource
{
    use ChecksTenantModuleAccess;

    protected static ?string $model = StaffProfile::class;

    protected static ?string $moduleCode = 'staff';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 40;

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
                                    ->maxLength(30),

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

                Forms\Components\Section::make(__('staff::staff.sections.compensation'))
                    ->schema([
                        Forms\Components\TextInput::make('base_salary_minor')
                            ->label(__('staff::staff.fields.base_salary'))
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->suffix('cents'),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('commission_type')
                                    ->label(__('staff::staff.fields.commission_type'))
                                    ->options(StaffProfile::COMMISSION_TYPES)
                                    ->default(StaffProfile::COMMISSION_PERCENTAGE)
                                    ->required()
                                    ->reactive(),

                                Forms\Components\TextInput::make('commission_percentage')
                                    ->label(__('staff::staff.fields.commission_percentage'))
                                    ->numeric()
                                    ->required()
                                    ->default(10)
                                    ->suffix('%')
                                    ->visible(fn (Forms\Get $get) => $get('commission_type') === StaffProfile::COMMISSION_PERCENTAGE),
                            ]),
                    ]),

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
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('staff::staff.fields.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('employee_number')
                    ->label(__('staff::staff.fields.employee_number'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('job_title')
                    ->label(__('staff::staff.fields.job_title'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('staff::staff.fields.branch'))
                    ->getStateUsing(fn (StaffProfile $record) => $record->branch?->name),

                Tables\Columns\TextColumn::make('commission_type')
                    ->label(__('staff::staff.fields.commission_type'))
                    ->formatStateUsing(fn ($state) => StaffProfile::COMMISSION_TYPES[$state] ?? $state)
                    ->badge(),

                Tables\Columns\TextColumn::make('commission_percentage')
                    ->label(__('staff::staff.fields.commission'))
                    ->suffix('%'),

                Tables\Columns\TextColumn::make('pending_earnings')
                    ->label(__('staff::staff.fields.pending_earnings'))
                    ->money('EGP')
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
            RelationManagers\CommissionRulesRelationManager::class,
            RelationManagers\CommissionRecordsRelationManager::class,
            RelationManagers\SalaryStructuresRelationManager::class,
            RelationManagers\SalaryComponentsRelationManager::class,
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
