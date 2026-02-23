<?php

namespace Modules\TreatmentPlans\Filament\Resources\TreatmentPlanResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Billing\Models\Payment;
use Modules\Accounting\Models\Journal;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $recordTitleAttribute = 'code';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('treatment_plans::treatment_plans.financials.deposits');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('amount')
                    ->label(__('treatment_plans::treatment_plans.financials.amount'))
                    ->required()
                    ->numeric()
                    ->minValue(0.01)
                    ->prefix(config('app.currency_symbol', '$')),

                Forms\Components\Select::make('journal_id')
                    ->label(__('treatment_plans::treatment_plans.financials.payment_method'))
                    ->options(fn () => Journal::whereIn('type', ['cash', 'bank', 'sales'])
                        ->pluck('name', 'id'))
                    ->required()
                    ->searchable(),

                Forms\Components\TextInput::make('reference_number')
                    ->label(__('treatment_plans::treatment_plans.financials.reference'))
                    ->maxLength(100),

                Forms\Components\Textarea::make('notes')
                    ->label(__('treatment_plans::treatment_plans.fields.notes'))
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('billing::billing.fields.code'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount_minor')
                    ->label(__('treatment_plans::treatment_plans.financials.amount'))
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->sortable(),

                Tables\Columns\TextColumn::make('journal.name')
                    ->label(__('treatment_plans::treatment_plans.financials.payment_method')),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('billing::billing.fields.status'))
                    ->badge()
                    ->color(fn ($state) => Payment::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\IconColumn::make('invoice_id')
                    ->label(__('billing::billing.invoice.applied'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->getStateUsing(fn ($record) => $record->invoice_id !== null),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label(__('billing::billing.fields.paid_at'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('receivedBy.name')
                    ->label(__('billing::billing.fields.received_by'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(Payment::STATUSES),

                Tables\Filters\TernaryFilter::make('assigned')
                    ->label(__('billing::billing.invoice.applied'))
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('invoice_id'),
                        false: fn ($query) => $query->whereNull('invoice_id'),
                    ),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('treatment_plans::treatment_plans.actions.collect_deposit'))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = $this->ownerRecord->tenant_id;
                        $data['patient_id'] = $this->ownerRecord->patient_id;
                        $data['branch_id'] = $this->ownerRecord->branch_id;
                        $data['amount_minor'] = (int) ($data['amount'] * 100);
                        $data['status'] = Payment::STATUS_COMPLETED;
                        $data['received_by_user_id'] = auth()->id();
                        $data['paid_at'] = now();
                        unset($data['amount']);
                        return $data;
                    })
                    ->after(function () {
                        $this->ownerRecord->recalculateFinancials();
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('paid_at', 'desc');
    }
}
