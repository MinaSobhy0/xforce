<?php

namespace Modules\Staff\Filament\Resources\StaffProfileResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Payroll\Models\EmployeeSalaryComponent;
use Modules\Payroll\Models\SalaryRule;

class SalaryComponentsRelationManager extends RelationManager
{
    protected static string $relationship = 'salaryComponents';

    protected static ?string $title = null;

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('payroll::payroll.labels.salary_components');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('salary_rule_id')
                    ->label(__('payroll::payroll.fields.salary_rule'))
                    ->options(
                        SalaryRule::query()
                            ->active()
                            ->pluck('name', 'id')
                    )
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->afterStateUpdated(function (Forms\Set $set, $state) {
                        if ($state) {
                            $rule = SalaryRule::find($state);
                            if ($rule) {
                                $set('name', $rule->name);
                                $set('component_type', $rule->category?->type === 'deduction' ? 'deduction' : 'earning');
                            }
                        }
                    }),

                Forms\Components\TextInput::make('name')
                    ->label(__('payroll::payroll.fields.name'))
                    ->required()
                    ->maxLength(255)
                    ->visible(fn (Forms\Get $get) => !$get('salary_rule_id')),

                Forms\Components\Select::make('component_type')
                    ->label(__('payroll::payroll.fields.component_type'))
                    ->options([
                        EmployeeSalaryComponent::COMPONENT_TYPE_EARNING => __('payroll::payroll.component_types.earning'),
                        EmployeeSalaryComponent::COMPONENT_TYPE_DEDUCTION => __('payroll::payroll.component_types.deduction'),
                    ])
                    ->required()
                    ->default(EmployeeSalaryComponent::COMPONENT_TYPE_EARNING),

                Forms\Components\Select::make('calculation_type')
                    ->label(__('payroll::payroll.fields.calculation_type'))
                    ->options([
                        EmployeeSalaryComponent::CALCULATION_TYPE_FIXED => __('payroll::payroll.calculation_types.fixed'),
                        EmployeeSalaryComponent::CALCULATION_TYPE_PERCENTAGE => __('payroll::payroll.calculation_types.percentage'),
                        EmployeeSalaryComponent::CALCULATION_TYPE_FORMULA => __('payroll::payroll.calculation_types.formula'),
                    ])
                    ->required()
                    ->default(EmployeeSalaryComponent::CALCULATION_TYPE_FIXED)
                    ->reactive(),

                Forms\Components\TextInput::make('amount')
                    ->label(__('payroll::payroll.fields.amount'))
                    ->numeric()
                    ->required()
                    ->prefix(fn () => current_currency())
                    ->visible(fn (Forms\Get $get) => $get('calculation_type') === EmployeeSalaryComponent::CALCULATION_TYPE_FIXED),

                Forms\Components\TextInput::make('percentage')
                    ->label(__('payroll::payroll.fields.percentage'))
                    ->numeric()
                    ->suffix('%')
                    ->required()
                    ->visible(fn (Forms\Get $get) => $get('calculation_type') === EmployeeSalaryComponent::CALCULATION_TYPE_PERCENTAGE),

                Forms\Components\Textarea::make('formula')
                    ->label(__('payroll::payroll.fields.formula'))
                    ->rows(2)
                    ->required()
                    ->helperText(__('payroll::payroll.help.formula_examples'))
                    ->visible(fn (Forms\Get $get) => $get('calculation_type') === EmployeeSalaryComponent::CALCULATION_TYPE_FORMULA),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\DatePicker::make('effective_date')
                            ->label(__('payroll::payroll.fields.effective_date'))
                            ->required()
                            ->default(now()),

