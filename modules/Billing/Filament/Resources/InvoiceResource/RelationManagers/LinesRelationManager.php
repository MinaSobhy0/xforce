<?php

namespace Modules\Billing\Filament\Resources\InvoiceResource\RelationManagers;

use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\TaxRate;
use Modules\Treatments\Models\Treatment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Line Items';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('treatment_id')
                    ->label('Treatment')
                    ->options(Treatment::query()->where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($state) {
                            $treatment = Treatment::find($state);
                            if ($treatment) {
                                $set('description', $treatment->name);
                                $set('unit_price_minor', $treatment->base_price_minor / 100);
                                $set('tax_rate', TaxRate::getDefault()?->rate ?? 14);
                            }
                        }
                    }),

                Forms\Components\TextInput::make('description')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('quantity')
                    ->numeric()
                    ->default(1)
                    ->minValue(0.01)
                    ->required(),

                Forms\Components\TextInput::make('unit_price_minor')
                    ->label('Unit Price')
                    ->numeric()
                    ->required()
                    ->prefix(config('app.currency_symbol', 'EGP'))
                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                    ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0),

                Forms\Components\TextInput::make('discount_minor')
                    ->label('Discount')
                    ->numeric()
                    ->default(0)
                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : 0)
                    ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0),

                Forms\Components\Select::make('discount_type')
                    ->options([
                        'fixed' => 'Fixed',
                        'percent' => 'Percent',
                    ])
                    ->default('fixed'),

                Forms\Components\TextInput::make('tax_rate')
                    ->label('Tax %')
                    ->numeric()
                    ->default(fn () => TaxRate::getDefault()?->rate ?? 14)
                    ->suffix('%'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('quantity')
                    ->numeric(2),

                Tables\Columns\TextColumn::make('unit_price_minor')
                    ->label('Unit Price')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->suffix(' ' . config('app.currency_symbol', 'EGP')),

                Tables\Columns\TextColumn::make('discount_minor')
                    ->label('Discount')
                    ->formatStateUsing(fn ($state, $record) => $state > 0
                        ? ($record->discount_type === 'percent'
                            ? $state . '%'
                            : number_format($state / 100, 2))
                        : '-'),

                Tables\Columns\TextColumn::make('tax_rate')
                    ->label('Tax')
                    ->suffix('%'),

                Tables\Columns\TextColumn::make('total_minor')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->suffix(' ' . config('app.currency_symbol', 'EGP'))
                    ->weight('bold'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => $this->ownerRecord->isEditable()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => $this->ownerRecord->isEditable()),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => $this->ownerRecord->isEditable()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => $this->ownerRecord->isEditable()),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }
}
