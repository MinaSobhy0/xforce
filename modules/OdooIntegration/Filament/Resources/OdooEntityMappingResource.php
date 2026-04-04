<?php

namespace Modules\OdooIntegration\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\OdooIntegration\Filament\Resources\OdooEntityMappingResource\Pages;
use Modules\OdooIntegration\Filament\Resources\OdooEntityMappingResource\RelationManagers;
use Modules\OdooIntegration\Models\OdooEntityMapping;
use Modules\OdooIntegration\Models\OdooConnection;
use Modules\OdooIntegration\Enums\SyncDirection;
use Modules\OdooIntegration\Enums\SyncFrequency;
use Modules\OdooIntegration\Enums\ConflictResolution;
use Modules\OdooIntegration\Jobs\SyncEntityJob;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class OdooEntityMappingResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = OdooEntityMapping::class;

    protected static ?string $moduleCode = 'odoo-integration';

    protected static ?string $permissionKey = 'odoo';

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 92;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationLabel(): string
    {
        return __('odoo-integration::odoo.entity_mappings');
    }

    public static function getModelLabel(): string
    {
        return __('odoo-integration::odoo.entity_mapping');
    }

    public static function getPluralModelLabel(): string
    {
        return __('odoo-integration::odoo.entity_mappings');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('odoo-integration::odoo.sections.entity_mapping'))
                    ->schema([
                        Forms\Components\Select::make('odoo_connection_id')
                            ->label(__('odoo-integration::odoo.fields.connection'))
                            ->options(OdooConnection::pluck('name', 'id'))
                            ->required()
                            ->searchable(),

                        Forms\Components\TextInput::make('name')
                            ->label(__('odoo-integration::odoo.fields.name'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('local_model')
                            ->label(__('odoo-integration::odoo.fields.local_model'))
                            ->options(static::getSyncableModels())
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                                if ($state && class_exists($state)) {
                                    $model = new $state();
                                    $set('local_table', $model->getTable());
                                }
                            }),

                        Forms\Components\TextInput::make('local_table')
                            ->label(__('odoo-integration::odoo.fields.local_table'))
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\Select::make('odoo_model')
                            ->label(__('odoo-integration::odoo.fields.odoo_model'))
                            ->options(static::getOdooModels())
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('sync_direction')
                            ->label(__('odoo-integration::odoo.fields.sync_direction'))
                            ->options(SyncDirection::options())
                            ->default(SyncDirection::IMPORT->value)
                            ->required(),

                        Forms\Components\Select::make('sync_frequency')
                            ->label(__('odoo-integration::odoo.fields.sync_frequency'))
                            ->options(SyncFrequency::options())
                            ->default(SyncFrequency::MANUAL->value)
                            ->required(),

                        Forms\Components\Select::make('conflict_resolution')
                            ->label(__('odoo-integration::odoo.fields.conflict_resolution'))
                            ->options(ConflictResolution::options())
                            ->default(ConflictResolution::MANUAL->value)
                            ->required(),

                        Forms\Components\TextInput::make('batch_size')
                            ->label(__('odoo-integration::odoo.fields.batch_size'))
                            ->numeric()
                            ->default(100)
                            ->required(),

                        Forms\Components\TextInput::make('priority')
                            ->label(__('odoo-integration::odoo.fields.priority'))
                            ->numeric()
                            ->default(50)
                            ->helperText(__('odoo-integration::odoo.helpers.priority')),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('odoo-integration::odoo.fields.is_active'))
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('odoo-integration::odoo.sections.filters'))
                    ->schema([
                        Forms\Components\Repeater::make('filter_conditions')
                            ->label(__('odoo-integration::odoo.fields.filter_conditions'))
                            ->schema([
                                Forms\Components\TextInput::make('field')
                                    ->label('Field')
                                    ->required(),
                                Forms\Components\Select::make('operator')
                                    ->label('Operator')
                                    ->options([
                                        '=' => '= (equals)',
                                        '!=' => '!= (not equals)',
                                        '>' => '> (greater than)',
                                        '<' => '< (less than)',
                                        '>=' => '>= (greater or equal)',
                                        '<=' => '<= (less or equal)',
                                        'like' => 'like (contains)',
                                        'ilike' => 'ilike (contains, case-insensitive)',
                                        'in' => 'in (list)',
                                        'not in' => 'not in (list)',
                                    ])
                                    ->required(),
                                Forms\Components\TextInput::make('value')
                                    ->label('Value')
                                    ->required(),
                            ])
                            ->columns(3)
                            ->helperText(__('odoo-integration::odoo.helpers.filter_conditions'))
                            ->collapsible()
                            ->defaultItems(0),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('odoo-integration::odoo.fields.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('connection.name')
                    ->label(__('odoo-integration::odoo.fields.connection'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('odoo_model')
                    ->label(__('odoo-integration::odoo.fields.odoo_model'))
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('sync_direction')
                    ->label(__('odoo-integration::odoo.fields.sync_direction'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof SyncDirection ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof SyncDirection ? $state->color() : 'gray'),

                Tables\Columns\TextColumn::make('sync_frequency')
                    ->label(__('odoo-integration::odoo.fields.sync_frequency'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof SyncFrequency ? $state->label() : $state),

                Tables\Columns\TextColumn::make('priority')
                    ->label(__('odoo-integration::odoo.fields.priority'))
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('odoo-integration::odoo.fields.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('field_mappings_count')
                    ->label(__('odoo-integration::odoo.fields.field_mappings'))
                    ->counts('fieldMappings')
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('odoo_connection_id')
                    ->label(__('odoo-integration::odoo.fields.connection'))
                    ->options(OdooConnection::pluck('name', 'id')),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('odoo-integration::odoo.fields.is_active')),

                Tables\Filters\SelectFilter::make('sync_direction')
                    ->label(__('odoo-integration::odoo.fields.sync_direction'))
                    ->options(SyncDirection::options()),
            ])
            ->actions([
                Tables\Actions\Action::make('sync')
                    ->label(__('odoo-integration::odoo.actions.sync'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->action(function (OdooEntityMapping $record) {
                        dispatch(new SyncEntityJob(
                            entityMappingId: $record->id,
                            syncType: 'delta',
                            triggeredBy: auth()->id(),
                        ));

                        Notification::make()
                            ->title(__('odoo-integration::odoo.messages.sync_queued'))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('priority');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\FieldMappingsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOdooEntityMappings::route('/'),
            'create' => Pages\CreateOdooEntityMapping::route('/create'),
            'view' => Pages\ViewOdooEntityMapping::route('/{record}'),
            'edit' => Pages\EditOdooEntityMapping::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount('fieldMappings');
    }

    /**
     * Get list of syncable local models.
     */
    protected static function getSyncableModels(): array
    {
        return [
            // Auth / Users
            \Modules\Auth\Models\User::class => 'User (users)',

            // Staff / HR
            \Modules\Staff\Models\StaffProfile::class => 'Staff Profile (staff_profiles)',

            // Payroll
            \Modules\Payroll\Models\SalaryRuleCategory::class => 'Salary Rule Category (salary_rule_categories)',
            \Modules\Payroll\Models\SalaryStructure::class => 'Salary Structure (salary_structures)',
            \Modules\Payroll\Models\SalaryRule::class => 'Salary Rule (salary_rules)',
            \Modules\Payroll\Models\PayrollRun::class => 'Payroll Run (payroll_runs)',
            \Modules\Payroll\Models\PayrollLine::class => 'Payroll Line (payroll_lines)',

            // Time Off / Leave
            \Modules\Booking\Models\TimeOffType::class => 'Time Off Type (time_off_types)',
            \Modules\Booking\Models\TimeOffAllocation::class => 'Time Off Allocation (time_off_allocations)',
            \Modules\Booking\Models\PractitionerTimeOff::class => 'Time Off Request (practitioner_time_off)',

            // Attendance
            \Modules\Attendance\Models\Attendance::class => 'Attendance (attendances)',

            // Projects
            \Modules\Projects\Models\Project::class => 'Project (projects)',
            \Modules\Projects\Models\ProjectTask::class => 'Project Task (project_tasks)',
            \Modules\Projects\Models\ProjectTimeEntry::class => 'Time Entry (project_time_entries)',
        ];
    }

    /**
     * Get list of common Odoo models.
     */
    protected static function getOdooModels(): array
    {
        return [
            // Users / Employees
            'res.users' => 'res.users (Users)',
            'res.partner' => 'res.partner (Partners/Contacts)',
            'hr.employee' => 'hr.employee (Employees)',
            'hr.department' => 'hr.department (Departments)',
            'hr.job' => 'hr.job (Job Positions)',

            // Payroll
            'hr.salary.rule.category' => 'hr.salary.rule.category (Salary Rule Categories)',
            'hr.payroll.structure' => 'hr.payroll.structure (Salary Structures)',
            'hr.salary.rule' => 'hr.salary.rule (Salary Rules)',
            'hr.payslip' => 'hr.payslip (Payslips)',
            'hr.payslip.line' => 'hr.payslip.line (Payslip Lines)',

            // Time Off / Leave
            'hr.leave.type' => 'hr.leave.type (Time Off Types)',
            'hr.leave.allocation' => 'hr.leave.allocation (Time Off Allocations)',
            'hr.leave' => 'hr.leave (Time Off Requests)',

            // Attendance
            'hr.attendance' => 'hr.attendance (Attendance)',

            // Projects / Timesheets
            'project.project' => 'project.project (Projects)',
            'project.task' => 'project.task (Tasks)',
            'account.analytic.line' => 'account.analytic.line (Timesheets)',
        ];
    }
}
