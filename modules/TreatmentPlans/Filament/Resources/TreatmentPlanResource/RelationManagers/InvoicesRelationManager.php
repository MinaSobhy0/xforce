<?php

namespace Modules\TreatmentPlans\Filament\Resources\TreatmentPlanResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Billing\Models\Invoice;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $recordTitleAttribute = 'code';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('billing::billing.navigation.invoices');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->disabled(),
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

                Tables\Columns\TextColumn::make('total_minor')
                    ->label(__('billing::billing.fields.total'))
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_minor')
                    ->label(__('billing::billing.fields.paid'))
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2)),

                Tables\Columns\TextColumn::make('deposits_applied_minor')
                    ->label(__('treatment_plans::treatment_plans.financials.deposits'))
                    ->formatStateUsing(fn ($state) => number_format(($state ?? 0) / 100, 2)),

                Tables\Columns\TextColumn::make('remaining_minor')
                    ->label(__('billing::billing.fields.balance'))
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('billing::billing.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => Invoice::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => Invoice::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('due_date')
                    ->label(__('billing::billing.fields.due_date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('issued_at')
                    ->label(__('billing::billing.fields.issued_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(Invoice::STATUSES),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label(__('filament-support::actions/view.single.label'))
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => route('filament.tenant.resources.invoices.view', [
                        'tenant' => current_tenant_id(),
                        'record' => $record->id,
                    ])),

                Tables\Actions\Action::make('apply_deposits')
                    ->label(__('treatment_plans::treatment_plans.financials.apply_deposits'))
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn ($record) => $record->remaining_minor > 0
                        && $this->ownerRecord->available_deposits_amount > 0)
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $deposits = $this->ownerRecord->unassignedPayments()->orderBy('paid_at')->get();
                        foreach ($deposits as $deposit) {
                            if ($record->remaining_minor <= 0) break;
                            $record->applyUnassignedPayment($deposit);
                        }
                        $this->ownerRecord->recalculateFinancials();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }
}
