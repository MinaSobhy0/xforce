<?php

namespace Modules\Inventory\Filament\Resources\VendorBillResource\RelationManagers;

use Modules\Inventory\Models\VendorBillLine;
use Modules\Inventory\Models\Product;
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
                Forms\Components\Select::make('product_id')
                    ->label('Product')
                    ->options(Product::query()->where('is_active', true)->get()->mapWithKeys(fn ($p) => [
                        $p->id => "[{$p->sku}] " . $p->getTranslation('name', app()->getLocale())
                    ]))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($state) {
                            $product = Product::find($state);
                            if ($product) {
                                $set('description', $product->getTranslation('name', app()->getLocale()));
                                $set('unit_price_minor', $product->cost_price_minor / 100);
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
                    ->prefix(current_currency())
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
                    ->default(0)
                    ->suffix('%'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('product.sku')
                    ->label('SKU')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('quantity')
                    ->numeric(2),

                Tables\Columns\TextColumn::make('unit_price_minor')
                    ->label('Unit Price')
                    ->formatStateUsing(fn ($state) => format_money($state)),

                Tables\Columns\TextColumn::make('discount_minor')
                    ->label('Discount')
                    ->formatStateUsing(fn ($state, $record) => $state > 0
                        ? ($record->discount_type === 'percent'
                            ? $state . '%'
                            : format_money($state))
                        : '-'),

                Tables\Columns\TextColumn::make('tax_rate')
                    ->label('Tax')
                    ->suffix('%'),

                Tables\Columns\TextColumn::make('total_minor')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => format_money($state))
                    ->weight('bold'),
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
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => $this->ownerRecord->isDraft()),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }
}
