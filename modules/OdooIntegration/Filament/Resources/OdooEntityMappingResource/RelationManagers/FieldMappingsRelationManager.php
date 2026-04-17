<?php

namespace Modules\OdooIntegration\Filament\Resources\OdooEntityMappingResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\OdooIntegration\Models\OdooFieldMapping;
use Modules\OdooIntegration\Models\OdooEntityMapping;
use Modules\OdooIntegration\Enums\SyncDirection;

class FieldMappingsRelationManager extends RelationManager
{
    protected static string $relationship = 'fieldMappings';

    protected static ?string $recordTitleAttribute = 'local_field';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('odoo-integration::odoo.sections.field_mapping'))
                    ->schema([
                        Forms\Components\TextInput::make('local_field')
                            ->label(__('odoo-integration::odoo.fields.local_field'))
                            ->required()
                            ->maxLength(255)
                            ->helperText('The field name in the local XForce model (e.g., first_name, email, status)'),

                        Forms\Components\TextInput::make('odoo_field')
                            ->label(__('odoo-integration::odoo.fields.odoo_field'))
                            ->required(fn (Forms\Get $get) => empty($get('default_value')))
                            ->maxLength(255)
                            ->helperText('The field name in Odoo (leave empty if using default value only)'),

                        Forms\Components\Select::make('direction')
                            ->label(__('odoo-integration::odoo.fields.sync_direction'))
                            ->options(SyncDirection::options())
                            ->default(SyncDirection::BIDIRECTIONAL->value)
                            ->required(),

                        Forms\Components\Select::make('transform_type')
                            ->label(__('odoo-integration::odoo.fields.transform_type'))
                            ->options(OdooFieldMapping::TRANSFORM_TYPES)
                            ->default('direct')
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('transform_config', [])),

                        Forms\Components\TextInput::make('sort_order')
                            ->label(__('odoo-integration::odoo.fields.sort_order'))
                            ->numeric()
                            ->default(0),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('odoo-integration::odoo.fields.is_active'))
                            ->default(true),

                        Forms\Components\Toggle::make('is_required')
                            ->label(__('odoo-integration::odoo.fields.is_required'))
                            ->default(false),

                        Forms\Components\Toggle::make('is_key_field')
                            ->label(__('odoo-integration::odoo.fields.is_key_field'))
                            ->helperText('Key fields are used to match existing records')
                            ->default(false),

                        Forms\Components\TextInput::make('default_value')
                            ->label(__('odoo-integration::odoo.fields.default_value'))
                            ->helperText('Default value (if set, Odoo field is optional)')
                            ->live(onBlur: true),
                    ])
                    ->columns(2),

                // Transform-specific configuration
                Forms\Components\Section::make(__('odoo-integration::odoo.sections.transform_config'))
                    ->schema([
                        // Money transform config
                        Forms\Components\TextInput::make('transform_config.decimals')
                            ->label('Decimal Places')
                            ->numeric()
                            ->default(2)
                            ->helperText('Number of decimal places for money conversion')
                            ->visible(fn (Forms\Get $get) => $get('transform_type') === 'money'),

                        // Relation transform config
                        Forms\Components\Select::make('transform_config.model')
                            ->label('Related Local Model')
                            ->options(static::getLocalModels())
                            ->searchable()
                            ->helperText('The local model to resolve the relation to')
                            ->visible(fn (Forms\Get $get) => $get('transform_type') === 'relation'),

                        Forms\Components\TextInput::make('transform_config.odoo_model')
                            ->label('Related Odoo Model')
                            ->helperText('The Odoo model name (e.g., res.users, hr.employee)')
                            ->visible(fn (Forms\Get $get) => $get('transform_type') === 'relation'),

                        // Enum transform config
                        Forms\Components\KeyValue::make('transform_config.mapping')
                            ->label('Value Mapping')
                            ->keyLabel('Odoo Value')
                            ->valueLabel('Local Value')
                            ->helperText('Map Odoo values to local values (e.g., draft → pending)')
                            ->visible(fn (Forms\Get $get) => $get('transform_type') === 'enum'),

                        Forms\Components\TextInput::make('transform_config.default')
                            ->label('Default Value (if no mapping)')
                            ->visible(fn (Forms\Get $get) => $get('transform_type') === 'enum'),

                        // Translatable transform config
                        Forms\Components\Select::make('transform_config.default_locale')
                            ->label('Default Locale')
                            ->options([
                                'en' => 'English',
                                'ar' => 'Arabic',
                            ])
                            ->default('en')
                            ->visible(fn (Forms\Get $get) => $get('transform_type') === 'translatable'),

                        // Split name transform config
                        Forms\Components\Select::make('transform_config.part')
                            ->label('Name Part')
                            ->options([
                                'first' => 'First Name',
                                'last' => 'Last Name',
                            ])
                            ->default('first')
                            ->visible(fn (Forms\Get $get) => $get('transform_type') === 'split_name'),

                        // Many2Many transform config
                        Forms\Components\Select::make('transform_config.pivot_model')
                            ->label('Pivot Model')
                            ->options(static::getLocalModels())
                            ->searchable()
                            ->visible(fn (Forms\Get $get) => $get('transform_type') === 'many2many'),

                        Forms\Components\TextInput::make('transform_config.pivot_local_key')
                            ->label('Local Key in Pivot')
                            ->visible(fn (Forms\Get $get) => $get('transform_type') === 'many2many'),

                        Forms\Components\TextInput::make('transform_config.pivot_foreign_key')
                            ->label('Foreign Key in Pivot')
                            ->visible(fn (Forms\Get $get) => $get('transform_type') === 'many2many'),
                    ])
                    ->visible(fn (Forms\Get $get) => in_array($get('transform_type'), [
                        'money', 'relation', 'enum', 'translatable', 'split_name', 'many2many'
                    ])),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('local_field')
                    ->label(__('odoo-integration::odoo.fields.local_field'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('odoo_field')
                    ->label(__('odoo-integration::odoo.fields.odoo_field'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('direction')
                    ->label(__('odoo-integration::odoo.fields.sync_direction'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof SyncDirection ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof SyncDirection ? $state->color() : 'gray'),

                Tables\Columns\TextColumn::make('transform_type')
                    ->label(__('odoo-integration::odoo.fields.transform_type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => OdooFieldMapping::TRANSFORM_TYPES[$state] ?? $state)
                    ->color('info'),

                Tables\Columns\IconColumn::make('is_key_field')
                    ->label('Key')
                    ->boolean()
                    ->trueIcon('heroicon-o-key')
                    ->falseIcon('heroicon-o-minus'),

                Tables\Columns\IconColumn::make('is_required')
                    ->label('Required')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('odoo-integration::odoo.fields.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('odoo-integration::odoo.fields.is_active')),

                Tables\Filters\TernaryFilter::make('is_key_field')
                    ->label('Key Field'),

                Tables\Filters\SelectFilter::make('transform_type')
                    ->label(__('odoo-integration::odoo.fields.transform_type'))
                    ->options(OdooFieldMapping::TRANSFORM_TYPES),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                Tables\Actions\Action::make('auto_generate')
                    ->label('Auto-Generate')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Auto-Generate Field Mappings')
                    ->modalDescription('This will generate basic direct mappings for common fields. You can customize them after generation.')
                    ->action(function () {
                        $this->autoGenerateFieldMappings();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ReplicateAction::make()
                    ->excludeAttributes(['created_at', 'updated_at']),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }

    /**
     * Auto-generate field mappings based on the entity mapping configuration.
     */
    protected function autoGenerateFieldMappings(): void
    {
        $entityMapping = $this->getOwnerRecord();
        $odooModel = $entityMapping->odoo_model;

        $commonMappings = $this->getCommonFieldMappings($odooModel);

        foreach ($commonMappings as $mapping) {
            // Check if mapping already exists
            $exists = $entityMapping->fieldMappings()
                ->where('local_field', $mapping['local_field'])
                ->where('odoo_field', $mapping['odoo_field'])
                ->exists();

            if (!$exists) {
                $entityMapping->fieldMappings()->create($mapping);
            }
        }
    }

    /**
     * Get common field mappings for an Odoo model.
     */
    protected function getCommonFieldMappings(string $odooModel): array
    {
        $mappings = [
            'hr.employee' => [
                ['local_field' => 'first_name', 'odoo_field' => 'name', 'transform_type' => 'split_name', 'transform_config' => ['part' => 'first'], 'is_required' => true],
                ['local_field' => 'last_name', 'odoo_field' => 'name', 'transform_type' => 'split_name', 'transform_config' => ['part' => 'last']],
                ['local_field' => 'email', 'odoo_field' => 'work_email', 'transform_type' => 'direct', 'is_key_field' => true],
                ['local_field' => 'phone', 'odoo_field' => 'work_phone', 'transform_type' => 'direct'],
                ['local_field' => 'mobile', 'odoo_field' => 'mobile_phone', 'transform_type' => 'direct'],
                ['local_field' => 'job_title', 'odoo_field' => 'job_title', 'transform_type' => 'direct'],
                ['local_field' => 'is_active', 'odoo_field' => 'active', 'transform_type' => 'boolean'],
            ],
            'res.users' => [
                ['local_field' => 'name', 'odoo_field' => 'name', 'transform_type' => 'direct', 'is_required' => true],
                ['local_field' => 'email', 'odoo_field' => 'login', 'transform_type' => 'direct', 'is_key_field' => true],
                ['local_field' => 'is_active', 'odoo_field' => 'active', 'transform_type' => 'boolean'],
            ],
            'hr.leave' => [
                ['local_field' => 'start_date', 'odoo_field' => 'date_from', 'transform_type' => 'datetime', 'is_required' => true],
                ['local_field' => 'end_date', 'odoo_field' => 'date_to', 'transform_type' => 'datetime', 'is_required' => true],
                ['local_field' => 'days_requested', 'odoo_field' => 'number_of_days', 'transform_type' => 'direct'],
                ['local_field' => 'reason', 'odoo_field' => 'name', 'transform_type' => 'direct'],
                ['local_field' => 'status', 'odoo_field' => 'state', 'transform_type' => 'enum', 'transform_config' => [
                    'mapping' => ['draft' => 'pending', 'confirm' => 'pending', 'validate' => 'approved', 'refuse' => 'rejected'],
                    'default' => 'pending',
                ]],
            ],
            'hr.attendance' => [
                ['local_field' => 'check_in_time', 'odoo_field' => 'check_in', 'transform_type' => 'datetime', 'is_required' => true],
                ['local_field' => 'check_out_time', 'odoo_field' => 'check_out', 'transform_type' => 'datetime'],
                ['local_field' => 'working_hours', 'odoo_field' => 'worked_hours', 'transform_type' => 'direct'],
            ],
            'project.project' => [
                ['local_field' => 'name', 'odoo_field' => 'name', 'transform_type' => 'translatable', 'transform_config' => ['default_locale' => 'en'], 'is_required' => true],
                ['local_field' => 'description', 'odoo_field' => 'description', 'transform_type' => 'direct'],
                ['local_field' => 'is_active', 'odoo_field' => 'active', 'transform_type' => 'boolean'],
            ],
            'project.task' => [
                ['local_field' => 'title', 'odoo_field' => 'name', 'transform_type' => 'direct', 'is_required' => true],
                ['local_field' => 'description', 'odoo_field' => 'description', 'transform_type' => 'direct'],
                ['local_field' => 'due_date', 'odoo_field' => 'date_deadline', 'transform_type' => 'date'],
                ['local_field' => 'estimated_hours', 'odoo_field' => 'planned_hours', 'transform_type' => 'direct'],
            ],
            'account.analytic.line' => [
                ['local_field' => 'description', 'odoo_field' => 'name', 'transform_type' => 'direct'],
                ['local_field' => 'hours', 'odoo_field' => 'unit_amount', 'transform_type' => 'direct', 'is_required' => true],
                ['local_field' => 'entry_date', 'odoo_field' => 'date', 'transform_type' => 'date', 'is_required' => true],
            ],
            'hr.payslip' => [
                ['local_field' => 'number', 'odoo_field' => 'number', 'transform_type' => 'direct', 'is_key_field' => true],
                ['local_field' => 'period_start', 'odoo_field' => 'date_from', 'transform_type' => 'date', 'is_required' => true],
                ['local_field' => 'period_end', 'odoo_field' => 'date_to', 'transform_type' => 'date', 'is_required' => true],
                ['local_field' => 'status', 'odoo_field' => 'state', 'transform_type' => 'enum', 'transform_config' => [
                    'mapping' => ['draft' => 'draft', 'verify' => 'pending', 'done' => 'approved', 'cancel' => 'cancelled'],
                    'default' => 'draft',
                ]],
            ],
            'hr.salary.rule.category' => [
                ['local_field' => 'name', 'odoo_field' => 'name', 'transform_type' => 'translatable', 'transform_config' => ['default_locale' => 'en'], 'is_required' => true],
                ['local_field' => 'code', 'odoo_field' => 'code', 'transform_type' => 'direct', 'is_key_field' => true],
            ],
        ];

        // Add default mappings for all models
        $defaultMappings = [
            ['local_field' => 'odoo_id', 'odoo_field' => 'id', 'transform_type' => 'direct', 'direction' => SyncDirection::IMPORT->value, 'is_key_field' => true],
        ];

        $modelMappings = $mappings[$odooModel] ?? [];

        // Add direction to all mappings if not set
        foreach ($modelMappings as &$mapping) {
            if (!isset($mapping['direction'])) {
                $mapping['direction'] = SyncDirection::BIDIRECTIONAL->value;
            }
            if (!isset($mapping['is_active'])) {
                $mapping['is_active'] = true;
            }
        }

        return array_merge($defaultMappings, $modelMappings);
    }

    /**
     * Get list of local models for relation config.
     */
    protected static function getLocalModels(): array
    {
        return [
            \Modules\Auth\Models\User::class => 'User',
            \Modules\Staff\Models\StaffProfile::class => 'StaffProfile',
            \Modules\Payroll\Models\SalaryRuleCategory::class => 'SalaryRuleCategory',
            \Modules\Payroll\Models\SalaryStructure::class => 'SalaryStructure',
            \Modules\Payroll\Models\SalaryRule::class => 'SalaryRule',
            \Modules\Payroll\Models\PayrollRun::class => 'PayrollRun',
            \Modules\Payroll\Models\PayrollLine::class => 'PayrollLine',
            \Modules\Booking\Models\TimeOffType::class => 'TimeOffType',
            \Modules\Booking\Models\TimeOffAllocation::class => 'TimeOffAllocation',
            \Modules\Booking\Models\PractitionerTimeOff::class => 'PractitionerTimeOff',
            \Modules\Attendance\Models\Attendance::class => 'Attendance',
            \Modules\Projects\Models\Project::class => 'Project',
            \Modules\Projects\Models\ProjectTask::class => 'ProjectTask',
            \Modules\Projects\Models\ProjectTimeEntry::class => 'ProjectTimeEntry',
        ];
    }
}
