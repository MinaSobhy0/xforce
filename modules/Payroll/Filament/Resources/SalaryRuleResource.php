<?php

namespace Modules\Payroll\Filament\Resources;

use App\Traits\ChecksTenantModuleAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Payroll\Models\SalaryRule;
use Modules\Payroll\Models\SalaryRuleCategory;
use Modules\Payroll\Filament\Resources\SalaryRuleResource\Pages;

class SalaryRuleResource extends Resource
{
    use ChecksTenantModuleAccess;

    protected static ?string $model = SalaryRule::class;

    protected static ?string $moduleCode = 'payroll';

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Payroll Settings';

    protected static ?int $navigationSort = 52;

    public static function getNavigationLabel(): string
    {
        return __('payroll::payroll.navigation.salary_rules');
    }

    public static function getModelLabel(): string
    {
        return __('payroll::payroll.labels.salary_rule');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payroll::payroll.labels.salary_rules');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('payroll::payroll.sections.basic_info'))
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('payroll::payroll.fields.name'))
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('code')
                                    ->label(__('payroll::payroll.fields.code'))
                                    ->required()
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true)
                                    ->alphaDash()
                                    ->helperText(__('payroll::payroll.help.code_unique')),

                                Forms\Components\Select::make('category_id')
                                    ->label(__('payroll::payroll.fields.category'))
                                    ->relationship('category', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('code')
                                            ->required()
                                            ->maxLength(50)
                                            ->alphaDash(),
                                        Forms\Components\Select::make('type')
                                            ->options(SalaryRuleCategory::TYPES)
                                            ->required(),
                                    ]),
                            ]),
                    ]),

                Forms\Components\Section::make(__('payroll::payroll.sections.calculation'))
                    ->schema([
                        Forms\Components\Select::make('amount_type')
                            ->label(__('payroll::payroll.fields.amount_type'))
                            ->options(SalaryRule::AMOUNT_TYPES)
                            ->required()
                            ->native(false)
                            ->live()
                            ->default(SalaryRule::AMOUNT_TYPE_FIXED),

                        // Fixed amount field
                        Forms\Components\TextInput::make('amount_fixed')
                            ->label(__('payroll::payroll.fields.amount_fixed'))
                            ->numeric()
                            ->prefix(fn () => current_currency())
                            ->visible(fn (Get $get) => $get('amount_type') === SalaryRule::AMOUNT_TYPE_FIXED)
                            ->required(fn (Get $get) => $get('amount_type') === SalaryRule::AMOUNT_TYPE_FIXED)
                            ->afterStateHydrated(function ($component, $state, $record) {
                                if ($record) {
                                    $component->state($record->amount_fixed_minor / 100);
                                }
                            })
                            ->dehydrateStateUsing(fn ($state) => null) // Handle in mutate
                            ->helperText(__('payroll::payroll.help.amount_fixed')),

                        // Percentage fields
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('amount_percentage')
                                    ->label(__('payroll::payroll.fields.amount_percentage'))
                                    ->numeric()
                                    ->suffix('%')
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->required(fn (Get $get) => $get('amount_type') === SalaryRule::AMOUNT_TYPE_PERCENTAGE),

                                Forms\Components\Select::make('percentage_base_id')
                                    ->label(__('payroll::payroll.fields.percentage_base'))
                                    ->relationship('percentageBase', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->helperText(__('payroll::payroll.help.percentage_base')),
                            ])
                            ->visible(fn (Get $get) => $get('amount_type') === SalaryRule::AMOUNT_TYPE_PERCENTAGE),

                        // Formula field
                        Forms\Components\Textarea::make('amount_formula')
                            ->label(__('payroll::payroll.fields.amount_formula'))
                            ->rows(3)
                            ->visible(fn (Get $get) => $get('amount_type') === SalaryRule::AMOUNT_TYPE_FORMULA)
                            ->required(fn (Get $get) => $get('amount_type') === SalaryRule::AMOUNT_TYPE_FORMULA)
                            ->helperText(__('payroll::payroll.help.formula_examples')),

                        // Formula Reference Section
                        Forms\Components\Section::make(__('payroll::payroll.sections.formula_reference'))
                            ->schema([
                                Forms\Components\Placeholder::make('formula_variables')
                                    ->label(__('payroll::payroll.labels.available_variables'))
                                    ->content(new \Illuminate\Support\HtmlString(static::getFormulaVariablesHtml()))
                                    ->columnSpanFull(),

                                Forms\Components\Placeholder::make('formula_functions')
                                    ->label(__('payroll::payroll.labels.available_functions'))
                                    ->content(new \Illuminate\Support\HtmlString(static::getFormulaFunctionsHtml()))
                                    ->columnSpanFull(),

                                Forms\Components\Placeholder::make('formula_examples')
                                    ->label(__('payroll::payroll.labels.formula_examples'))
                                    ->content(new \Illuminate\Support\HtmlString(static::getFormulaExamplesHtml()))
                                    ->columnSpanFull(),
                            ])
                            ->visible(fn (Get $get) => $get('amount_type') === SalaryRule::AMOUNT_TYPE_FORMULA)
                            ->collapsed()
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make(__('payroll::payroll.sections.conditions'))
                    ->schema([
                        Forms\Components\Select::make('condition_type')
                            ->label(__('payroll::payroll.fields.condition_type'))
                            ->options(SalaryRule::CONDITION_TYPES)
                            ->native(false)
                            ->live()
                            ->default(SalaryRule::CONDITION_ALWAYS),

                        Forms\Components\Textarea::make('condition_formula')
                            ->label(__('payroll::payroll.fields.condition_formula'))
                            ->rows(2)
                            ->visible(fn (Get $get) => $get('condition_type') === SalaryRule::CONDITION_FORMULA)
                            ->helperText(__('payroll::payroll.help.condition_formula')),
                    ])
                    ->collapsed(),

                Forms\Components\Section::make(__('payroll::payroll.sections.advanced'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('field_mapping')
                                    ->label(__('payroll::payroll.fields.field_mapping'))
                                    ->helperText(__('payroll::payroll.help.field_mapping')),

                                Forms\Components\TextInput::make('sequence')
                                    ->label(__('payroll::payroll.fields.sequence'))
                                    ->numeric()
                                    ->default(0)
                                    ->helperText(__('payroll::payroll.help.sequence')),
                            ]),
                    ])
                    ->collapsed(),

                Forms\Components\Section::make(__('payroll::payroll.sections.settings'))
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('payroll::payroll.fields.is_active'))
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('payroll::payroll.fields.code'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('payroll::payroll.fields.name'))
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('category.name')
                    ->label(__('payroll::payroll.fields.category'))
                    ->badge()
                    ->color(fn ($record) => $record->category?->type_color ?? 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount_type')
                    ->label(__('payroll::payroll.fields.amount_type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => SalaryRule::AMOUNT_TYPES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        SalaryRule::AMOUNT_TYPE_FIXED => 'success',
                        SalaryRule::AMOUNT_TYPE_PERCENTAGE => 'info',
                        SalaryRule::AMOUNT_TYPE_FORMULA => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('display_value')
                    ->label(__('payroll::payroll.fields.value'))
                    ->getStateUsing(fn ($record) => $record->display_value),

                Tables\Columns\TextColumn::make('sequence')
                    ->label(__('payroll::payroll.fields.sequence'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('payroll::payroll.fields.is_active'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label(__('payroll::payroll.fields.category'))
                    ->relationship('category', 'name'),

                Tables\Filters\SelectFilter::make('amount_type')
                    ->label(__('payroll::payroll.fields.amount_type'))
                    ->options(SalaryRule::AMOUNT_TYPES),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('payroll::payroll.fields.is_active')),
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
            ->defaultSort('sequence')
            ->reorderable('sequence');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalaryRules::route('/'),
            'create' => Pages\CreateSalaryRule::route('/create'),
            'view' => Pages\ViewSalaryRule::route('/{record}'),
            'edit' => Pages\EditSalaryRule::route('/{record}/edit'),
        ];
    }

    /**
     * Get HTML for available formula variables.
     */
    protected static function getFormulaVariablesHtml(): string
    {
        $variables = [
            __('payroll::payroll.formula_ref.salary_variables') => [
                'base_salary' => __('payroll::payroll.formula_ref.base_salary'),
                'BASIC' => __('payroll::payroll.formula_ref.basic_alias'),
                'daily_rate' => __('payroll::payroll.formula_ref.daily_rate'),
                'hourly_rate' => __('payroll::payroll.formula_ref.hourly_rate'),
            ],
            __('payroll::payroll.formula_ref.time_variables') => [
                'total_days' => __('payroll::payroll.formula_ref.total_days'),
                'worked_days' => __('payroll::payroll.formula_ref.worked_days'),
                'working_days' => __('payroll::payroll.formula_ref.working_days'),
                'overtime_hours' => __('payroll::payroll.formula_ref.overtime_hours'),
                'late_minutes' => __('payroll::payroll.formula_ref.late_minutes'),
                'absence_days' => __('payroll::payroll.formula_ref.absence_days'),
            ],
            __('payroll::payroll.formula_ref.leave_variables') => [
                'paid_leave_days' => __('payroll::payroll.formula_ref.paid_leave_days'),
                'unpaid_leave_days' => __('payroll::payroll.formula_ref.unpaid_leave_days'),
                'sick_leave_days' => __('payroll::payroll.formula_ref.sick_leave_days'),
                'annual_leave_days' => __('payroll::payroll.formula_ref.annual_leave_days'),
            ],
            __('payroll::payroll.formula_ref.earnings_variables') => [
                'commission_amount' => __('payroll::payroll.formula_ref.commission_amount'),
                'commission_count' => __('payroll::payroll.formula_ref.commission_count'),
                'bonus_amount' => __('payroll::payroll.formula_ref.bonus_amount'),
            ],
            __('payroll::payroll.formula_ref.totals_variables') => [
                'GROSS' => __('payroll::payroll.formula_ref.gross'),
                'TOTAL_EARNINGS' => __('payroll::payroll.formula_ref.total_earnings'),
                'TOTAL_ALLOWANCE' => __('payroll::payroll.formula_ref.total_allowance'),
                'TOTAL_DEDUCTION' => __('payroll::payroll.formula_ref.total_deduction'),
                'NET' => __('payroll::payroll.formula_ref.net'),
            ],
            __('payroll::payroll.formula_ref.tax_variables') => [
                'taxable_income' => __('payroll::payroll.formula_ref.taxable_income'),
                'taxable_amount' => __('payroll::payroll.formula_ref.taxable_amount'),
                'SI_EMP' => __('payroll::payroll.formula_ref.si_emp'),
            ],
            __('payroll::payroll.formula_ref.deduction_variables') => [
                'loan_deduction' => __('payroll::payroll.formula_ref.loan_deduction'),
                'other_deductions' => __('payroll::payroll.formula_ref.other_deductions'),
            ],
            __('payroll::payroll.formula_ref.rule_codes') => [
                'BASIC, HRA, TA, MEAL, PHONE' => __('payroll::payroll.formula_ref.rule_codes_desc'),
                'COMM, BONUS, OT' => __('payroll::payroll.formula_ref.benefit_codes_desc'),
                'SI_EMP, TAX, ABSENCE, LATE, LOAN' => __('payroll::payroll.formula_ref.deduction_codes_desc'),
            ],
        ];

        $html = '<div class="space-y-3 text-sm">';
        foreach ($variables as $group => $vars) {
            $html .= '<div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">';
            $html .= '<div class="font-semibold text-primary-600 dark:text-primary-400 mb-2">' . e($group) . '</div>';
            $html .= '<div class="grid grid-cols-1 md:grid-cols-2 gap-1">';
            foreach ($vars as $var => $desc) {
                $html .= '<div class="flex gap-2">';
                $html .= '<code class="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-700 rounded text-xs font-mono">' . e($var) . '</code>';
                $html .= '<span class="text-gray-600 dark:text-gray-400">' . e($desc) . '</span>';
                $html .= '</div>';
            }
            $html .= '</div></div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Get HTML for available formula functions.
     */
    protected static function getFormulaFunctionsHtml(): string
    {
        $functions = [
            'min(a, b)' => __('payroll::payroll.formula_ref.fn_min'),
            'max(a, b)' => __('payroll::payroll.formula_ref.fn_max'),
            'abs(a)' => __('payroll::payroll.formula_ref.fn_abs'),
            'round(a, precision)' => __('payroll::payroll.formula_ref.fn_round'),
            'floor(a)' => __('payroll::payroll.formula_ref.fn_floor'),
            'ceil(a)' => __('payroll::payroll.formula_ref.fn_ceil'),
            'if_else(condition, true_val, false_val)' => __('payroll::payroll.formula_ref.fn_if_else'),
            'percentage(base, percent)' => __('payroll::payroll.formula_ref.fn_percentage'),
        ];

        $operators = [
            '+, -, *, /' => __('payroll::payroll.formula_ref.op_arithmetic'),
            '>, <, >=, <=, ==, !=' => __('payroll::payroll.formula_ref.op_comparison'),
            '&&, ||, !' => __('payroll::payroll.formula_ref.op_logical'),
            '? :' => __('payroll::payroll.formula_ref.op_ternary'),
        ];

        $html = '<div class="space-y-3 text-sm">';

        // Functions
        $html .= '<div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">';
        $html .= '<div class="font-semibold text-blue-600 dark:text-blue-400 mb-2">' . __('payroll::payroll.formula_ref.functions') . '</div>';
        $html .= '<div class="grid grid-cols-1 md:grid-cols-2 gap-1">';
        foreach ($functions as $fn => $desc) {
            $html .= '<div class="flex gap-2">';
            $html .= '<code class="px-1.5 py-0.5 bg-blue-100 dark:bg-blue-800 rounded text-xs font-mono">' . e($fn) . '</code>';
            $html .= '<span class="text-gray-600 dark:text-gray-400">' . e($desc) . '</span>';
            $html .= '</div>';
        }
        $html .= '</div></div>';

        // Operators
        $html .= '<div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-3">';
        $html .= '<div class="font-semibold text-purple-600 dark:text-purple-400 mb-2">' . __('payroll::payroll.formula_ref.operators') . '</div>';
        $html .= '<div class="grid grid-cols-1 md:grid-cols-2 gap-1">';
        foreach ($operators as $op => $desc) {
            $html .= '<div class="flex gap-2">';
            $html .= '<code class="px-1.5 py-0.5 bg-purple-100 dark:bg-purple-800 rounded text-xs font-mono">' . e($op) . '</code>';
            $html .= '<span class="text-gray-600 dark:text-gray-400">' . e($desc) . '</span>';
            $html .= '</div>';
        }
        $html .= '</div></div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Get HTML for formula examples.
     */
    protected static function getFormulaExamplesHtml(): string
    {
        $examples = [
            __('payroll::payroll.formula_ref.ex_housing') => 'base_salary * 0.25',
            __('payroll::payroll.formula_ref.ex_transport_cap') => 'min(base_salary * 0.10, 500)',
            __('payroll::payroll.formula_ref.ex_overtime') => '(base_salary / 30 / 8) * overtime_hours * 1.5',
            __('payroll::payroll.formula_ref.ex_absence') => '(base_salary / working_days) * absence_days',
            __('payroll::payroll.formula_ref.ex_late') => '(hourly_rate / 60) * late_minutes',
            __('payroll::payroll.formula_ref.ex_attendance_bonus') => 'absence_days == 0 && late_minutes == 0 ? 500 : 0',
            __('payroll::payroll.formula_ref.ex_gross') => 'BASIC + HRA + TA + MEAL + COMM + BONUS + OT',
            __('payroll::payroll.formula_ref.ex_net') => 'GROSS - SI_EMP - TAX - ABSENCE - LATE - LOAN',
            __('payroll::payroll.formula_ref.ex_commission_cap') => 'min(commission_amount, base_salary * 0.5)',
            __('payroll::payroll.formula_ref.ex_prorated') => 'base_salary * (worked_days / total_days)',
            __('payroll::payroll.formula_ref.ex_tiered_bonus') => 'base_salary > 10000 ? 1000 : (base_salary > 5000 ? 500 : 200)',
            __('payroll::payroll.formula_ref.ex_si_capped') => 'min(base_salary, 12600) * 0.11',
        ];

        $html = '<div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3 text-sm">';
        $html .= '<div class="font-semibold text-green-600 dark:text-green-400 mb-2">' . __('payroll::payroll.formula_ref.common_examples') . '</div>';
        $html .= '<div class="space-y-2">';
        foreach ($examples as $desc => $formula) {
            $html .= '<div class="flex flex-col md:flex-row md:items-center gap-1 md:gap-3 border-b border-green-200 dark:border-green-800 pb-2 last:border-0">';
            $html .= '<span class="text-gray-700 dark:text-gray-300 md:w-1/3">' . e($desc) . '</span>';
            $html .= '<code class="px-2 py-1 bg-green-100 dark:bg-green-800 rounded text-xs font-mono flex-1 overflow-x-auto">' . e($formula) . '</code>';
            $html .= '</div>';
        }
        $html .= '</div></div>';

        return $html;
    }
}
