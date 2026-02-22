<?php

namespace Modules\GiftCards\Filament\Resources\GiftCardResource\RelationManagers;

use Modules\GiftCards\Models\GiftCardTransaction;
use Filament\Forms;
use Filament\Forms\Form;
use App\Filament\Resources\RelationManagers\BaseRelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TransactionsRelationManager extends BaseRelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Transaction History';

    public function form(Form $form): Form
    {
        return $form
            ->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('giftcards::giftcards.fields.date'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('giftcards::giftcards.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => GiftCardTransaction::TYPES[$state] ?? $state)
                    ->color(fn ($state) => GiftCardTransaction::TYPE_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('amount_minor')
                    ->label(__('giftcards::giftcards.fields.amount'))
                    ->formatStateUsing(function ($state) {
                        $prefix = $state >= 0 ? '+' : '';
                        return $prefix . number_format($state / 100, 2);
                    })
                    ->suffix(' ' . current_currency())
                    ->color(fn (GiftCardTransaction $record) => $record->isCredit() ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('running_balance_minor')
                    ->label(__('giftcards::giftcards.fields.balance'))
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->suffix(' ' . current_currency()),

                Tables\Columns\TextColumn::make('notes')
                    ->label(__('giftcards::giftcards.fields.notes'))
                    ->wrap()
                    ->limit(50),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label(__('giftcards::giftcards.fields.created_by'))
                    ->placeholder('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('giftcards::giftcards.fields.type'))
                    ->options(GiftCardTransaction::TYPES),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }
}
