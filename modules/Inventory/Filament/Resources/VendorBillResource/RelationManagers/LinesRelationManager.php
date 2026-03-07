<?php

namespace Modules\Inventory\Filament\Resources\VendorBillResource\RelationManagers;

use Modules\Billing\Models\TaxRate;
use Modules\Inventory\Models\VendorBillLine;
use Modules\Inventory\Models\Product;
use Modules\Accounting\Models\ChartOfAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('inventory::inventory.sections.line_items');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->label(__('inventory::inventory.fields.product'))
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
                                $defaultTax = TaxRate::getDefault(TaxRate::TYPE_PURCHASE);
                                $set('tax_rates', $defaultTax ? [(string) $defaultTax->rate] : []);
                                // Set account based on product type:
                                // - Storable products: use stock valuation account (inventory asset)
                                // - Consumable products: use expense account
                                if ($product->tracksInventory()) {
                                    $accountId = $product->stock_valuation_account_id;
                                } else {
                                    $accountId = $product->expense_account_id;
                                }
                                if ($accountId) {
                                    $set('account_id', $accountId);
                                }
                            }
                        }
                    }),

                Forms\Components\TextInput::make('description')
                    ->label(__('inventory::inventory.fields.description'))
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('account_id')
                    ->label(__('inventory::inventory.fields.account'))
                    ->options(
                        ChartOfAccount::where('is_active', true)
                            ->orderBy('code')
                            ->get()
                            ->mapWithKeys(fn ($a) => [$a->id => "[{$a->code}] " . $a->getTranslation('name', app()->getLocale())])
                    )
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\TextInput::make('quantity')
                    ->label(__('inventory::inventory.fields.qty'))
                    ->numeric()
                    ->default(1)
                    ->minValue(0.01)
                    ->required(),

                Forms\Components\TextInput::make('unit_price_minor')
                    ->label(__('inventory::inventory.fields.unit_price'))
                    ->numeric()
                    ->required()
                    ->prefix(current_currency())
                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                    ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0),

                Forms\Components\Select::make('discount_type')
                    ->label(__('inventory::inventory.fields.disc_type'))
                    ->options([
                        'fixed' => current_currency(),
                        'percent' => '%',
                    ])
                    ->default('fixed'),

                Forms\Components\TextInput::make('discount_minor')
                    ->label(__('inventory::inventory.fields.discount'))
                    ->numeric()
                    ->default(0)
                    ->formatStateUsing(function ($state, Forms\Get $get) {
                        if ($get('discount_type') === 'percent') {
                            return $state ?: 0;
                        }
                        return $state ? $state / 100 : 0;
                    })
                    ->dehydrateStateUsing(function ($state, Forms\Get $get) {
                        if ($get('discount_type') === 'percent') {
                            return $state ? (int) $state : 0;
                        }
                        return $state ? (int) ($state * 100) : 0;
                    }),

                Forms\Components\Select::make('tax_rates')
                    ->label(__('inventory::inventory.fields.taxes'))
                    ->multiple()
                    ->options(function () {
                        return TaxRate::where('is_active', true)
                            ->where('type', TaxRate::TYPE_PURCHASE)
                            ->orderByDesc('rate')
                            ->get()
                            ->mapWithKeys(fn ($t) => [
                                (string) $t->rate => $t->getTranslation('name', app()->getLocale()) . " ({$t->rate}%)"
                            ]);
                    })
                    ->default(function () {
                        $default = TaxRate::getDefault(TaxRate::TYPE_PURCHASE);
                        return $default ? [(string) $default->rate] : [];
                    }),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('product.sku')
                    ->label(__('inventory::inventory.fields.sku'))
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('description')
                    ->label(__('inventory::inventory.fields.description'))
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('account.code')
                    ->label(__('inventory::inventory.fields.account'))
                    ->formatStateUsing(fn ($state, $record) => $record->account
                        ? "[{$record->account->code}] " . $record->account->getTranslation('name', app()->getLocale())
                        : '-')
                    ->wrap(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label(__('inventory::inventory.fields.qty'))
                    ->numeric(2),

                Tables\Columns\TextColumn::make('unit_price_minor')
                    ->label(__('inventory::inventory.fields.unit_price'))
                    ->formatStateUsing(fn ($state) => format_money($state)),

                Tables\Columns\TextColumn::make('discount_minor')
                    ->label(__('inventory::inventory.fields.discount'))
                    ->formatStateUsing(fn ($state, $record) => $state > 0
                        ? ($record->discount_type === 'percent'
                            ? $state . '%'
                            : format_money($state))
                        : '-'),

                Tables\Columns\TextColumn::make('tax_rates')
                    ->label(__('inventory::inventory.fields.taxes'))
                    ->formatStateUsing(function ($state, $record) {
                        // Use record's accessor to get properly casted array
                        $rates = $record->tax_rates ?? [];

                        // Fallback to state parsing if record accessor fails
                        if (empty($rates)) {
                            if (empty($state)) {
                                return '-';
                            }
                            $rates = is_array($state) ? $state : json_decode($state, true);
                        }

                        if (empty($rates) || !is_array($rates)) {
                            return '-';
                        }

                        $vatRates = array_filter($rates, fn ($r) => floatval($r) >= 0);
                        $whRates = array_filter($rates, fn ($r) => floatval($r) < 0);

                        $parts = [];
                        if (!empty($vatRates)) {
                            $parts[] = 'VAT: ' . implode(', ', array_map(fn ($r) => $r . '%', $vatRates));
                        }
                        if (!empty($whRates)) {
                            $parts[] = 'WH: ' . implode(', ', array_map(fn ($r) => $r . '%', $whRates));
                        }
                        return empty($parts) ? '-' : implode(' | ', $parts);
                    }),

                Tables\Columns\TextColumn::make('total_minor')
                    ->label(__('inventory::inventory.fields.total'))
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
