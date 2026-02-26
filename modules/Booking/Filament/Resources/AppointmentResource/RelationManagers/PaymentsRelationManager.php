<?php

namespace Modules\Booking\Filament\Resources\AppointmentResource\RelationManagers;

use Modules\Billing\Models\Payment;
use Modules\Accounting\Models\Journal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('billing::billing.payments');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('billing::billing.fields.code'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount_minor')
                    ->label(__('billing::billing.fields.amount'))
                    ->formatStateUsing(fn ($state) => format_money($state))
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('journal.name')
                    ->label(__('billing::billing.fields.payment_method'))
                    ->badge()
                    ->color(fn (Payment $record) => $record->journal?->type_color ?? 'gray'),

                Tables\Columns\TextColumn::make('reference_number')
                    ->label(__('billing::billing.fields.reference'))
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('receivedBy.name')
                    ->label(__('billing::billing.relation.received_by')),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label(__('billing::billing.relation.date_time'))
                    ->dateTime(),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('paid_at', 'desc');
    }
}
