<?php

namespace Modules\Accounting\Filament\Resources\JournalEntryResource\RelationManagers;

use Modules\Accounting\Models\ChartOfAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Journal Lines';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('account_id')
                    ->label('Account')
                    ->options(ChartOfAccount::active()->postable()->get()->pluck('display_name', 'id'))
                    ->searchable()
                    ->required(),

                Forms\Components\TextInput::make('debit_minor')
                    ->label('Debit')
                    ->numeric()
                    ->default(0)
                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : 0)
                    ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0),

                Forms\Components\TextInput::make('credit_minor')
                    ->label('Credit')
                    ->numeric()
                    ->default(0)
                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : 0)
                    ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0),

                Forms\Components\TextInput::make('description')
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('account.display_name')
            ->columns([
                Tables\Columns\TextColumn::make('account.display_name')
                    ->label('Account')
                    ->searchable(),

                Tables\Columns\TextColumn::make('description')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('debit_minor')
                    ->label('Debit')
                    ->formatStateUsing(fn ($state) => $state > 0 ? number_format($state / 100, 2) : '-')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('credit_minor')
                    ->label('Credit')
                    ->formatStateUsing(fn ($state) => $state > 0 ? number_format($state / 100, 2) : '-')
                    ->alignEnd(),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => $this->ownerRecord->isDraft()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => $this->ownerRecord->isDraft()),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => $this->ownerRecord->isDraft()),
            ])
            ->bulkActions([]);
    }
}
