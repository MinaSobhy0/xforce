<?php

namespace Modules\Inventory\Filament\Resources\VendorBillResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Billing\Models\Payment;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    protected static ?string $icon = 'heroicon-o-banknotes';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('billing::billing.fields.code'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('journal.name')
                    ->label(__('billing::billing.fields.payment_method'))
                    ->icon(fn (Payment $record) => $record->method_icon),

                Tables\Columns\TextColumn::make('amount_minor')
                    ->label(__('billing::billing.fields.amount'))
                    ->money(current_currency())
                    ->formatStateUsing(fn ($state) => $state / 100)
                    ->sortable(),

                Tables\Columns\TextColumn::make('reference_number')
                    ->label(__('billing::billing.fields.reference'))
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label(__('billing::billing.fields.paid_at'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('receivedBy.name')
                    ->label(__('billing::billing.fields.recorded_by'))
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('billing::billing.fields.status'))
                    ->badge()
                    ->color(fn (Payment $record) => $record->status_color),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                //
            ])
            ->defaultSort('paid_at', 'desc');
    }
}
