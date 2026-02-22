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
}
