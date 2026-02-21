<?php

namespace Modules\Payroll\Filament\Resources\PayrollRunResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Modules\Payroll\Models\PayrollLine;
use Modules\Payroll\Services\SalarySlipPdfService;
use Modules\Staff\Models\StaffProfile;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Payslips';

    protected static bool $isLazy = false;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('staff_profile_id')
                    ->label(__('payroll::payroll.fields.employee'))
                    ->options(function () {
                        return StaffProfile::with('user')
                            ->where('is_active', true)
                            ->get()
                            ->pluck('user.name', 'id');
                    })
                    ->searchable()
                    ->required()
                    ->disabledOn('edit'),

                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('base_salary_minor')
                        ->label(__('payroll::payroll.fields.base_salary'))
                        ->numeric()
                        ->required()
                        ->default(0)
                        ->suffix('cents'),

                    Forms\Components\TextInput::make('commissions_minor')
                        ->label(__('payroll::payroll.fields.commissions'))
                        ->numeric()
                        ->default(0)
                        ->suffix('cents'),

                    Forms\Components\TextInput::make('bonuses_minor')
                        ->label(__('payroll::payroll.fields.bonuses'))
                        ->numeric()
                        ->default(0)
                        ->suffix('cents'),
                ]),

                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('deductions_minor')
                        ->label(__('payroll::payroll.fields.deductions'))
                        ->numeric()
                        ->default(0)
                        ->suffix('cents'),

                    Forms\Components\TextInput::make('tax_minor')
                        ->label(__('payroll::payroll.fields.tax'))
                        ->numeric()
                        ->default(0)
                        ->suffix('cents'),

                    Forms\Components\TextInput::make('social_insurance_minor')
                        ->label(__('payroll::payroll.fields.social_insurance'))
                        ->numeric()
                        ->default(0)
                        ->suffix('cents'),
                ]),

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
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('payroll::payroll.actions.add_payslip'))
                    ->visible(fn () => $this->ownerRecord->isEditable())
                    ->mutateFormDataUsing(function (array $data): array {
                        // Auto-fill from staff profile if base salary not set
                        if (empty($data['base_salary_minor']) && !empty($data['staff_profile_id'])) {
                            $staff = StaffProfile::find($data['staff_profile_id']);
                            if ($staff) {
                                $data['base_salary_minor'] = $staff->base_salary_minor;
                            }
                        }
                        return $data;
                    }),

                Tables\Actions\Action::make('generate_all')
                    ->label(__('payroll::payroll.actions.generate_all'))
                    ->icon('heroicon-o-user-group')
                    ->color('primary')
                    ->visible(fn () => $this->ownerRecord->isEditable())
                    ->requiresConfirmation()
                    ->modalHeading(__('payroll::payroll.actions.generate_all'))
                    ->modalDescription(__('payroll::payroll.messages.generate_all_confirm'))
                    ->action(function () {
                        $payrollRun = $this->ownerRecord;
                        $existingStaffIds = $payrollRun->lines()->pluck('staff_profile_id')->toArray();

                        $staffProfiles = StaffProfile::with('user')
                            ->where('is_active', true)
                            ->whereNotIn('id', $existingStaffIds)
                            ->get();

                        $count = 0;
                        foreach ($staffProfiles as $staff) {
                            $line = PayrollLine::generateFromStaffProfile($staff, $payrollRun);
                            $line->save();
                            $count++;
                        }

                        Notification::make()
                            ->title(__('payroll::payroll.messages.generated_count', ['count' => $count]))
                            ->success()
                            ->send();
                    }),
            ])
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

                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => $this->ownerRecord->isEditable()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => $this->ownerRecord->isEditable()),
                ]),
            ]);
    }
}