                        Forms\Components\DatePicker::make('end_date')
                            ->label(__('payroll::payroll.fields.end_date'))
                            ->after('effective_date'),
                    ]),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Toggle::make('is_taxable')
                            ->label(__('payroll::payroll.fields.is_taxable'))
                            ->default(true),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('payroll::payroll.fields.is_active'))
                            ->default(true),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label(__('payroll::payroll.fields.name'))
                    ->searchable(['name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('component_type')
                    ->label(__('payroll::payroll.fields.component_type'))
                    ->badge()
                    ->colors([
                        'success' => EmployeeSalaryComponent::COMPONENT_TYPE_EARNING,
                        'danger' => EmployeeSalaryComponent::COMPONENT_TYPE_DEDUCTION,
                    ])
                    ->formatStateUsing(fn ($state) => __("payroll::payroll.component_types.{$state}")),

                Tables\Columns\TextColumn::make('calculation_type')
                    ->label(__('payroll::payroll.fields.calculation_type'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => __("payroll::payroll.calculation_types.{$state}")),

                Tables\Columns\TextColumn::make('value_display')
                    ->label(__('payroll::payroll.fields.value'))
                    ->getStateUsing(function (EmployeeSalaryComponent $record) {
                        if ($record->calculation_type === EmployeeSalaryComponent::CALCULATION_TYPE_FIXED) {
                            return format_money($record->amount_minor);
                        }
                        if ($record->calculation_type === EmployeeSalaryComponent::CALCULATION_TYPE_PERCENTAGE) {
                            return $record->percentage . '%';
                        }
                        return __('payroll::payroll.fields.formula');
                    }),

                Tables\Columns\TextColumn::make('effective_date')
                    ->label(__('payroll::payroll.fields.effective_date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label(__('payroll::payroll.fields.end_date'))
                    ->date()
                    ->placeholder('-'),

                Tables\Columns\IconColumn::make('is_taxable')
                    ->label(__('payroll::payroll.fields.is_taxable'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('payroll::payroll.fields.is_active'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('component_type')
                    ->label(__('payroll::payroll.fields.component_type'))
                    ->options([
                        EmployeeSalaryComponent::COMPONENT_TYPE_EARNING => __('payroll::payroll.component_types.earning'),
                        EmployeeSalaryComponent::COMPONENT_TYPE_DEDUCTION => __('payroll::payroll.component_types.deduction'),
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('payroll::payroll.fields.is_active')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['amount_minor'] = (int) round(($data['amount'] ?? 0) * 100);
                        $data['created_by'] = auth()->id();

                        // If salary rule is selected, copy the name
                        if (!empty($data['salary_rule_id']) && empty($data['name'])) {
                            $rule = SalaryRule::find($data['salary_rule_id']);
                            $data['name'] = $rule?->name ?? 'Component';
                        }

                        unset($data['amount']);
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->slideOver()
                    ->infolist([
                        Infolists\Components\Section::make(__('payroll::payroll.labels.salary_component'))
                            ->schema([
                                Infolists\Components\TextEntry::make('display_name')
                                    ->label(__('payroll::payroll.fields.name')),
                                Infolists\Components\TextEntry::make('component_type')
                                    ->label(__('payroll::payroll.fields.component_type'))
                                    ->badge()
                                    ->colors([
                                        'success' => EmployeeSalaryComponent::COMPONENT_TYPE_EARNING,
                                        'danger' => EmployeeSalaryComponent::COMPONENT_TYPE_DEDUCTION,
                                    ])
                                    ->formatStateUsing(fn ($state) => __("payroll::payroll.component_types.{$state}")),
                                Infolists\Components\TextEntry::make('calculation_type')
                                    ->label(__('payroll::payroll.fields.calculation_type'))
                                    ->badge()
                                    ->color('gray')
                                    ->formatStateUsing(fn ($state) => __("payroll::payroll.calculation_types.{$state}")),
                            ])->columns(3),

                        Infolists\Components\Section::make(__('payroll::payroll.sections.value'))
                            ->schema([
                                Infolists\Components\TextEntry::make('amount_minor')
                                    ->label(__('payroll::payroll.fields.amount'))
                                    ->formatStateUsing(fn ($state) => format_money($state ?? 0))
                                    ->visible(fn (EmployeeSalaryComponent $record) => $record->calculation_type === EmployeeSalaryComponent::CALCULATION_TYPE_FIXED),
                                Infolists\Components\TextEntry::make('percentage')
                                    ->label(__('payroll::payroll.fields.percentage'))
                                    ->suffix('%')
                                    ->visible(fn (EmployeeSalaryComponent $record) => $record->calculation_type === EmployeeSalaryComponent::CALCULATION_TYPE_PERCENTAGE),
                                Infolists\Components\TextEntry::make('formula')
                                    ->label(__('payroll::payroll.fields.formula'))
                                    ->visible(fn (EmployeeSalaryComponent $record) => $record->calculation_type === EmployeeSalaryComponent::CALCULATION_TYPE_FORMULA),
                            ])->columns(1),

                        Infolists\Components\Section::make(__('payroll::payroll.sections.dates'))
                            ->schema([
                                Infolists\Components\TextEntry::make('effective_date')
                                    ->label(__('payroll::payroll.fields.effective_date'))
                                    ->date(),
                                Infolists\Components\TextEntry::make('end_date')
                                    ->label(__('payroll::payroll.fields.end_date'))
                                    ->date()
                                    ->placeholder('-'),
                                Infolists\Components\IconEntry::make('is_taxable')
                                    ->label(__('payroll::payroll.fields.is_taxable'))
                                    ->boolean(),
                                Infolists\Components\IconEntry::make('is_active')
                                    ->label(__('payroll::payroll.fields.is_active'))
                                    ->boolean(),
                            ])->columns(4),

                        Infolists\Components\Section::make(__('payroll::payroll.sections.linked_rule'))
                            ->schema([
                                Infolists\Components\TextEntry::make('salaryRule.name')
                                    ->label(__('payroll::payroll.fields.salary_rule'))
                                    ->placeholder(__('payroll::payroll.messages.no_rule')),
                            ])
                            ->collapsible(),
                    ]),

                Tables\Actions\Action::make('toggleActive')
                    ->label(fn (EmployeeSalaryComponent $record) => $record->is_active
                        ? __('payroll::payroll.actions.deactivate')
                        : __('payroll::payroll.actions.activate'))
                    ->icon(fn (EmployeeSalaryComponent $record) => $record->is_active
                        ? 'heroicon-o-x-circle'
                        : 'heroicon-o-check-circle')
                    ->color(fn (EmployeeSalaryComponent $record) => $record->is_active ? 'danger' : 'success')
                    ->action(fn (EmployeeSalaryComponent $record) => $record->update(['is_active' => !$record->is_active])),

                Tables\Actions\EditAction::make()
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data['amount'] = ($data['amount_minor'] ?? 0) / 100;
                        return $data;
                    })
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['amount_minor'] = (int) round(($data['amount'] ?? 0) * 100);
                        unset($data['amount']);
                        return $data;
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
