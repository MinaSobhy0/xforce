<?php

namespace Modules\Payroll\Filament\Resources;

use App\Traits\ChecksTenantModuleAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Modules\Payroll\Models\PayrollLine;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Filament\Resources\PayslipResource\Pages;

class PayslipResource extends Resource
{
    use ChecksTenantModuleAccess;

    protected static ?string $model = PayrollLine::class;

    protected static ?string $moduleCode = 'payroll';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 46;

    protected static ?string $slug = 'payslips';

    public static function getNavigationLabel(): string
    {
        return __('payroll::payroll.navigation.payslips');
    }

    public static function getModelLabel(): string
    {
        return __('payroll::payroll.labels.payslip');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payroll::payroll.labels.payslips');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('payroll::payroll.sections.employee'))
                    ->schema([
                        Forms\Components\Placeholder::make('employee_name')
                            ->label(__('payroll::payroll.fields.employee'))
                            ->content(fn (?PayrollLine $record) => $record?->staffProfile?->user?->name ?? '-'),

                        Forms\Components\Placeholder::make('job_title')
                            ->label(__('payroll::payroll.fields.job_title'))
                            ->content(fn (?PayrollLine $record) => $record?->staffProfile?->job_title ?? '-'),

                        Forms\Components\Placeholder::make('period')
                            ->label(__('payroll::payroll.fields.period'))
                            ->content(fn (?PayrollLine $record) => $record?->payrollRun?->period_label ?? '-'),
                    ])->columns(3),

                Forms\Components\Section::make(__('payroll::payroll.sections.earnings'))
                    ->schema([
                        Forms\Components\TextInput::make('base_salary_minor')
                            ->label(__('payroll::payroll.fields.base_salary'))
                            ->numeric()
                            ->required()
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
                    ])->columns(3),

                Forms\Components\Section::make(__('payroll::payroll.sections.deductions'))
                    ->schema([
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
                    ])->columns(3),

                Forms\Components\Section::make(__('payroll::payroll.sections.notes'))
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label(__('payroll::payroll.fields.notes'))
                            ->rows(2),
                    ])->collapsed(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
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
                        Infolists\Components\TextEntry::make('base_salary')
                            ->label(__('payroll::payroll.fields.base_salary'))
                            ->money('EGP'),

                        Infolists\Components\TextEntry::make('commissions')
                            ->label(__('payroll::payroll.fields.commissions'))
                            ->money('EGP'),

                        Infolists\Components\TextEntry::make('bonuses')
                            ->label(__('payroll::payroll.fields.bonuses'))
                            ->money('EGP'),

                        Infolists\Components\TextEntry::make('gross_salary_minor')
                            ->label(__('payroll::payroll.fields.gross_salary'))
                            ->formatStateUsing(fn ($state) => number_format($state / 100, 2) . ' EGP')
                            ->weight('bold'),
                    ])->columns(4),

                Infolists\Components\Section::make(__('payroll::payroll.sections.deductions'))
                    ->schema([
                        Infolists\Components\TextEntry::make('deductions')
                            ->label(__('payroll::payroll.fields.deductions'))
                            ->money('EGP'),

                        Infolists\Components\TextEntry::make('tax')
                            ->label(__('payroll::payroll.fields.tax'))
                            ->money('EGP'),

                        Infolists\Components\TextEntry::make('social_insurance')
                            ->label(__('payroll::payroll.fields.social_insurance'))
                            ->money('EGP'),

                        Infolists\Components\TextEntry::make('total_deductions_minor')
                            ->label(__('payroll::payroll.fields.total_deductions'))
                            ->formatStateUsing(fn ($state) => number_format($state / 100, 2) . ' EGP')
                            ->weight('bold'),
                    ])->columns(4),

                Infolists\Components\Section::make(__('payroll::payroll.sections.net'))
                    ->schema([
                        Infolists\Components\TextEntry::make('net_salary')
                            ->label(__('payroll::payroll.fields.net_salary'))
                            ->money('EGP')
                            ->size('lg')
                            ->weight('bold')
                            ->color('success'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payrollRun.period_label')
                    ->label(__('payroll::payroll.fields.period'))
                    ->sortable(['payroll_runs.period_year', 'payroll_runs.period_month']),

                Tables\Columns\TextColumn::make('staffProfile.user.name')
                    ->label(__('payroll::payroll.fields.employee'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('staffProfile.job_title')
                    ->label(__('payroll::payroll.fields.job_title'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('base_salary')
                    ->label(__('payroll::payroll.fields.base_salary'))
                    ->money('EGP')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('commissions')
                    ->label(__('payroll::payroll.fields.commissions'))
                    ->money('EGP'),

                Tables\Columns\TextColumn::make('bonuses')
                    ->label(__('payroll::payroll.fields.bonuses'))
                    ->money('EGP')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('deductions')
                    ->label(__('payroll::payroll.fields.deductions'))
                    ->money('EGP')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('net_salary')
                    ->label(__('payroll::payroll.fields.net_salary'))
                    ->money('EGP')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('payrollRun.status')
                    ->label(__('payroll::payroll.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => PayrollRun::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => PayrollRun::STATUS_COLORS[$state] ?? 'gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payroll_run_id')
                    ->label(__('payroll::payroll.fields.period'))
                    ->relationship('payrollRun', 'run_number')
                    ->getOptionLabelFromRecordUsing(fn (PayrollRun $record) => $record->period_label . ' (' . $record->run_number . ')'),

                Tables\Filters\SelectFilter::make('staff_profile_id')
                    ->label(__('payroll::payroll.fields.employee'))
                    ->relationship('staffProfile', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->user?->name ?? $record->id)
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label(__('payroll::payroll.fields.status'))
                    ->options(PayrollRun::STATUSES)
                    ->query(function ($query, array $data) {
                        if ($data['value']) {
                            $query->whereHas('payrollRun', fn ($q) => $q->where('status', $data['value']));
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('download_pdf')
                    ->label(__('payroll::payroll.actions.download_payslip'))
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->url(fn (PayrollLine $record) => route('payroll.payslip.download', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayslips::route('/'),
            'view' => Pages\ViewPayslip::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['payrollRun', 'staffProfile.user']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
