<?php

namespace Modules\OdooIntegration\Filament\Resources\OdooConnectionResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Modules\OdooIntegration\Models\OdooEntityMapping;
use Modules\OdooIntegration\Models\OdooFieldMapping;
use Modules\OdooIntegration\Enums\SyncDirection;
use Modules\OdooIntegration\Enums\SyncFrequency;
use Modules\OdooIntegration\Enums\ConflictResolution;
use Modules\OdooIntegration\Jobs\SyncEntityJob;

class EntityMappingsRelationManager extends RelationManager
{
    protected static string $relationship = 'entityMappings';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = null;

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('odoo-integration::odoo.labels.entity_mappings');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('odoo-integration::odoo.sections.entity_mapping'))
                    ->schema([
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
                        Forms\Components\KeyValue::make('filter_conditions')
                            ->label(__('odoo-integration::odoo.fields.filter_conditions'))
                            ->helperText(__('odoo-integration::odoo.helpers.filter_conditions')),
                    ])
                    ->collapsed(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('odoo-integration::odoo.fields.name'))
                    ->searchable()
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

                Tables\Columns\TextColumn::make('sync_records_count')
                    ->label(__('odoo-integration::odoo.fields.records'))
                    ->counts('syncRecords'),

                Tables\Columns\TextColumn::make('pending_conflicts')
                    ->label(__('odoo-integration::odoo.fields.conflicts'))
                    ->state(fn (OdooEntityMapping $record) => $record->getPendingConflictsCount())
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('odoo-integration::odoo.fields.is_active')),

                Tables\Filters\SelectFilter::make('sync_direction')
                    ->label(__('odoo-integration::odoo.fields.sync_direction'))
                    ->options(SyncDirection::options()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = current_tenant_id();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('configureFields')
                    ->label(__('odoo-integration::odoo.actions.configure_fields'))
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('info')
                    ->slideOver()
                    ->modalWidth('5xl')
                    ->fillForm(function (OdooEntityMapping $record): array {
                        // If no field mappings exist, create defaults
                        if ($record->fieldMappings()->count() === 0) {
                            $this->createDefaultFieldMappings($record);
                            $record->refresh();
                        }
                        return [];
                    })
                    ->form(function (OdooEntityMapping $record) {
                        $localFields = $this->getModelFields($record->local_model);
                        $requiredColumns = $this->getRequiredColumns($record->local_model);

                        return [
                            Forms\Components\Placeholder::make('info')
                                ->label('')
                                ->content('Configure how fields map between your local database and Odoo. Fields marked with ⚠️ are required.')
                                ->columnSpanFull(),

                            Forms\Components\Repeater::make('field_mappings')
                                ->label(__('odoo-integration::odoo.labels.field_mappings'))
                                ->relationship('fieldMappings')
                                ->schema([
                                    Forms\Components\Grid::make(5)
                                        ->schema([
                                            Forms\Components\Select::make('local_field')
                                                ->label(__('odoo-integration::odoo.fields.local_field'))
                                                ->options($localFields)
                                                ->searchable()
                                                ->required(),

                                            Forms\Components\TextInput::make('odoo_field')
                                                ->label(__('odoo-integration::odoo.fields.odoo_field'))
                                                ->required()
                                                ->placeholder('field_name'),

                                            Forms\Components\Select::make('direction')
                                                ->label(__('odoo-integration::odoo.fields.direction'))
                                                ->options(SyncDirection::options())
                                                ->default(SyncDirection::BIDIRECTIONAL->value)
                                                ->required(),

                                            Forms\Components\Select::make('transform_type')
                                                ->label(__('odoo-integration::odoo.fields.transform_type'))
                                                ->options(OdooFieldMapping::transformTypeOptions())
                                                ->default('direct')
                                                ->required(),

                                            Forms\Components\Toggle::make('is_active')
                                                ->label(__('odoo-integration::odoo.fields.is_active'))
                                                ->default(true)
                                                ->inline(false),
                                        ]),

                                    Forms\Components\Grid::make(4)
                                        ->schema([
                                            Forms\Components\Toggle::make('is_required')
                                                ->label(__('odoo-integration::odoo.fields.is_required'))
                                                ->inline(false),

                                            Forms\Components\Toggle::make('is_key_field')
                                                ->label(__('odoo-integration::odoo.fields.is_key_field'))
                                                ->inline(false),

                                            Forms\Components\TextInput::make('default_value')
                                                ->label(__('odoo-integration::odoo.fields.default_value'))
                                                ->placeholder('Default if empty'),

                                            Forms\Components\TextInput::make('sort_order')
                                                ->label(__('odoo-integration::odoo.fields.sort_order'))
                                                ->numeric()
                                                ->default(0),
                                        ]),
                                ])
                                ->defaultItems(0)
                                ->addActionLabel(__('odoo-integration::odoo.actions.add_field_mapping'))
                                ->reorderable()
                                ->collapsible()
                                ->cloneable()
                                ->itemLabel(function (array $state) use ($requiredColumns): ?string {
                                    $localField = $state['local_field'] ?? '?';
                                    $odooField = $state['odoo_field'] ?? '?';
                                    $requiredBadge = in_array($localField, $requiredColumns) ? ' ⚠️' : '';
                                    return "{$localField} → {$odooField}{$requiredBadge}";
                                }),
                        ];
                    })
                    ->action(function (OdooEntityMapping $record, array $data): void {
                        Notification::make()
                            ->title(__('odoo-integration::odoo.messages.fields_saved'))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('syncNow')
                    ->label(__('odoo-integration::odoo.actions.sync_now'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Select::make('direction')
                            ->label(__('odoo-integration::odoo.fields.direction'))
                            ->options([
                                'import' => __('odoo-integration::odoo.direction.import'),
                                'export' => __('odoo-integration::odoo.direction.export'),
                            ])
                            ->default('import')
                            ->required(),

                        Forms\Components\Toggle::make('full_sync')
                            ->label(__('odoo-integration::odoo.fields.full_sync'))
                            ->helperText(__('odoo-integration::odoo.helpers.full_sync'))
                            ->default(false),

                        Forms\Components\Toggle::make('run_in_background')
                            ->label(__('odoo-integration::odoo.fields.run_in_background'))
                            ->helperText(__('odoo-integration::odoo.helpers.run_in_background'))
                            ->default(true),
                    ])
                    ->action(function (OdooEntityMapping $record, array $data): void {
                        $syncType = ($data['full_sync'] ?? false) ? 'full' : 'delta';

                        if ($data['run_in_background'] ?? true) {
                            dispatch(new SyncEntityJob(
                                entityMappingId: $record->id,
                                syncType: $syncType,
                                triggeredBy: auth()->id(),
                            ));

                            Notification::make()
                                ->title(__('odoo-integration::odoo.messages.sync_queued'))
                                ->body(__('odoo-integration::odoo.messages.sync_queued_body'))
                                ->success()
                                ->send();
                        } else {
                            try {
                                $syncEngine = app(\Modules\OdooIntegration\Services\Sync\SyncEngine::class);
                                $syncLog = $syncEngine->syncEntity(
                                    $record,
                                    $syncType,
                                    auth()->id()
                                );

                                if ($syncLog->isComplete()) {
                                    Notification::make()
                                        ->title(__('odoo-integration::odoo.messages.sync_completed'))
                                        ->body("Processed: {$syncLog->records_processed}, Created: {$syncLog->records_created}, Updated: {$syncLog->records_updated}")
                                        ->success()
                                        ->send();
                                } else {
                                    Notification::make()
                                        ->title(__('odoo-integration::odoo.messages.sync_partial'))
                                        ->body("Failed: {$syncLog->records_failed}")
                                        ->warning()
                                        ->send();
                                }
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title(__('odoo-integration::odoo.messages.sync_failed'))
                                    ->body(substr($e->getMessage(), 0, 200))
                                    ->danger()
                                    ->send();
                            }
                        }
                    }),

                Tables\Actions\Action::make('view_mapping')
                    ->label(__('odoo-integration::odoo.actions.view_details'))
                    ->icon('heroicon-o-eye')
                    ->url(fn (OdooEntityMapping $record) => \Modules\OdooIntegration\Filament\Resources\OdooEntityMappingResource::getUrl('view', ['record' => $record])),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('priority');
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

    /**
     * Get all fields from a model for the dropdown.
     */
    protected function getModelFields(string $modelClass): array
    {
        if (!class_exists($modelClass)) {
            return [];
        }

        try {
            $model = new $modelClass;
            $table = $model->getTable();
            $columnDetails = $this->getColumnDetails($table);

            $options = [];
            foreach ($columnDetails as $column => $details) {
                $label = str_replace('_', ' ', $column);
                $label = ucwords($label);
                $requiredBadge = $details['required'] ? ' ⚠️ REQUIRED' : '';
                $options[$column] = "{$column} ({$label}){$requiredBadge}";
            }

            // Sort: required fields first, then alphabetically
            uksort($options, function ($a, $b) use ($columnDetails) {
                $aRequired = $columnDetails[$a]['required'] ?? false;
                $bRequired = $columnDetails[$b]['required'] ?? false;

                if ($aRequired && !$bRequired) return -1;
                if (!$aRequired && $bRequired) return 1;
                return strcasecmp($a, $b);
            });

            return $options;
        } catch (\Exception $e) {
            // Fallback to model's fillable
            try {
                $model = new $modelClass;
                $fillable = $model->getFillable();

                $options = [];
                foreach ($fillable as $field) {
                    $label = str_replace('_', ' ', $field);
                    $label = ucwords($label);
                    $options[$field] = "{$field} ({$label})";
                }

                asort($options);
                return $options;
            } catch (\Exception $e2) {
                return [];
            }
        }
    }

    /**
     * Get column details from database including NOT NULL constraint.
     */
    protected function getColumnDetails(string $table): array
    {
        $columns = [];

        try {
            $results = DB::connection('tenant')->select("
                SELECT
                    column_name,
                    is_nullable,
                    column_default,
                    data_type
                FROM information_schema.columns
                WHERE table_name = ?
                ORDER BY ordinal_position
            ", [$table]);

            foreach ($results as $row) {
                $columns[$row->column_name] = [
                    'required' => $row->is_nullable === 'NO' && $row->column_default === null,
                    'nullable' => $row->is_nullable === 'YES',
                    'has_default' => $row->column_default !== null,
                    'type' => $row->data_type,
                ];
            }
        } catch (\Exception $e) {
            // Return empty if query fails
        }

        return $columns;
    }

    /**
     * Get only the required (NOT NULL without default) columns.
     */
    protected function getRequiredColumns(string $modelClass): array
    {
        if (!class_exists($modelClass)) {
            return [];
        }

        try {
            $model = new $modelClass;
            $table = $model->getTable();
            $columnDetails = $this->getColumnDetails($table);

            $required = [];
            foreach ($columnDetails as $column => $details) {
                // Skip common auto-populated fields
                if (in_array($column, ['id', 'created_at', 'updated_at', 'deleted_at', 'tenant_id'])) {
                    continue;
                }

                if ($details['required']) {
                    $required[] = $column;
                }
            }

            return $required;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create default field mappings for an entity mapping.
     */
    protected function createDefaultFieldMappings(OdooEntityMapping $entityMapping): void
    {
        $defaultMappings = config("odoo-integration.default_mappings.{$entityMapping->odoo_model}", []);

        $sortOrder = 0;
        foreach ($defaultMappings as $mapping) {
            OdooFieldMapping::create([
                'entity_mapping_id' => $entityMapping->id,
                'local_field' => $mapping['local_field'],
                'odoo_field' => $mapping['odoo_field'],
                'direction' => $mapping['direction'] ?? SyncDirection::BIDIRECTIONAL->value,
                'transform_type' => $mapping['transform_type'] ?? 'direct',
                'is_required' => $mapping['is_required'] ?? false,
                'is_key_field' => $mapping['is_key_field'] ?? false,
                'is_active' => true,
                'sort_order' => $sortOrder++,
            ]);
        }
    }
}
