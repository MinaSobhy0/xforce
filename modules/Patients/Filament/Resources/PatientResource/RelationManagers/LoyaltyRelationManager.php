<?php

namespace Modules\Patients\Filament\Resources\PatientResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Loyalty\Models\LoyaltyTransaction;

class LoyaltyRelationManager extends RelationManager
{
    protected static string $relationship = 'loyaltyTransactions';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('loyalty::loyalty.fields.date'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('loyalty::loyalty.fields.type'))
                    ->badge()
                    ->color(fn ($state) => LoyaltyTransaction::getTypeColor($state))
                    ->icon(fn ($state) => LoyaltyTransaction::getTypeIcon($state))
                    ->formatStateUsing(fn ($state) => LoyaltyTransaction::getTypeLabel($state)),

                Tables\Columns\TextColumn::make('points')
                    ->label(__('loyalty::loyalty.fields.points'))
                    ->alignCenter()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => $state > 0 ? "+{$state}" : $state),

                Tables\Columns\TextColumn::make('balance_after')
                    ->label(__('loyalty::loyalty.fields.balance'))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('description')
                    ->label(__('loyalty::loyalty.fields.description'))
                    ->limit(40),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(LoyaltyTransaction::getTypeOptions()),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('loyalty::loyalty.loyalty_points');
    }
}
