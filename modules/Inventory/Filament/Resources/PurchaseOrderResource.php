<?php

namespace Modules\Inventory\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Modules\Core\Models\Branch;
use Modules\Inventory\Models\PurchaseOrder;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Filament\Resources\PurchaseOrderResource\Pages;
use Modules\Inventory\Filament\Resources\PurchaseOrderResource\RelationManagers;
use XLinic\Framework\Core\Filament\RelationManagers\ActivityLogRelationManager;

class PurchaseOrderResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = PurchaseOrder::class;

    protected static ?string $moduleCode = 'inventory';

    protected static ?string $permissionKey = 'products';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 22;

    protected static ?string $recordTitleAttribute = 'order_number';

    public static function getNavigationLabel(): string
    {
        return __('inventory::inventory.navigation.purchase_orders');
    }

    public static function getModelLabel(): string
    {
        return __('inventory::inventory.labels.purchase_order');
    }

    public static function getPluralModelLabel(): string
    {
        return __('inventory::inventory.labels.purchase_orders');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('inventory::inventory.sections.order_info'))
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('order_number')
                                    ->label(__('inventory::inventory.fields.order_number'))
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->placeholder('Auto-generated'),

                                Forms\Components\Select::make('status')
                                    ->label(__('inventory::inventory.fields.status'))
                                    ->options(PurchaseOrder::STATUSES)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->default(PurchaseOrder::STATUS_DRAFT),

                                Forms\Components\DatePicker::make('order_date')
                                    ->label(__('inventory::inventory.fields.order_date'))
                                    ->required()
                                    ->default(now()),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('supplier_id')
                                    ->label(__('inventory::inventory.fields.supplier'))
                                    ->relationship('supplier', 'id')
                                    ->getOptionLabelFromRecordUsing(fn (Supplier $record) => $record->getTranslation('name', app()->getLocale()))
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name.en')
                                            ->label('Name (English)')
                                            ->required(),
                                        Forms\Components\TextInput::make('name.ar')
                                            ->label('Name (Arabic)')
                                            ->required(),
                                        Forms\Components\TextInput::make('phone')
                                            ->label('Phone'),
                                    ]),

                                Forms\Components\Select::make('branch_id')
                                    ->label(__('inventory::inventory.fields.branch'))
                                    ->relationship('branch', 'id')
                                    ->getOptionLabelFromRecordUsing(fn (Branch $record) => $record->name)
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->default(fn () => current_branch_id())
                                    ->disabled(fn () => current_branch_id() !== null)
                                    ->dehydrated(),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('expected_date')
                                    ->label(__('inventory::inventory.fields.expected_date')),

                                Forms\Components\DatePicker::make('received_date')
                                    ->label(__('inventory::inventory.fields.received_date'))
                                    ->disabled(),
                            ]),
                    ]),

                Forms\Components\Section::make(__('inventory::inventory.sections.items'))
                    ->schema([
                        Forms\Components\Repeater::make('lines')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label(__('inventory::inventory.fields.product'))
                                    ->relationship('product', 'id')
                                    ->getOptionLabelFromRecordUsing(fn (Product $record) => "[{$record->sku}] " . $record->getTranslation('name', app()->getLocale()))
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            if ($product) {
                                                $set('unit_price_minor', $product->cost_price_minor / 100);
                                                // Set default tax rates (multi-select)
                                                $defaultTax = \Modules\Billing\Models\TaxRate::getDefault(\Modules\Billing\Models\TaxRate::TYPE_PURCHASE);
                                                $set('tax_rates', $defaultTax ? [(string) $defaultTax->rate] : ['14']);
                                            }
                                        }
                                    })
                                    ->columnSpan(['default' => 12, 'md' => 4]),

                                Forms\Components\TextInput::make('quantity')
                                    ->label(__('inventory::inventory.fields.qty'))
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->minValue(1)
                                    ->live(onBlur: true)
                                    ->columnSpan(['default' => 4, 'md' => 1]),

                                Forms\Components\TextInput::make('unit_price_minor')
                                    ->label(__('inventory::inventory.fields.unit_price'))
                                    ->numeric()
                                    ->required()
                                    ->prefix(current_currency())
                                    ->live(onBlur: true)
                                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                                    ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0)
                                    ->columnSpan(['default' => 4, 'md' => 2]),

                                Forms\Components\Select::make('tax_rates')
                                    ->label(__('inventory::inventory.fields.taxes'))
                                    ->multiple()
                                    ->options(function () {
                                        return \Modules\Billing\Models\TaxRate::where('is_active', true)
                                            ->where('type', \Modules\Billing\Models\TaxRate::TYPE_PURCHASE)
                                            ->orderByDesc('rate')
                                            ->get()
                                            ->mapWithKeys(fn ($t) => [
                                                (string) $t->rate => $t->getTranslation('name', app()->getLocale()) . " ({$t->rate}%)"
                                            ]);
                                    })
                                    ->default(function () {
                                        $default = \Modules\Billing\Models\TaxRate::getDefault(\Modules\Billing\Models\TaxRate::TYPE_PURCHASE);
                                        return $default ? [(string) $default->rate] : ['14'];
                                    })
                                    ->live(onBlur: true)
                                    ->columnSpan(['default' => 4, 'md' => 2]),

                                Forms\Components\TextInput::make('quantity_received')
                                    ->label(__('inventory::inventory.fields.received'))
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->default(0)
                                    ->columnSpan(['default' => 4, 'md' => 1]),

                                Forms\Components\Textarea::make('notes')
                                    ->label(__('inventory::inventory.fields.notes'))
                                    ->rows(1)
                                    ->columnSpan(['default' => 12, 'md' => 2]),
                            ])
                            ->columns(12)
                            ->defaultItems(1)
                            ->addActionLabel(__('inventory::inventory.actions.add_item'))
                            ->reorderable(false)
                            ->live(),
                    ]),

                Forms\Components\Section::make(__('inventory::inventory.sections.totals'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                // Left column: Discount, Shipping inputs (tax is per-line now)
                                Forms\Components\Section::make()
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\Select::make('discount_type')
                                                    ->label(__('inventory::inventory.fields.discount_type'))
                                                    ->options([
                                                        'percentage' => __('inventory::inventory.discount_types.percentage'),
                                                        'amount' => __('inventory::inventory.discount_types.amount'),
                                                    ])
                                                    ->default('percentage')
                                                    ->live()
                                                    ->dehydrated(false),

                                                Forms\Components\TextInput::make('discount_value')
                                                    ->label(__('inventory::inventory.fields.discount'))
                                                    ->numeric()
                                                    ->default(0)
                                                    ->live(onBlur: true)
                                                    ->prefix(fn (Forms\Get $get) => $get('discount_type') === 'percentage' ? null : current_currency())
                                                    ->suffix(fn (Forms\Get $get) => $get('discount_type') === 'percentage' ? '%' : null)
                                                    ->dehydrated(false),
                                            ]),

                                        Forms\Components\TextInput::make('shipping_amount_minor')
                                            ->label(__('inventory::inventory.fields.shipping'))
                                            ->numeric()
                                            ->default(0)
                                            ->live(onBlur: true)
                                            ->prefix(current_currency())
                                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                                            ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0),
                                    ])
                                    ->columnSpan(1),

                                // Right column: Totals breakdown
                                Forms\Components\Section::make()
                                    ->schema([
                                        Forms\Components\Placeholder::make('subtotal_display')
                                            ->label(__('inventory::inventory.fields.subtotal'))
                                            ->content(function (Forms\Get $get) {
                                                $lines = $get('lines') ?? [];
                                                $subtotal = 0;
                                                foreach ($lines as $line) {
                                                    $qty = (float) ($line['quantity'] ?? 0);
                                                    $price = (float) ($line['unit_price_minor'] ?? 0);
                                                    $subtotal += $qty * $price;
                                                }

                                                // Apply discount to subtotal
                                                $discountType = $get('discount_type') ?? 'percentage';
                                                $discountValue = (float) ($get('discount_value') ?? 0);
                                                if ($discountType === 'percentage') {
                                                    $discountAmount = $subtotal * ($discountValue / 100);
                                                } else {
                                                    $discountAmount = $discountValue;
                                                }
                                                $discountedSubtotal = $subtotal - $discountAmount;

                                                return number_format($discountedSubtotal, 2) . ' ' . current_currency();
                                            }),

                                        // VAT (positive tax rates - Odoo logic: discount first, then tax)
                                        Forms\Components\Placeholder::make('vat_display')
                                            ->label(__('inventory::inventory.fields.vat'))
                                            ->content(function (Forms\Get $get) {
                                                $lines = $get('lines') ?? [];
                                                $subtotal = 0;
                                                $vatOnSubtotal = 0;

                                                // Calculate subtotal and VAT per line
                                                foreach ($lines as $line) {
                                                    $qty = (float) ($line['quantity'] ?? 0);
                                                    $price = (float) ($line['unit_price_minor'] ?? 0);
                                                    $lineSubtotal = $qty * $price;
                                                    $subtotal += $lineSubtotal;

                                                    $taxRates = $line['tax_rates'] ?? [];
                                                    $positiveRates = array_filter(array_map('floatval', $taxRates), fn($r) => $r > 0);
                                                    $vatPercent = array_sum($positiveRates);
                                                    $vatOnSubtotal += $lineSubtotal * ($vatPercent / 100);
                                                }

                                                // Apply discount ratio to VAT (Odoo-like: discount reduces taxable base)
                                                $discountType = $get('discount_type') ?? 'percentage';
                                                $discountValue = (float) ($get('discount_value') ?? 0);
                                                $discountRatio = 1;
                                                if ($subtotal > 0) {
                                                    if ($discountType === 'percentage') {
                                                        $discountRatio = 1 - ($discountValue / 100);
                                                    } else {
                                                        $discountRatio = ($subtotal - $discountValue) / $subtotal;
                                                    }
                                                }

                                                $vatAmount = $vatOnSubtotal * max(0, $discountRatio);
                                                return '+ ' . number_format($vatAmount, 2) . ' ' . current_currency();
                                            }),

                                        // Withholding (negative tax rates - Odoo logic: discount first, then tax)
                                        Forms\Components\Placeholder::make('whm_display')
                                            ->label(__('inventory::inventory.fields.whm'))
                                            ->content(function (Forms\Get $get) {
                                                $lines = $get('lines') ?? [];
                                                $subtotal = 0;
                                                $whmOnSubtotal = 0;

                                                // Calculate subtotal and WH per line
                                                foreach ($lines as $line) {
                                                    $qty = (float) ($line['quantity'] ?? 0);
                                                    $price = (float) ($line['unit_price_minor'] ?? 0);
                                                    $lineSubtotal = $qty * $price;
                                                    $subtotal += $lineSubtotal;

                                                    $taxRates = $line['tax_rates'] ?? [];
                                                    $negativeRates = array_filter(array_map('floatval', $taxRates), fn($r) => $r < 0);
                                                    $whmPercent = abs(array_sum($negativeRates));
                                                    $whmOnSubtotal += $lineSubtotal * ($whmPercent / 100);
                                                }

                                                // Apply discount ratio to WH
                                                $discountType = $get('discount_type') ?? 'percentage';
                                                $discountValue = (float) ($get('discount_value') ?? 0);
                                                $discountRatio = 1;
                                                if ($subtotal > 0) {
                                                    if ($discountType === 'percentage') {
                                                        $discountRatio = 1 - ($discountValue / 100);
                                                    } else {
                                                        $discountRatio = ($subtotal - $discountValue) / $subtotal;
                                                    }
                                                }

                                                $whmAmount = $whmOnSubtotal * max(0, $discountRatio);
                                                return '- ' . number_format($whmAmount, 2) . ' ' . current_currency();
                                            })
                                            ->visible(function (Forms\Get $get) {
                                                $lines = $get('lines') ?? [];
                                                foreach ($lines as $line) {
                                                    $taxRates = $line['tax_rates'] ?? [];
                                                    foreach ($taxRates as $rate) {
                                                        if ((float) $rate < 0) {
                                                            return true;
                                                        }
                                                    }
                                                }
                                                return false;
                                            }),

                                        Forms\Components\Placeholder::make('discount_display')
                                            ->label(__('inventory::inventory.fields.discount'))
                                            ->content(function (Forms\Get $get) {
                                                $lines = $get('lines') ?? [];
                                                $subtotal = 0;
                                                foreach ($lines as $line) {
                                                    $qty = (float) ($line['quantity'] ?? 0);
                                                    $price = (float) ($line['unit_price_minor'] ?? 0);
                                                    $subtotal += $qty * $price;
                                                }
                                                $discountType = $get('discount_type') ?? 'percentage';
                                                $discountValue = (float) ($get('discount_value') ?? 0);
                                                if ($discountType === 'percentage') {
                                                    $discountAmount = $subtotal * ($discountValue / 100);
                                                } else {
                                                    $discountAmount = $discountValue;
                                                }
                                                return '- ' . number_format($discountAmount, 2) . ' ' . current_currency();
                                            }),

                                        Forms\Components\Placeholder::make('shipping_display')
                                            ->label(__('inventory::inventory.fields.shipping'))
                                            ->content(function (Forms\Get $get) {
                                                $shipping = (float) ($get('shipping_amount_minor') ?? 0);
                                                return '+ ' . number_format($shipping, 2) . ' ' . current_currency();
                                            }),

                                        Forms\Components\Placeholder::make('total_display')
                                            ->label(__('inventory::inventory.fields.total'))
                                            ->content(function (Forms\Get $get) {
                                                $lines = $get('lines') ?? [];
                                                $subtotal = 0;
                                                $taxOnSubtotal = 0;

                                                // Calculate subtotal and taxes per line
                                                foreach ($lines as $line) {
                                                    $qty = (float) ($line['quantity'] ?? 0);
                                                    $price = (float) ($line['unit_price_minor'] ?? 0);
                                                    $lineSubtotal = $qty * $price;
                                                    $subtotal += $lineSubtotal;

                                                    $taxRates = $line['tax_rates'] ?? [];
                                                    $totalTaxPercent = array_sum(array_map('floatval', $taxRates));
                                                    $taxOnSubtotal += $lineSubtotal * ($totalTaxPercent / 100);
                                                }

                                                // Calculate discount
                                                $discountType = $get('discount_type') ?? 'percentage';
                                                $discountValue = (float) ($get('discount_value') ?? 0);
                                                if ($discountType === 'percentage') {
                                                    $discountAmount = $subtotal * ($discountValue / 100);
                                                } else {
                                                    $discountAmount = $discountValue;
                                                }

                                                // Odoo logic: discount reduces taxable base, so tax is proportionally reduced
                                                $discountRatio = $subtotal > 0 ? ($subtotal - $discountAmount) / $subtotal : 1;
                                                $taxAmount = $taxOnSubtotal * max(0, $discountRatio);

                                                $shipping = (float) ($get('shipping_amount_minor') ?? 0);

                                                // Total = (Subtotal - Discount) + Tax + Shipping
                                                $total = ($subtotal - $discountAmount) + $taxAmount + $shipping;
                                                return new \Illuminate\Support\HtmlString(
                                                    '<span class="text-xl font-bold text-primary-600 dark:text-primary-400">' .
                                                    number_format($total, 2) . ' ' . current_currency() .
                                                    '</span>'
                                                );
                                            }),
                                    ])
                                    ->columnSpan(1),
                            ]),

                        Forms\Components\Hidden::make('subtotal_minor')
                            ->dehydrateStateUsing(function (Forms\Get $get) {
                                $lines = $get('lines') ?? [];
                                $subtotal = 0;
                                foreach ($lines as $line) {
                                    $qty = (float) ($line['quantity'] ?? 0);
                                    $price = (float) ($line['unit_price_minor'] ?? 0) * 100;
                                    $subtotal += $qty * $price;
                                }
                                return (int) $subtotal;
                            }),
                        Forms\Components\Hidden::make('tax_amount_minor')
                            ->dehydrateStateUsing(function (Forms\Get $get) {
                                $lines = $get('lines') ?? [];
                                $subtotal = 0;
                                $taxOnSubtotal = 0;

                                foreach ($lines as $line) {
                                    $qty = (float) ($line['quantity'] ?? 0);
                                    $price = (float) ($line['unit_price_minor'] ?? 0) * 100;
                                    $lineSubtotal = $qty * $price;
                                    $subtotal += $lineSubtotal;

                                    $taxRates = $line['tax_rates'] ?? [];
                                    $totalTaxPercent = array_sum(array_map('floatval', $taxRates));
                                    $taxOnSubtotal += $lineSubtotal * ($totalTaxPercent / 100);
                                }

                                // Odoo logic: apply discount ratio to tax
                                $discountType = $get('discount_type') ?? 'percentage';
                                $discountValue = (float) ($get('discount_value') ?? 0);
                                if ($discountType === 'percentage') {
                                    $discountAmount = $subtotal * ($discountValue / 100);
                                } else {
                                    $discountAmount = $discountValue * 100;
                                }
                                $discountRatio = $subtotal > 0 ? ($subtotal - $discountAmount) / $subtotal : 1;

                                return (int) ($taxOnSubtotal * max(0, $discountRatio));
                            }),
                        Forms\Components\Hidden::make('discount_amount_minor')
                            ->dehydrateStateUsing(function (Forms\Get $get) {
                                $lines = $get('lines') ?? [];
                                $subtotal = 0;
                                foreach ($lines as $line) {
                                    $qty = (float) ($line['quantity'] ?? 0);
                                    $price = (float) ($line['unit_price_minor'] ?? 0) * 100;
                                    $subtotal += $qty * $price;
                                }
                                $discountType = $get('discount_type') ?? 'percentage';
                                $discountValue = (float) ($get('discount_value') ?? 0);
                                if ($discountType === 'percentage') {
                                    return (int) ($subtotal * ($discountValue / 100));
                                }
                                return (int) ($discountValue * 100);
                            }),
                        Forms\Components\Hidden::make('total_amount_minor')
                            ->dehydrateStateUsing(function (Forms\Get $get) {
                                $lines = $get('lines') ?? [];
                                $subtotal = 0;
                                $taxOnSubtotal = 0;

                                foreach ($lines as $line) {
                                    $qty = (float) ($line['quantity'] ?? 0);
                                    $price = (float) ($line['unit_price_minor'] ?? 0) * 100;
                                    $lineSubtotal = $qty * $price;
                                    $subtotal += $lineSubtotal;

                                    $taxRates = $line['tax_rates'] ?? [];
                                    $totalTaxPercent = array_sum(array_map('floatval', $taxRates));
                                    $taxOnSubtotal += $lineSubtotal * ($totalTaxPercent / 100);
                                }

                                // Discount
                                $discountType = $get('discount_type') ?? 'percentage';
                                $discountValue = (float) ($get('discount_value') ?? 0);
                                if ($discountType === 'percentage') {
                                    $discountAmount = (int) ($subtotal * ($discountValue / 100));
                                } else {
                                    $discountAmount = (int) ($discountValue * 100);
                                }

                                // Odoo logic: tax is calculated on discounted subtotal
                                $discountRatio = $subtotal > 0 ? ($subtotal - $discountAmount) / $subtotal : 1;
                                $taxAmount = (int) ($taxOnSubtotal * max(0, $discountRatio));

                                // Shipping
                                $shipping = (int) (((float) ($get('shipping_amount_minor') ?? 0)) * 100);

                                // Total = (Subtotal - Discount) + Tax + Shipping
                                return (int) (($subtotal - $discountAmount) + $taxAmount + $shipping);
                            }),
                    ]),

                Forms\Components\Section::make(__('inventory::inventory.sections.notes'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Textarea::make('notes')
                                    ->label(__('inventory::inventory.fields.notes'))
                                    ->helperText('Visible to supplier')
                                    ->rows(3),

                                Forms\Components\Textarea::make('internal_notes')
                                    ->label(__('inventory::inventory.fields.internal_notes'))
                                    ->helperText('Internal use only')
                                    ->rows(3),
                            ]),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label(__('inventory::inventory.fields.order_number'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label(__('inventory::inventory.fields.supplier'))
                    ->getStateUsing(fn (PurchaseOrder $record) => $record->supplier?->getTranslation('name', app()->getLocale()))
                    ->searchable(['name']),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('inventory::inventory.fields.branch'))
                    ->getStateUsing(fn (PurchaseOrder $record) => $record->branch?->name),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('inventory::inventory.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => PurchaseOrder::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => PurchaseOrder::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('order_date')
                    ->label(__('inventory::inventory.fields.order_date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('expected_date')
                    ->label(__('inventory::inventory.fields.expected_date'))
                    ->date()
                    ->sortable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label(__('inventory::inventory.fields.total'))
                    ->money(current_currency())
                    ->sortable(),

                Tables\Columns\TextColumn::make('lines_count')
                    ->label(__('inventory::inventory.fields.items'))
                    ->counts('lines'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('inventory::inventory.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('inventory::inventory.fields.status'))
                    ->options(PurchaseOrder::STATUSES),

                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label(__('inventory::inventory.fields.supplier'))
                    ->relationship('supplier', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Supplier $record) => $record->getTranslation('name', app()->getLocale())),

                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('inventory::inventory.fields.branch'))
                    ->relationship('branch', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Branch $record) => $record->name),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (PurchaseOrder $record) => $record->isEditable()),

                Tables\Actions\Action::make('send')
                    ->label(__('inventory::inventory.actions.send'))
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn (PurchaseOrder $record) => $record->canTransitionTo(PurchaseOrder::STATUS_SENT))
                    ->requiresConfirmation()
                    ->action(function (PurchaseOrder $record) {
                        if ($record->send(auth()->id())) {
                            Notification::make()
                                ->title(__('inventory::inventory.messages.order_sent'))
                                ->success()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label(__('inventory::inventory.actions.cancel'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (PurchaseOrder $record) => $record->canTransitionTo(PurchaseOrder::STATUS_CANCELLED))
                    ->requiresConfirmation()
                    ->action(function (PurchaseOrder $record) {
                        if ($record->cancel()) {
                            Notification::make()
                                ->title(__('inventory::inventory.messages.order_cancelled'))
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => false), // Disable bulk delete for POs
                ]),
            ])
            ->defaultSort('order_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LinesRelationManager::class,
            ActivityLogRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'view' => Pages\ViewPurchaseOrder::route('/{record}'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
            'receive' => Pages\ReceivePurchaseOrder::route('/{record}/receive'),
        ];
    }
}
