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

    protected static ?string $recordTitleAttribute = 'code';

    public static function getNavigationLabel(): string
    {
        return __('inventory::inventory.navigation.vendor_bills');
    }

    public static function getModelLabel(): string
    {
        return __('inventory::inventory.labels.vendor_bill');
    }

    public static function getPluralModelLabel(): string
    {
        return __('inventory::inventory.labels.vendor_bills');
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
                        Forms\Components\Section::make(__('inventory::inventory.sections.bill_details'))
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('code')
                                            ->label(__('inventory::inventory.fields.bill_number'))
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->placeholder('Auto-generated'),

                                        Forms\Components\Select::make('status')
                                            ->label(__('inventory::inventory.fields.status'))
                                            ->options(VendorBill::STATUSES)
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->default(VendorBill::STATUS_DRAFT),

                                        Forms\Components\DatePicker::make('bill_date')
                                            ->label(__('inventory::inventory.fields.bill_date'))
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
                                            ->preload(),

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
                                        Forms\Components\TextInput::make('vendor_reference')
                                            ->label(__('inventory::inventory.fields.vendor_reference'))
                                            ->maxLength(255),

                                        Forms\Components\DatePicker::make('due_date')
                                            ->label(__('inventory::inventory.fields.due_date')),
                                    ]),
                            ]),

                        Forms\Components\Section::make(__('inventory::inventory.sections.line_items'))
                            ->schema([
                                Forms\Components\Repeater::make('lines')
                                    ->relationship()
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
                                                        $defaultTax = TaxRate::getDefault();
                                                        $set('tax_rate', $defaultTax ? (string) $defaultTax->rate : '14');
                                                        // Set expense account from product or fallback to first expense account
                                                        if ($product->expense_account_id) {
                                                            $set('account_id', $product->expense_account_id);
                                                        }
                                                    }
                                                }
                                            })
                                            ->columnSpan(['default' => 12, 'md' => 4]),

                                        Forms\Components\TextInput::make('description')
                                            ->label(__('inventory::inventory.fields.description'))
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(['default' => 12, 'md' => 4]),

                                        Forms\Components\Select::make('account_id')
                                            ->label(__('inventory::inventory.fields.account'))
                                            ->options(
                                                ChartOfAccount::where('type', ChartOfAccount::TYPE_EXPENSE)
                                                    ->where('is_active', true)
                                                    ->orderBy('code')
                                                    ->get()
                                                    ->mapWithKeys(fn ($a) => [$a->id => "[{$a->code}] " . $a->getTranslation('name', app()->getLocale())])
                                            )
                                            ->default(fn () => ChartOfAccount::where('type', ChartOfAccount::TYPE_EXPENSE)->where('is_active', true)->orderBy('code')->first()?->id)
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->columnSpan(['default' => 12, 'md' => 4]),

                                        Forms\Components\TextInput::make('quantity')
                                            ->label(__('inventory::inventory.fields.qty'))
                                            ->numeric()
                                            ->default(1)
                                            ->minValue(0.01)
                                            ->step(0.01)
                                            ->required()
                                            ->columnSpan(['default' => 4, 'md' => 2]),

                                        Forms\Components\TextInput::make('unit_price_minor')
                                            ->label(__('inventory::inventory.fields.unit_price'))
                                            ->numeric()
                                            ->required()
                                            ->live(onBlur: true)
                                            ->prefix(current_currency())
                                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                                            ->dehydrateStateUsing(fn ($state) => $state ? (int) ((float) $state * 100) : 0)
                                            ->columnSpan(['default' => 8, 'md' => 3]),

                                        Forms\Components\Select::make('discount_type')
                                            ->label(__('inventory::inventory.fields.disc_type'))
                                            ->options([
                                                'fixed' => current_currency(),
                                                'percent' => '%',
                                            ])
                                            ->default('fixed')
                                            ->live()
                                            ->columnSpan(['default' => 4, 'md' => 2]),

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
                                            })
                                            ->columnSpan(['default' => 4, 'md' => 2]),

                                        Forms\Components\Select::make('tax_rate')
                                            ->label(__('inventory::inventory.fields.tax'))
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
                                    ->addActionLabel(__('inventory::inventory.actions.add_line_item'))
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
                        Forms\Components\Section::make(__('inventory::inventory.sections.summary'))
                            ->schema([
                                Forms\Components\Placeholder::make('subtotal_display')
                                    ->label(__('inventory::inventory.fields.subtotal'))
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
                                    ->label(__('inventory::inventory.fields.discount'))
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
                                    ->label(__('inventory::inventory.fields.tax'))
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
                                    ->label(__('inventory::inventory.fields.total'))
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

                        Forms\Components\Section::make(__('inventory::inventory.sections.notes'))
                            ->schema([
                                Forms\Components\Textarea::make('notes')
                                    ->label(__('inventory::inventory.fields.notes'))
                                    ->rows(2),

                                Forms\Components\Textarea::make('internal_notes')
                                    ->label(__('inventory::inventory.fields.internal_notes'))
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
                    ->label(__('inventory::inventory.fields.bill_number'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label(__('inventory::inventory.fields.supplier'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('vendor_reference')
                    ->label(__('inventory::inventory.fields.vendor_ref'))
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_minor')
                    ->label(__('inventory::inventory.fields.total'))
                    ->formatStateUsing(fn ($state) => format_money($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_minor')
                    ->label(__('inventory::inventory.fields.paid'))
                    ->formatStateUsing(fn ($state) => format_money($state))
                    ->color(fn (VendorBill $record) => $record->isPaid() ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('remaining_minor')
                    ->label(__('inventory::inventory.fields.remaining'))
                    ->formatStateUsing(fn ($state) => format_money($state))
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('inventory::inventory.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => VendorBill::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => VendorBill::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('bill_date')
                    ->label(__('inventory::inventory.fields.bill_date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->label(__('inventory::inventory.fields.due_date'))
                    ->date()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('inventory::inventory.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('inventory::inventory.fields.status'))
                    ->options(VendorBill::STATUSES)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label(__('inventory::inventory.fields.supplier'))
                    ->relationship('supplier', 'name'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn (VendorBill $record) => $record->isDraft()),

                    Tables\Actions\Action::make('validate')
                        ->label(__('inventory::inventory.actions.validate'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription(__('inventory::inventory.messages.validate_bill_confirmation'))
                        ->visible(fn (VendorBill $record) => $record->canValidate())
                        ->action(function (VendorBill $record) {
                            if ($record->validate()) {
                                Notification::make()
                                    ->title(__('inventory::inventory.messages.bill_validated'))
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title(__('inventory::inventory.messages.bill_validation_failed'))
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Tables\Actions\Action::make('record_payment')
                        ->label(__('inventory::inventory.actions.record_payment'))
                        ->icon('heroicon-o-banknotes')
                        ->color('info')
                        ->visible(fn (VendorBill $record) => $record->canRecordPayment())
                        ->form([
                            Forms\Components\TextInput::make('amount')
                                ->label(__('inventory::inventory.fields.payment_amount'))
                                ->numeric()
                                ->required()
                                ->prefix(current_currency())
                                ->default(fn (VendorBill $record) => $record->remaining_minor / 100),

                            Forms\Components\Select::make('payment_type')
                                ->label(__('inventory::inventory.fields.payment_method'))
                                ->options([
                                    'cash' => __('billing::billing.payment_methods.cash'),
                                    'bank' => __('billing::billing.payment_methods.bank_transfer'),
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
                                ->title(__('inventory::inventory.messages.payment_recorded'))
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('cancel')
                        ->label(__('inventory::inventory.actions.cancel'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (VendorBill $record) => $record->canCancel())
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label(__('inventory::inventory.messages.cancellation_reason'))
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
                            ->label(__('inventory::inventory.fields.bill_number'))
                            ->weight(FontWeight::Bold)
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                        Infolists\Components\TextEntry::make('status')
                            ->label(__('inventory::inventory.fields.status'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => VendorBill::STATUSES[$state] ?? $state)
                            ->color(fn (string $state): string => VendorBill::STATUS_COLORS[$state] ?? 'gray'),

                        Infolists\Components\TextEntry::make('vendor_reference')
                            ->label(__('inventory::inventory.fields.vendor_ref'))
                            ->placeholder('-'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make(__('inventory::inventory.fields.supplier'))
                    ->schema([
                        Infolists\Components\TextEntry::make('supplier.name')
                            ->label(__('inventory::inventory.fields.supplier')),

                        Infolists\Components\TextEntry::make('branch.name')
                            ->label(__('inventory::inventory.fields.branch')),

                        Infolists\Components\TextEntry::make('purchaseOrder.order_number')
                            ->label(__('inventory::inventory.labels.purchase_order'))
                            ->placeholder('-'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make(__('inventory::inventory.sections.amounts'))
                    ->schema([
                        Infolists\Components\TextEntry::make('subtotal_minor')
                            ->label(__('inventory::inventory.fields.subtotal'))
                            ->formatStateUsing(fn ($state) => format_money($state)),

                        Infolists\Components\TextEntry::make('tax_minor')
                            ->label(__('inventory::inventory.fields.tax'))
                            ->formatStateUsing(fn ($state) => format_money($state)),

                        Infolists\Components\TextEntry::make('total_minor')
                            ->label(__('inventory::inventory.fields.total'))
                            ->formatStateUsing(fn ($state) => format_money($state))
                            ->weight(FontWeight::Bold),

                        Infolists\Components\TextEntry::make('paid_minor')
                            ->label(__('inventory::inventory.fields.paid'))
                            ->formatStateUsing(fn ($state) => format_money($state))
                            ->color('success'),

                        Infolists\Components\TextEntry::make('remaining_minor')
                            ->label(__('inventory::inventory.fields.remaining'))
                            ->formatStateUsing(fn ($state) => format_money($state))
                            ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                    ])
                    ->columns(5),

                Infolists\Components\Section::make(__('inventory::inventory.sections.dates'))
                    ->schema([
                        Infolists\Components\TextEntry::make('bill_date')
                            ->label(__('inventory::inventory.fields.bill_date'))
                            ->date(),

                        Infolists\Components\TextEntry::make('due_date')
                            ->label(__('inventory::inventory.fields.due_date'))
                            ->date()
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('validated_at')
                            ->label(__('inventory::inventory.fields.validated'))
                            ->dateTime()
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('paid_at')
                            ->label(__('inventory::inventory.fields.paid'))
                            ->dateTime()
                            ->placeholder('-'),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make(__('inventory::inventory.fields.journal_entry'))
                    ->schema([
                        Infolists\Components\TextEntry::make('journalEntry.code')
                            ->label(__('inventory::inventory.fields.journal_entry'))
                            ->placeholder(__('inventory::inventory.messages.not_created'))
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
            'record-payment' => Pages\RecordPayment::route('/{record}/record-payment'),
        ];
    }
}
