<?php

namespace Modules\Packages\Filament\Resources\PackageSubscriptionResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Billing\Models\Payment;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('packages::packages.subscriptions.relations.payments');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('packages::packages.subscriptions.fields.payment_number'))
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => route('filament.tenant.resources.payments.view', ['record' => $record->id]))
                    ->color('primary'),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label(__('packages::packages.subscriptions.fields.paid_at'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount_minor')
                    ->label(__('packages::packages.subscriptions.fields.amount'))
                    ->formatStateUsing(fn ($state) => format_money($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('paymentMethod.name')
                    ->label(__('packages::packages.subscriptions.fields.payment_method'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('reference')
                    ->label(__('packages::packages.subscriptions.fields.reference'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('notes')
                    ->label(__('packages::packages.fields.notes'))
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('view_invoice')
                    ->label(__('packages::packages.subscriptions.actions.view_invoice'))
                    ->icon('heroicon-o-document-text')
                    ->url(fn () => $this->ownerRecord->invoice_id
                        ? route('filament.tenant.resources.invoices.view', ['record' => $this->ownerRecord->invoice_id])
                        : null)
                    ->visible(fn () => $this->ownerRecord->invoice_id !== null),
            ])
            ->bulkActions([])
            ->defaultSort('paid_at', 'desc');
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
