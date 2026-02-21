<?php

namespace Modules\Payroll\Filament\Resources\PayrollRunResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Payroll\Models\PayrollLine;
use Modules\Payroll\Services\SalarySlipPdfService;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Payslips';

    protected static bool $isLazy = false;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('base_salary_minor')
                    ->label(__('payroll::payroll.fields.base_salary'))
                    ->numeric()
                    ->required()
                    ->suffix('cents'),

                Forms\Components\TextInput::make('bonuses_minor')
                    ->label(__('payroll::payroll.fields.bonuses'))
                    ->numeric()
                    ->default(0)
                    ->suffix('cents'),

                Forms\Components\TextInput::make('deductions_minor')
                    ->label(__('payroll::payroll.fields.deductions'))
                    ->numeric()
                    ->default(0)
                    ->suffix('cents'),

                Forms\Components\Textarea::make('notes')
                    ->label(__('payroll::payroll.fields.notes'))
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('staffProfile.user.name')
                    ->label(__('payroll::payroll.fields.employee'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('staffProfile.job_title')
                    ->label(__('payroll::payroll.fields.job_title')),

                Tables\Columns\TextColumn::make('base_salary')
                    ->label(__('payroll::payroll.fields.base_salary'))
                    ->money('EGP'),

                Tables\Columns\TextColumn::make('commissions')
                    ->label(__('payroll::payroll.fields.commissions'))
                    ->money('EGP'),

                Tables\Columns\TextColumn::make('bonuses')
                    ->label(__('payroll::payroll.fields.bonuses'))
                    ->money('EGP'),

                Tables\Columns\TextColumn::make('deductions')
                    ->label(__('payroll::payroll.fields.deductions'))
                    ->money('EGP'),

                Tables\Columns\TextColumn::make('tax')
                    ->label(__('payroll::payroll.fields.tax'))
                    ->money('EGP')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('social_insurance')
                    ->label(__('payroll::payroll.fields.social_insurance'))
                    ->money('EGP')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('net_salary')
                    ->label(__('payroll::payroll.fields.net_salary'))
                    ->money('EGP')
                    ->weight('bold'),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => $this->ownerRecord->isEditable()),

                Tables\Actions\Action::make('download_pdf')
                    ->label(__('payroll::payroll.actions.download_payslip'))
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function (PayrollLine $record) {
                        $service = app(SalarySlipPdfService::class);
                        return $service->download($record);
                    }),
            ])
            ->bulkActions([]);
    }
}
