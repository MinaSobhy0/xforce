<?php

namespace Modules\Staff\Filament\Resources\StaffProfileResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Payroll\Models\EmployeeSalaryStructure;
use Modules\Payroll\Models\SalaryStructure;

class SalaryStructuresRelationManager extends RelationManager
{
    protected static string $relationship = 'salaryStructures';

    protected static ?string $title = null;

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('payroll::payroll.labels.salary_structures');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('salary_structure_id')
                    ->label(__('payroll::payroll.labels.salary_structure'))
                    ->options(
                        SalaryStructure::query()
                            ->active()
                            ->pluck('name', 'id')
                    )
                    ->required()
                    ->searchable()
                    ->preload(),

                Forms\Components\TextInput::make('base_salary')
                    ->label(__('payroll::payroll.fields.base_salary'))
                    ->numeric()
                    ->required()
                    ->prefix(fn () => current_currency())
                    ->helperText(__('payroll::payroll.help.base_salary_employee')),

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

                Forms\Components\Toggle::make('is_current')
                    ->label(__('payroll::payroll.fields.is_current'))
                    ->default(false)
                    ->helperText(__('payroll::payroll.help.is_current')),

                Forms\Components\Textarea::make('notes')
                    ->label(__('payroll::payroll.fields.notes'))
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('salaryStructure.name')
            ->columns([
                Tables\Columns\TextColumn::make('salaryStructure.name')
                    ->label(__('payroll::payroll.labels.salary_structure'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('salaryStructure.pay_frequency')
                    ->label(__('payroll::payroll.fields.pay_frequency'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => __("payroll::payroll.pay_frequencies.{$state}")),

                Tables\Columns\TextColumn::make('base_salary_minor')
                    ->label(__('payroll::payroll.fields.base_salary'))
                    ->formatStateUsing(fn ($state) => format_money($state ?? 0))
                    ->sortable(),

                Tables\Columns\TextColumn::make('effective_date')
                    ->label(__('payroll::payroll.fields.effective_date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label(__('payroll::payroll.fields.end_date'))
                    ->date()
                    ->placeholder('-'),

                Tables\Columns\IconColumn::make('is_current')
                    ->label(__('payroll::payroll.fields.is_current'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_current')
                    ->label(__('payroll::payroll.fields.is_current')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['base_salary_minor'] = (int) round(($data['base_salary'] ?? 0) * 100);
                        $data['assigned_by'] = auth()->id();
                        unset($data['base_salary']);
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('makeCurrent')
                    ->label(__('payroll::payroll.actions.make_current'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (EmployeeSalaryStructure $record) => !$record->is_current)
                    ->requiresConfirmation()
                    ->action(fn (EmployeeSalaryStructure $record) => $record->makeCurrent()),

                Tables\Actions\EditAction::make()
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data['base_salary'] = ($data['base_salary_minor'] ?? 0) / 100;
                        return $data;
                    })
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['base_salary_minor'] = (int) round(($data['base_salary'] ?? 0) * 100);
                        unset($data['base_salary']);
                        return $data;
                    }),

                Tables\Actions\DeleteAction::make()
                    ->before(function (EmployeeSalaryStructure $record) {
                        if ($record->is_current) {
                            throw new \Exception(__('payroll::payroll.messages.cannot_delete_current'));
                        }
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('effective_date', 'desc');
    }
}
