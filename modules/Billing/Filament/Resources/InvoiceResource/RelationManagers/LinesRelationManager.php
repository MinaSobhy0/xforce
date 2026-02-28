<?php

namespace Modules\Billing\Filament\Resources\InvoiceResource\RelationManagers;

use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\TaxRate;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Services\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('billing::billing.sections.line_items');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('line_type')
                    ->label(__('billing::billing.fields.line_type'))
                    ->options([
                        InvoiceLine::LINE_TYPE_SERVICE => __('billing::billing.line_types.service'),
                        InvoiceLine::LINE_TYPE_PRODUCT => __('billing::billing.line_types.product'),
                        InvoiceLine::LINE_TYPE_PACKAGE => __('billing::billing.line_types.package'),
                        InvoiceLine::LINE_TYPE_OTHER => __('billing::billing.line_types.other'),
                    ])
                    ->default(InvoiceLine::LINE_TYPE_SERVICE)
                    ->required()
                    ->live(),

                Forms\Components\Select::make('service_id')
                    ->label(__('billing::billing.fields.service'))
                    ->options(Service::query()->where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->visible(fn (Forms\Get $get) => $get('line_type') === InvoiceLine::LINE_TYPE_SERVICE)
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($state) {
                            $service = Service::find($state);
                            if ($service) {
                                $set('description', $service->name);
                                $set('unit_price_minor', $service->base_price_minor / 100);
                                $defaultTax = TaxRate::getDefault(TaxRate::TYPE_SALES);
                                $set('tax_rates', $defaultTax ? [(string) $defaultTax->rate] : ['14']);
                            }
                        }
                    }),

                Forms\Components\Select::make('account_id')
                    ->label(__('billing::billing.fields.account'))
                    ->options(
                        ChartOfAccount::where('type', ChartOfAccount::TYPE_INCOME)
                            ->where('is_active', true)
                            ->orderBy('code')
                            ->get()
                            ->mapWithKeys(fn ($a) => [$a->id => "[{$a->code}] " . $a->getTranslation('name', app()->getLocale())])
                    )
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\TextInput::make('description')
                    ->label(__('billing::billing.fields.description'))
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('quantity')
                    ->label(__('billing::billing.fields.quantity'))
                    ->numeric()
                    ->default(1)
                    ->minValue(0.01)
                    ->required(),

                Forms\Components\TextInput::make('unit_price_minor')
                    ->label(__('billing::billing.fields.unit_price'))
                    ->numeric()
                    ->required()
                    ->prefix(current_currency())
                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                    ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0),

                Forms\Components\Select::make('discount_type')
                    ->label(__('billing::billing.fields.discount_type'))
                    ->options([
                        'fixed' => __('billing::billing.discount_types.fixed'),
                        'percent' => __('billing::billing.discount_types.percent'),
                    ])
                    ->default('fixed')
                    ->live(),

                Forms\Components\TextInput::make('discount_minor')
                    ->label(__('billing::billing.fields.discount'))
                    ->numeric()
                    ->default(0)
                    ->formatStateUsing(fn ($state, $record) =>
                        $record && $record->discount_type === 'percent'
                            ? $state
                            : ($state ? $state / 100 : 0)
                    )
                    ->dehydrateStateUsing(fn ($state, Forms\Get $get) =>
                        $get('discount_type') === 'percent'
                            ? (int) $state
                            : (int) (($state ?? 0) * 100)
                    ),

                Forms\Components\Select::make('tax_rates')
                    ->label(__('billing::billing.fields.taxes'))
                    ->multiple()
                    ->options(function () {
                        return TaxRate::where('is_active', true)
                            ->where('type', TaxRate::TYPE_SALES)
                            ->orderByDesc('rate')
                            ->get()
                            ->mapWithKeys(fn ($t) => [
                                (string) $t->rate => $t->getTranslation('name', app()->getLocale()) . " ({$t->rate}%)"
                            ]);
                    })
                    ->default(function () {
                        $default = TaxRate::getDefault(TaxRate::TYPE_SALES);
                        return $default ? [(string) $default->rate] : ['14'];
                    }),
            ]);
    }

    public function table(Table $table): Table
    {
        $isEditable = $this->ownerRecord->isEditable();

        return $table
            ->recordTitleAttribute('description')
            ->paginated(false)
            ->columns([
                // Service Code - Read only
                Tables\Columns\TextColumn::make('service.code')
                    ->label('')
                    ->default('-')
                    ->color('gray'),

                // Description - Inline editable
                Tables\Columns\TextInputColumn::make('description')
                    ->label(__('billing::billing.fields.description'))
                    ->rules(['required', 'max:255'])
                    ->disabled(! $isEditable)
                    ->searchable(),

                // Quantity - Inline editable
                Tables\Columns\TextInputColumn::make('quantity')
                    ->label(__('billing::billing.fields.quantity'))
                    ->type('number')
                    ->rules(['required', 'numeric', 'min:0.01'])
                    ->disabled(! $isEditable)
                    ->afterStateUpdated(fn ($record) => $this->recalculate($record)),

                // Unit Price - Inline editable with conversion
                Tables\Columns\TextInputColumn::make('unit_price_value')
                    ->label(__('billing::billing.fields.unit_price'))
                    ->type('number')
                    ->disabled(! $isEditable)
                    ->state(fn ($record) => number_format($record->unit_price_minor / 100, 2, '.', ''))
                    ->updateStateUsing(function ($record, $state) {
                        $record->update(['unit_price_minor' => (int)(floatval($state) * 100)]);
                        $this->recalculate($record);
                        return $state;
                    }),

                // Discount - Inline editable with conversion
                Tables\Columns\TextInputColumn::make('discount_value')
                    ->label(__('billing::billing.fields.discount'))
                    ->type('number')
                    ->disabled(! $isEditable)
                    ->state(fn ($record) => $record->discount_type === 'percent'
                        ? number_format($record->discount_minor, 2, '.', '')
                        : number_format($record->discount_minor / 100, 2, '.', ''))
                    ->updateStateUsing(function ($record, $state) {
                        $value = $record->discount_type === 'percent'
                            ? (int) floatval($state)
                            : (int)(floatval($state) * 100);
                        $record->update(['discount_minor' => $value]);
                        $this->recalculate($record);
                        return $state;
                    }),

                // Taxes - Display only (multiple values)
                Tables\Columns\TextColumn::make('tax_rates')
                    ->label(__('billing::billing.fields.taxes'))
                    ->formatStateUsing(function ($state, $record) {
                        $rates = $record->tax_rates ?? [];
                        if (empty($rates)) {
                            return '-';
                        }
                        $vatRates = array_filter($rates, fn ($r) => floatval($r) >= 0);
                        $whRates = array_filter($rates, fn ($r) => floatval($r) < 0);
                        $parts = [];
                        if (!empty($vatRates)) {
                            $parts[] = 'VAT: ' . implode(', ', array_map(fn ($r) => number_format((float)$r, 2) . '%', $vatRates));
                        }
                        if (!empty($whRates)) {
                            $parts[] = 'WH: ' . implode(', ', array_map(fn ($r) => number_format((float)$r, 2) . '%', $whRates));
                        }
                        return empty($parts) ? '-' : implode(' | ', $parts);
                    }),

                // Total - Read only, calculated
                Tables\Columns\TextColumn::make('total_minor')
                    ->label(__('billing::billing.fields.total'))
                    ->formatStateUsing(fn ($state) => format_money($state))
                    ->weight('bold'),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('billing::billing.actions.add_line_item'))
                    ->visible($isEditable)
                    ->mutateFormDataUsing(function (array $data): array {
                        if (!isset($data['account_id'])) {
                            $data['account_id'] = ChartOfAccount::where('type', ChartOfAccount::TYPE_INCOME)
                                ->where('is_active', true)
                                ->first()?->id;
                        }
                        return $data;
                    })
                    ->after(fn () => $this->ownerRecord->recalculateTotals()),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->visible($isEditable)
                    ->after(fn () => $this->ownerRecord->recalculateTotals()),
            ])
            ->bulkActions([])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }

    protected function recalculate($record): void
    {
        $record->refresh();
        $record->calculateTotal();
        $record->save();
        $this->ownerRecord->recalculateTotals();
    }
}
