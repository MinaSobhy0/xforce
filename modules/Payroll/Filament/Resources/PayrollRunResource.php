<?php

namespace Modules\Payroll\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Filament\Resources\PayrollRunResource\Pages;
use Modules\Payroll\Filament\Resources\PayrollRunResource\RelationManagers;
use Modules\Payroll\Services\PayrollCalculationService;

class PayrollRunResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = PayrollRun::class;

    protected static ?string $moduleCode = 'payroll';

    protected static ?string $permissionKey = 'payroll';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('payroll::payroll.navigation.runs');
    }

    public static function getModelLabel(): string
    {
        return __('payroll::payroll.labels.run');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payroll::payroll.labels.runs');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('payroll::payroll.sections.period'))
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('run_number')
                                    ->label(__('payroll::payroll.fields.run_number'))
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->placeholder('Auto-generated'),

                                Forms\Components\Select::make('period_year')
                                    ->label(__('payroll::payroll.fields.year'))
                                    ->options(array_combine(
                                        range(date('Y') - 2, date('Y') + 1),
                                        range(date('Y') - 2, date('Y') + 1)
                                    ))
                                    ->required()
                                    ->default(date('Y')),

                                Forms\Components\Select::make('period_month')
                                    ->label(__('payroll::payroll.fields.month'))
                                    ->options([
                                        1 => 'January',
                                        2 => 'February',
                                        3 => 'March',
                                        4 => 'April',
                                        5 => 'May',
                                        6 => 'June',
                                        7 => 'July',
                                        8 => 'August',
                                        9 => 'September',
                                        10 => 'October',
                                        11 => 'November',
                                        12 => 'December',
                                    ])
                                    ->required()
                                    ->default(date('n')),
                            ]),
                    ]),

                Forms\Components\Section::make(__('payroll::payroll.sections.totals'))
                    ->schema([
                        Forms\Components\Grid::make(5)
                            ->schema([
                                Forms\Components\Placeholder::make('total_base_salary_display')
                                    ->label(__('payroll::payroll.fields.base_salary'))
                                    ->content(fn (?PayrollRun $record) => $record ? format_money($record->total_base_salary_minor) : '-'),

                                Forms\Components\Placeholder::make('total_commissions_display')
                                    ->label(__('payroll::payroll.fields.commissions'))
                                    ->content(fn (?PayrollRun $record) => $record ? format_money($record->total_commissions_minor) : '-'),

                                Forms\Components\Placeholder::make('total_bonuses_display')
                                    ->label(__('payroll::payroll.fields.bonuses'))
                                    ->content(fn (?PayrollRun $record) => $record ? format_money($record->total_bonuses_minor) : '-'),

                                Forms\Components\Placeholder::make('total_deductions_display')
                                    ->label(__('payroll::payroll.fields.deductions'))
                                    ->content(fn (?PayrollRun $record) => $record ? format_money($record->total_deductions_minor) : '-'),

                                Forms\Components\Placeholder::make('total_net_salary_display')
                                    ->label(__('payroll::payroll.fields.net_salary'))
                                    ->content(fn (?PayrollRun $record) => $record ? format_money($record->total_net_salary_minor) : '-'),
                            ]),
                    ])
                    ->visible(fn (?PayrollRun $record) => $record !== null),

                Forms\Components\Section::make(__('payroll::payroll.sections.notes'))
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label(__('payroll::payroll.fields.notes'))
                            ->rows(3),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('run_number')
                    ->label(__('payroll::payroll.fields.run_number'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_label')
                    ->label(__('payroll::payroll.fields.period'))
                    ->sortable(['period_year', 'period_month']),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('payroll::payroll.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => PayrollRun::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => PayrollRun::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('employee_count')
                    ->label(__('payroll::payroll.fields.employees'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_base_salary_minor')
                    ->label(__('payroll::payroll.fields.base_salary'))
                    ->formatStateUsing(fn ($state) => format_money($state ?? 0)),

                Tables\Columns\TextColumn::make('total_commissions_minor')
                    ->label(__('payroll::payroll.fields.commissions'))
                    ->formatStateUsing(fn ($state) => format_money($state ?? 0)),

                Tables\Columns\TextColumn::make('total_net_salary_minor')
                    ->label(__('payroll::payroll.fields.net_salary'))
                    ->formatStateUsing(fn ($state) => format_money($state ?? 0))
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label(__('payroll::payroll.fields.paid_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('payroll::payroll.fields.status'))
                    ->options(PayrollRun::STATUSES),

                Tables\Filters\SelectFilter::make('period_year')
                    ->label(__('payroll::payroll.fields.year'))
                    ->options(array_combine(
                        range(date('Y') - 2, date('Y')),
                        range(date('Y') - 2, date('Y'))
                    )),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (PayrollRun $record) => $record->isEditable()),

                Tables\Actions\Action::make('calculate')
                    ->label(__('payroll::payroll.actions.calculate'))
                    ->icon('heroicon-o-calculator')
                    ->color('primary')
                    ->visible(fn (PayrollRun $record) => $record->status === PayrollRun::STATUS_DRAFT)
                    ->requiresConfirmation()
                    ->modalHeading(__('payroll::payroll.actions.calculate'))
                    ->modalDescription(__('payroll::payroll.messages.calculate_confirm'))
                    ->action(function (PayrollRun $record) {
                        $record->startCalculation();

                        $service = app(PayrollCalculationService::class);
                        $result = $service->calculatePayrollRun($record);

                        if ($result['success']) {
                            Notification::make()
                                ->title(__('payroll::payroll.messages.calculated'))
                                ->body(__('payroll::payroll.messages.calculated_count', ['count' => $result['count']]))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('payroll::payroll.messages.calculation_failed'))
                                ->body($result['errors'][0]['error'] ?? 'Unknown error')
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('recalculate')
                    ->label(__('payroll::payroll.actions.recalculate'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (PayrollRun $record) => $record->canRecalculate())
                    ->requiresConfirmation()
                    ->modalHeading(__('payroll::payroll.actions.recalculate'))
                    ->modalDescription(__('payroll::payroll.messages.recalculate_confirm'))
                    ->action(function (PayrollRun $record) {
                        $service = app(PayrollCalculationService::class);
                        $result = $service->calculatePayrollRun($record);

                        if ($result['success']) {
                            Notification::make()
                                ->title(__('payroll::payroll.messages.recalculated'))
                                ->body(__('payroll::payroll.messages.calculated_count', ['count' => $result['count']]))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('payroll::payroll.messages.calculation_failed'))
                                ->body($result['errors'][0]['error'] ?? 'Unknown error')
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('approve')
                    ->label(__('payroll::payroll.actions.approve'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (PayrollRun $record) => $record->canTransitionTo(PayrollRun::STATUS_APPROVED))
                    ->requiresConfirmation()
                    ->action(function (PayrollRun $record) {
                        if ($record->approve(auth()->id())) {
                            Notification::make()
                                ->title(__('payroll::payroll.messages.approved'))
                                ->success()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('pay')
                    ->label(__('payroll::payroll.actions.pay'))
                    ->icon('heroicon-o-banknotes')
                    ->color('info')
                    ->visible(fn (PayrollRun $record) => in_array($record->status, [PayrollRun::STATUS_APPROVED, PayrollRun::STATUS_PROCESSING]))
                    ->requiresConfirmation()
                    ->action(function (PayrollRun $record) {
                        if ($record->markAsPaid(auth()->id())) {
                            Notification::make()
                                ->title(__('payroll::payroll.messages.paid'))
                                ->success()
                                ->send();
                        }
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (PayrollRun $record) => $record->isDeletable()),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrollRuns::route('/'),
            'create' => Pages\CreatePayrollRun::route('/create'),
            'view' => Pages\ViewPayrollRun::route('/{record}'),
            'edit' => Pages\EditPayrollRun::route('/{record}/edit'),
        ];
    }
}
