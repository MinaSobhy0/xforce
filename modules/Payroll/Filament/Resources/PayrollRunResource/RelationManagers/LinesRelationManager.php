<?php

namespace Modules\Payroll\Filament\Resources\PayrollRunResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Modules\Payroll\Models\PayrollLine;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Services\PayrollCalculationService;
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
                            ->mapWithKeys(fn ($staff) => [
                                $staff->id => $staff->user?->name ?? $staff->employee_number ?? 'Unknown',
                            ]);
                    })
                    ->searchable()
                    ->required()
                    ->disabledOn('edit')
                    ->reactive()
                    ->afterStateUpdated(function (Forms\Set $set, $state) {
                        if ($state) {
                            $staff = StaffProfile::with('currentSalaryStructure')->find($state);
                            if ($staff) {
                                // Use EmployeeSalaryStructure base salary if available, otherwise StaffProfile
                                $baseSalary = $staff->currentSalaryStructure?->base_salary
                                    ?? ($staff->base_salary_minor / 100);
                                $set('base_salary', $baseSalary);
                            }
                        }
                    }),

                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('base_salary')
                        ->label(__('payroll::payroll.fields.base_salary'))
                        ->numeric()
                        ->required()
                        ->default(0)
                        ->prefix(fn () => current_currency()),

                    Forms\Components\TextInput::make('commissions')
                        ->label(__('payroll::payroll.fields.commissions'))
                        ->numeric()
                        ->default(0)
                        ->prefix(fn () => current_currency()),

                    Forms\Components\TextInput::make('bonuses')
                        ->label(__('payroll::payroll.fields.bonuses'))
                        ->numeric()
                        ->default(0)
                        ->prefix(fn () => current_currency()),
                ]),

                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('deductions')
                        ->label(__('payroll::payroll.fields.deductions'))
                        ->numeric()
                        ->default(0)
                        ->prefix(fn () => current_currency()),

                    Forms\Components\TextInput::make('tax')
                        ->label(__('payroll::payroll.fields.tax'))
                        ->numeric()
                        ->default(0)
                        ->prefix(fn () => current_currency()),

                    Forms\Components\TextInput::make('social_insurance')
                        ->label(__('payroll::payroll.fields.social_insurance'))
                        ->numeric()
                        ->default(0)
                        ->prefix(fn () => current_currency()),
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
                    // `users.name` is an accessor, not a column — Filament's
                    // default searchable() would emit WHERE users.name ILIKE
                    // and crash. Search the real columns instead.
                    ->searchable(query: function ($query, string $search) {
                        return $query->whereHas('staffProfile.user', function ($q) use ($search) {
                            $q->where('first_name', 'ilike', "%{$search}%")
                                ->orWhere('last_name', 'ilike', "%{$search}%")
                                ->orWhere('email', 'ilike', "%{$search}%");
                        });
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('staffProfile.job_title')
                    ->label(__('payroll::payroll.fields.job_title')),

                Tables\Columns\TextColumn::make('base_salary_minor')
                    ->label(__('payroll::payroll.fields.base_salary'))
                    ->formatStateUsing(fn ($state) => format_money($state ?? 0)),

                Tables\Columns\TextColumn::make('allowances_minor')
                    ->label(__('payroll::payroll.fields.allowances'))
                    ->formatStateUsing(fn ($state) => format_money($state ?? 0)),

                Tables\Columns\TextColumn::make('commissions_minor')
                    ->label(__('payroll::payroll.fields.commissions'))
                    ->formatStateUsing(fn ($state) => format_money($state ?? 0)),

                Tables\Columns\TextColumn::make('bonuses_minor')
                    ->label(__('payroll::payroll.fields.bonuses'))
                    ->formatStateUsing(fn ($state) => format_money($state ?? 0)),

                Tables\Columns\TextColumn::make('deductions_minor')
                    ->label(__('payroll::payroll.fields.deductions'))
                    ->formatStateUsing(fn ($state) => format_money($state ?? 0)),

                Tables\Columns\TextColumn::make('tax_minor')
                    ->label(__('payroll::payroll.fields.tax'))
                    ->formatStateUsing(fn ($state) => format_money($state ?? 0))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('social_insurance_minor')
                    ->label(__('payroll::payroll.fields.social_insurance'))
                    ->formatStateUsing(fn ($state) => format_money($state ?? 0))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('net_salary_minor')
                    ->label(__('payroll::payroll.fields.net_salary'))
                    ->formatStateUsing(fn ($state) => format_money($state ?? 0))
                    ->weight('bold'),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('payroll::payroll.actions.add_payslip'))
                    ->visible(fn () => $this->ownerRecord->isEditable())
                    ->mutateFormDataUsing(function (array $data): array {
                        // Convert EGP to minor units
                        $data['base_salary_minor'] = (int) round(($data['base_salary'] ?? 0) * 100);
                        $data['commissions_minor'] = (int) round(($data['commissions'] ?? 0) * 100);
                        $data['bonuses_minor'] = (int) round(($data['bonuses'] ?? 0) * 100);
                        $data['deductions_minor'] = (int) round(($data['deductions'] ?? 0) * 100);
                        $data['tax_minor'] = (int) round(($data['tax'] ?? 0) * 100);
                        $data['social_insurance_minor'] = (int) round(($data['social_insurance'] ?? 0) * 100);

                        // Remove temporary fields
                        unset($data['base_salary'], $data['commissions'], $data['bonuses']);
                        unset($data['deductions'], $data['tax'], $data['social_insurance']);

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
                Tables\Actions\ViewAction::make()
                    ->slideOver()
                    ->extraModalFooterActions(fn (PayrollLine $record) => [
                        Tables\Actions\Action::make('recalculate_modal')
                            ->label(__('payroll::payroll.actions.recalculate'))
                            ->icon('heroicon-o-calculator')
                            ->color('warning')
                            ->visible(fn () => $this->ownerRecord->isEditable())
                            ->requiresConfirmation()
                            ->modalHeading(__('payroll::payroll.actions.recalculate'))
                            ->modalDescription(__('payroll::payroll.messages.recalculate_single_confirm'))
                            ->action(function () use ($record) {
                                $service = app(PayrollCalculationService::class);
                                $service->recalculatePayslip($record);

                                Notification::make()
                                    ->title(__('payroll::payroll.messages.payslip_recalculated'))
                                    ->success()
                                    ->send();
                            }),
                    ])
                    ->infolist([
                        Infolists\Components\Section::make(__('payroll::payroll.sections.employee'))
                            ->schema([
                                Infolists\Components\TextEntry::make('staffProfile.user.name')
                                    ->label(__('payroll::payroll.fields.employee')),
                                Infolists\Components\TextEntry::make('staffProfile.job_title')
                                    ->label(__('payroll::payroll.fields.job_title')),
                                Infolists\Components\TextEntry::make('payrollRun.period_label')
                                    ->label(__('payroll::payroll.fields.period')),
                                Infolists\Components\TextEntry::make('payrollRun.status')
                                    ->label(__('payroll::payroll.fields.status'))
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => PayrollRun::STATUSES[$state] ?? $state)
                                    ->color(fn ($state) => PayrollRun::STATUS_COLORS[$state] ?? 'gray'),
                            ])->columns(4),

                        Infolists\Components\Section::make(__('payroll::payroll.sections.earnings'))
                            ->schema([
                                Infolists\Components\TextEntry::make('base_salary_minor')
                                    ->label(__('payroll::payroll.fields.base_salary'))
                                    ->formatStateUsing(fn ($state) => format_money($state)),
                                Infolists\Components\TextEntry::make('allowances_minor')
                                    ->label(__('payroll::payroll.fields.allowances'))
                                    ->formatStateUsing(fn ($state) => format_money($state)),
                                Infolists\Components\TextEntry::make('commissions_minor')
                                    ->label(__('payroll::payroll.fields.commissions'))
                                    ->formatStateUsing(fn ($state) => format_money($state)),
                                Infolists\Components\TextEntry::make('bonuses_minor')
                                    ->label(__('payroll::payroll.fields.bonuses'))
                                    ->formatStateUsing(fn ($state) => format_money($state)),
                                Infolists\Components\TextEntry::make('gross_salary_minor')
                                    ->label(__('payroll::payroll.fields.gross_salary'))
                                    ->formatStateUsing(fn ($state) => format_money($state))
                                    ->weight('bold'),
                            ])->columns(5),

                        Infolists\Components\Section::make(__('payroll::payroll.sections.deductions'))
                            ->schema([
                                Infolists\Components\TextEntry::make('tax_minor')
                                    ->label(__('payroll::payroll.fields.tax'))
                                    ->formatStateUsing(fn ($state) => format_money($state)),
                                Infolists\Components\TextEntry::make('social_insurance_minor')
                                    ->label(__('payroll::payroll.fields.social_insurance'))
                                    ->formatStateUsing(fn ($state) => format_money($state)),
                                Infolists\Components\TextEntry::make('deductions_minor')
                                    ->label(__('payroll::payroll.fields.other_deductions'))
                                    ->formatStateUsing(fn ($state) => format_money($state)),
                                Infolists\Components\TextEntry::make('total_deductions_minor')
                                    ->label(__('payroll::payroll.fields.total_deductions'))
                                    ->formatStateUsing(fn ($state) => format_money($state))
                                    ->weight('bold'),
                            ])->columns(4),

                        Infolists\Components\Section::make(__('payroll::payroll.sections.net'))
                            ->schema([
                                Infolists\Components\TextEntry::make('net_salary_minor')
                                    ->label(__('payroll::payroll.fields.net_salary'))
                                    ->formatStateUsing(fn ($state) => format_money($state))
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->weight('bold')
                                    ->color('success'),
                            ]),
                    ]),

                Tables\Actions\EditAction::make()
                    ->visible(fn () => $this->ownerRecord->isEditable())
                    ->mutateRecordDataUsing(function (array $data): array {
                        // Convert minor units to EGP for editing
                        $data['base_salary'] = ($data['base_salary_minor'] ?? 0) / 100;
                        $data['commissions'] = ($data['commissions_minor'] ?? 0) / 100;
                        $data['bonuses'] = ($data['bonuses_minor'] ?? 0) / 100;
                        $data['deductions'] = ($data['deductions_minor'] ?? 0) / 100;
                        $data['tax'] = ($data['tax_minor'] ?? 0) / 100;
                        $data['social_insurance'] = ($data['social_insurance_minor'] ?? 0) / 100;
                        return $data;
                    })
                    ->mutateFormDataUsing(function (array $data): array {
                        // Convert EGP to minor units
                        $data['base_salary_minor'] = (int) round(($data['base_salary'] ?? 0) * 100);
                        $data['commissions_minor'] = (int) round(($data['commissions'] ?? 0) * 100);
                        $data['bonuses_minor'] = (int) round(($data['bonuses'] ?? 0) * 100);
                        $data['deductions_minor'] = (int) round(($data['deductions'] ?? 0) * 100);
                        $data['tax_minor'] = (int) round(($data['tax'] ?? 0) * 100);
                        $data['social_insurance_minor'] = (int) round(($data['social_insurance'] ?? 0) * 100);

                        unset($data['base_salary'], $data['commissions'], $data['bonuses']);
                        unset($data['deductions'], $data['tax'], $data['social_insurance']);

                        return $data;
                    }),

                Tables\Actions\Action::make('recalculate')
                    ->label(__('payroll::payroll.actions.recalculate'))
                    ->icon('heroicon-o-calculator')
                    ->color('warning')
                    ->visible(fn () => $this->ownerRecord->isEditable())
                    ->requiresConfirmation()
                    ->modalHeading(__('payroll::payroll.actions.recalculate'))
                    ->modalDescription(__('payroll::payroll.messages.recalculate_single_confirm'))
                    ->action(function (PayrollLine $record) {
                        $service = app(PayrollCalculationService::class);
                        $service->recalculatePayslip($record);

                        Notification::make()
                            ->title(__('payroll::payroll.messages.payslip_recalculated'))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('download_pdf')
                    ->label(__('payroll::payroll.actions.download_payslip'))
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->url(fn (PayrollLine $record) => "/payroll/payslip/{$record->id}/download")
                    ->openUrlInNewTab(),

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
