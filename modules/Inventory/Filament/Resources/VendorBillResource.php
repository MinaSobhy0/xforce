<?php

namespace Modules\Inventory\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\FontWeight;
use Filament\Notifications\Notification;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Billing\Models\TaxRate;
use Modules\Core\Models\Branch;
use Modules\Inventory\Models\VendorBill;
use Modules\Inventory\Models\VendorBillLine;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Filament\Resources\VendorBillResource\Pages;

class VendorBillResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = VendorBill::class;

    protected static ?string $moduleCode = 'inventory';

    protected static ?string $permissionKey = 'vendor-bills';

    protected static ?string $navigationIcon = 'heroicon-o-document-minus';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('inventory::inventory.navigation.vendor_bills') ?? 'Vendor Bills';
    }

    public static function getModelLabel(): string
    {
        return __('inventory::inventory.labels.vendor_bill') ?? 'Vendor Bill';
    }

    public static function getPluralModelLabel(): string
    {
        return __('inventory::inventory.labels.vendor_bills') ?? 'Vendor Bills';
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', VendorBill::STATUS_DRAFT)->count() ?: null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Bill Details')
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('code')
                                            ->label('Bill #')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->placeholder('Auto-generated'),

                                        Forms\Components\Select::make('status')
                                            ->options(VendorBill::STATUSES)
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->default(VendorBill::STATUS_DRAFT),

                                        Forms\Components\DatePicker::make('bill_date')
                                            ->label('Bill Date')
                                            ->required()
                                            ->default(now()),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('supplier_id')
                                            ->label('Supplier')
                                            ->relationship('supplier', 'id')
                                            ->getOptionLabelFromRecordUsing(fn (Supplier $record) => $record->getTranslation('name', app()->getLocale()))
                                            ->required()
                                            ->searchable()
                                            ->preload(),

                                        Forms\Components\Select::make('branch_id')
                                            ->label('Branch')
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
                                        Forms\Components\TextInput::make('vendor_reference')
                                            ->label('Vendor Reference')
                                            ->maxLength(255),

                                        Forms\Components\DatePicker::make('due_date')
                                            ->label('Due Date'),
                                    ]),
                            ]),

                        Forms\Components\Section::make('Line Items')
                            ->schema([
                                Forms\Components\Repeater::make('lines')
                                    ->relationship()
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
                                                        $defaultTax = TaxRate::getDefault();
                                                        $set('tax_rate', $defaultTax ? (string) $defaultTax->rate : '14');
                                                    }
                                                }
                                            })
                                            ->columnSpan(['default' => 12, 'md' => 4]),

                                        Forms\Components\TextInput::make('description')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(['default' => 12, 'md' => 4]),

                                        Forms\Components\Select::make('account_id')
                                            ->label('Account')
                                            ->options(
                                                ChartOfAccount::where('type', ChartOfAccount::TYPE_EXPENSE)
                                                    ->where('is_active', true)
                                                    ->orderBy('code')
                                                    ->get()
                                                    ->mapWithKeys(fn ($a) => [$a->id => "[{$a->code}] " . $a->getTranslation('name', app()->getLocale())])
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->columnSpan(['default' => 12, 'md' => 4]),

                                        Forms\Components\TextInput::make('quantity')
                                            ->label('Qty')
                                            ->numeric()
                                            ->default(1)
                                            ->minValue(0.01)
                                            ->step(0.01)
                                            ->required()
                                            ->columnSpan(['default' => 4, 'md' => 2]),

                                        Forms\Components\TextInput::make('unit_price_minor')
                                            ->label('Unit Price')
                                            ->numeric()
                                            ->required()
                                            ->live(onBlur: true)
                                            ->prefix(current_currency())
                                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                                            ->dehydrateStateUsing(fn ($state) => $state ? (int) ((float) $state * 100) : 0)
                                            ->columnSpan(['default' => 8, 'md' => 3]),

                                        Forms\Components\Select::make('discount_type')
                                            ->label('Type')
                                            ->options([
                                                'fixed' => current_currency(),
                                                'percent' => '%',
                                            ])
                                            ->default('fixed')
                                            ->live()
                                            ->columnSpan(['default' => 4, 'md' => 2]),

                                        Forms\Components\TextInput::make('discount_minor')
                                            ->label('Discount')
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
                                            })
                                            ->columnSpan(['default' => 4, 'md' => 2]),

                                        Forms\Components\Select::make('tax_rate')
                                            ->label('Tax')
                                            ->options(function () {
                                                return TaxRate::where('is_active', true)
                                                    ->orderBy('rate')
                                                    ->get()
                                                    ->mapWithKeys(fn ($t) => [
                                                        (string) $t->rate => $t->getTranslation('name', app()->getLocale()) . " ({$t->rate}%)"
                                                    ]);
                                            })
                                            ->default(function () {
                                                $default = TaxRate::getDefault();
                                                return $default ? (string) $default->rate : '14';
                                            })
                                            ->columnSpan(['default' => 4, 'md' => 3]),
                                    ])
                                    ->columns(12)
                                    ->defaultItems(1)
                                    ->addActionLabel('Add Line Item')
                                    ->reorderable()
                                    ->reorderableWithButtons()
                                    ->cloneable()
                                    ->live(onBlur: true)
                                    ->itemLabel(fn (array $state): ?string => $state['description'] ?? null),
                            ]),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Summary')
                            ->schema([
                                Forms\Components\Placeholder::make('subtotal_display')
                                    ->label('Subtotal')
                                    ->content(function (Forms\Get $get) {
                                        $lines = $get('lines') ?? [];
                                        $subtotal = 0;
                                        foreach ($lines as $line) {
                                            $qty = (float) ($line['quantity'] ?? 0);
                                            $price = (float) ($line['unit_price_minor'] ?? 0) * 100;
                                            $subtotal += $qty * $price;
                                        }
                                        return format_money((int) $subtotal);
                                    }),

                                Forms\Components\Placeholder::make('discount_display')
                                    ->label('Discount')
                                    ->content(function (Forms\Get $get) {
                                        $lines = $get('lines') ?? [];
                                        $totalDiscount = 0;
                                        foreach ($lines as $line) {
                                            $qty = (float) ($line['quantity'] ?? 0);
                                            $price = (float) ($line['unit_price_minor'] ?? 0) * 100;
                                            $lineSubtotal = $qty * $price;

                                            $discountType = $line['discount_type'] ?? 'fixed';
                                            $discountValue = (float) ($line['discount_minor'] ?? 0);
                                            if ($discountType === 'percent') {
                                                $totalDiscount += $lineSubtotal * $discountValue / 100;
                                            } else {
                                                $totalDiscount += $discountValue * 100;
                                            }
                                        }
                                        return $totalDiscount > 0 ? '-' . format_money((int) $totalDiscount) : '-';
                                    })
                                    ->visible(function (Forms\Get $get) {
                                        $lines = $get('lines') ?? [];
                                        foreach ($lines as $line) {
                                            if (($line['discount_minor'] ?? 0) > 0) {
                                                return true;
                                            }
                                        }
                                        return false;
                                    }),

                                Forms\Components\Placeholder::make('tax_display')
                                    ->label('Tax')
                                    ->content(function (Forms\Get $get) {
                                        $lines = $get('lines') ?? [];
                                        $tax = 0;
                                        foreach ($lines as $line) {
                                            $qty = (float) ($line['quantity'] ?? 0);
                                            $price = (float) ($line['unit_price_minor'] ?? 0) * 100;
                                            $lineSubtotal = $qty * $price;

                                            // Apply line discount before tax
                                            $discountType = $line['discount_type'] ?? 'fixed';
                                            $discountValue = (float) ($line['discount_minor'] ?? 0);
                                            if ($discountType === 'percent') {
                                                $lineSubtotal -= $lineSubtotal * $discountValue / 100;
                                            } else {
                                                $lineSubtotal -= $discountValue * 100;
                                            }

                                            $taxRate = (float) ($line['tax_rate'] ?? 0);
                                            $tax += $lineSubtotal * $taxRate / 100;
                                        }
                                        return format_money((int) $tax);
                                    }),

                                Forms\Components\Placeholder::make('total_display')
                                    ->label('Total')
                                    ->content(function (Forms\Get $get) {
                                        $lines = $get('lines') ?? [];
                                        $total = 0;
                                        foreach ($lines as $line) {
                                            $qty = (float) ($line['quantity'] ?? 0);
                                            $price = (float) ($line['unit_price_minor'] ?? 0) * 100;
                                            $lineSubtotal = $qty * $price;

                                            // Apply line discount
                                            $discountType = $line['discount_type'] ?? 'fixed';
                                            $discountValue = (float) ($line['discount_minor'] ?? 0);
                                            if ($discountType === 'percent') {
                                                $lineSubtotal -= $lineSubtotal * $discountValue / 100;
                                            } else {
                                                $lineSubtotal -= $discountValue * 100;
                                            }

                                            // Add tax
                                            $taxRate = (float) ($line['tax_rate'] ?? 0);
                                            $lineSubtotal += $lineSubtotal * $taxRate / 100;

                                            $total += $lineSubtotal;
                                        }
                                        return format_money((int) $total);
                                    })
                                    ->extraAttributes(['class' => 'text-lg font-bold']),
                            ]),

                        Forms\Components\Section::make('Notes')
                            ->schema([
                                Forms\Components\Textarea::make('notes')
                                    ->label('Notes')
                                    ->rows(2),

                                Forms\Components\Textarea::make('internal_notes')
                                    ->label('Internal Notes')
                                    ->rows(2),
                            ])
                            ->collapsed(),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Bill #')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('vendor_reference')
                    ->label('Vendor Ref')
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_minor')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => format_money($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_minor')
                    ->label('Paid')
                    ->formatStateUsing(fn ($state) => format_money($state))
                    ->color(fn (VendorBill $record) => $record->isPaid() ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('remaining_minor')
                    ->label('Remaining')
                    ->formatStateUsing(fn ($state) => format_money($state))
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => VendorBill::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => VendorBill::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('bill_date')
                    ->label('Bill Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(VendorBill::STATUSES)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn (VendorBill $record) => $record->isDraft()),

                    Tables\Actions\Action::make('validate')
                        ->label('Validate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription('This will validate the bill and create journal entries. This action cannot be undone.')
                        ->visible(fn (VendorBill $record) => $record->canValidate())
                        ->action(function (VendorBill $record) {
                            if ($record->validate()) {
                                Notification::make()
                                    ->title('Bill validated successfully')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Failed to validate bill')
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Tables\Actions\Action::make('record_payment')
                        ->label('Record Payment')
                        ->icon('heroicon-o-banknotes')
                        ->color('info')
                        ->visible(fn (VendorBill $record) => $record->canRecordPayment())
                        ->form([
                            Forms\Components\TextInput::make('amount')
                                ->label('Amount')
                                ->numeric()
                                ->required()
                                ->prefix(current_currency())
                                ->default(fn (VendorBill $record) => $record->remaining_minor / 100),

                            Forms\Components\Select::make('payment_type')
                                ->label('Payment Method')
                                ->options([
                                    'cash' => 'Cash',
                                    'bank' => 'Bank Transfer',
                                ])
                                ->default('cash')
                                ->required(),
                        ])
                        ->action(function (VendorBill $record, array $data) {
                            $amountMinor = (int) ($data['amount'] * 100);
                            $record->recordPayment($amountMinor);

                            // Create payment journal entry
                            $accountingService = app(\Modules\Inventory\Services\InventoryAccountingService::class);
                            $accountingService->createVendorPaymentJournalEntry($record, $amountMinor, $data['payment_type']);

                            Notification::make()
                                ->title('Payment recorded successfully')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('cancel')
                        ->label('Cancel')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (VendorBill $record) => $record->canCancel())
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Cancellation Reason')
                                ->required(),
                        ])
                        ->action(fn (VendorBill $record, array $data) => $record->cancel($data['reason'])),
                ]),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make()
                    ->schema([
                        Infolists\Components\TextEntry::make('code')
                            ->label('Bill #')
                            ->weight(FontWeight::Bold)
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => VendorBill::STATUSES[$state] ?? $state)
                            ->color(fn (string $state): string => VendorBill::STATUS_COLORS[$state] ?? 'gray'),

                        Infolists\Components\TextEntry::make('vendor_reference')
                            ->label('Vendor Ref')
                            ->placeholder('-'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Supplier')
                    ->schema([
                        Infolists\Components\TextEntry::make('supplier.name')
                            ->label('Supplier'),

                        Infolists\Components\TextEntry::make('branch.name')
                            ->label('Branch'),

                        Infolists\Components\TextEntry::make('purchaseOrder.order_number')
                            ->label('Purchase Order')
                            ->placeholder('None'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Amounts')
                    ->schema([
                        Infolists\Components\TextEntry::make('subtotal_minor')
                            ->label('Subtotal')
                            ->formatStateUsing(fn ($state) => format_money($state)),

                        Infolists\Components\TextEntry::make('tax_minor')
                            ->label('Tax')
                            ->formatStateUsing(fn ($state) => format_money($state)),

                        Infolists\Components\TextEntry::make('total_minor')
                            ->label('Total')
                            ->formatStateUsing(fn ($state) => format_money($state))
                            ->weight(FontWeight::Bold),

                        Infolists\Components\TextEntry::make('paid_minor')
                            ->label('Paid')
                            ->formatStateUsing(fn ($state) => format_money($state))
                            ->color('success'),

                        Infolists\Components\TextEntry::make('remaining_minor')
                            ->label('Remaining')
                            ->formatStateUsing(fn ($state) => format_money($state))
                            ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                    ])
                    ->columns(5),

                Infolists\Components\Section::make('Dates')
                    ->schema([
                        Infolists\Components\TextEntry::make('bill_date')
                            ->label('Bill Date')
                            ->date(),

                        Infolists\Components\TextEntry::make('due_date')
                            ->label('Due Date')
                            ->date()
                            ->placeholder('No due date'),

                        Infolists\Components\TextEntry::make('validated_at')
                            ->label('Validated')
                            ->dateTime()
                            ->placeholder('Not validated'),

                        Infolists\Components\TextEntry::make('paid_at')
                            ->label('Paid')
                            ->dateTime()
                            ->placeholder('Not paid'),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make('Journal Entry')
                    ->schema([
                        Infolists\Components\TextEntry::make('journalEntry.code')
                            ->label('Journal Entry')
                            ->placeholder('Not created')
                            ->url(fn (VendorBill $record) => $record->journal_entry_id
                                ? route('filament.tenant.resources.journal-entries.view', $record->journal_entry_id)
                                : null),
                    ])
                    ->visible(fn (VendorBill $record) => $record->isValidated()),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            VendorBillResource\RelationManagers\LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorBills::route('/'),
            'create' => Pages\CreateVendorBill::route('/create'),
            'view' => Pages\ViewVendorBill::route('/{record}'),
            'edit' => Pages\EditVendorBill::route('/{record}/edit'),
        ];
    }
}
