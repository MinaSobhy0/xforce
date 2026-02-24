<?php

namespace Modules\Billing\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Billing\Filament\Resources\PaymentResource\Pages;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\Invoice;
use Modules\Accounting\Models\Journal;
use Modules\Patients\Models\Patient;
use Modules\Inventory\Models\VendorBill;
use Modules\Inventory\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;

class PaymentResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = Payment::class;

    protected static ?string $moduleCode = 'billing';

    protected static ?string $permissionKey = 'payments';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getNavigationLabel(): string
    {
        return __('billing::billing.payments');
    }

    public static function getModelLabel(): string
    {
        return __('billing::billing.payment');
    }

    public static function getPluralModelLabel(): string
    {
        return __('billing::billing.payments');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('billing::billing.sections.payment_details'))
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('code')
                                    ->label(__('billing::billing.fields.code'))
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->placeholder(__('billing::billing.placeholders.auto_generated')),

                                Forms\Components\Select::make('type')
                                    ->label(__('billing::billing.fields.type'))
                                    ->options(Payment::TYPES)
                                    ->default(Payment::TYPE_RECEIVE)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set) {
                                        $set('invoice_id', null);
                                        $set('vendor_bill_id', null);
                                        $set('patient_id', null);
                                        $set('supplier_id', null);
                                        $set('amount_minor', null);
                                    })
                                    ->disabled(fn ($record) => $record !== null),

                                Forms\Components\Select::make('status')
                                    ->label(__('billing::billing.fields.status'))
                                    ->options(Payment::STATUSES)
                                    ->default(Payment::STATUS_COMPLETED)
                                    ->required()
                                    ->disabled(fn ($record) => $record !== null),
                            ]),

                        // Invoice selection (for receive type)
                        Forms\Components\Select::make('invoice_id')
                            ->label(__('billing::billing.fields.invoice'))
                            ->options(function () {
                                return Invoice::whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIALLY_PAID])
                                    ->orderBy('created_at', 'desc')
                                    ->limit(100)
                                    ->get()
                                    ->filter(fn ($inv) => $inv->remaining_minor > 0)
                                    ->mapWithKeys(fn ($inv) => [
                                        $inv->id => "{$inv->code} - {$inv->patient?->full_name} (" . format_money($inv->remaining_minor) . " remaining)"
                                    ]);
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                $invoice = Invoice::find($value);
                                if (!$invoice) return null;
                                return "{$invoice->code} - {$invoice->patient?->full_name}";
                            })
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get) => $get('type') === Payment::TYPE_RECEIVE)
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state) {
                                    $invoice = Invoice::find($state);
                                    if ($invoice) {
                                        $set('patient_id', $invoice->patient_id);
                                        $set('branch_id', $invoice->branch_id);
                                        $set('amount_minor', $invoice->remaining_minor / 100);
                                    }
                                }
                            })
                            ->disabled(fn ($record) => $record !== null)
                            ->columnSpan(2),

                        // Vendor Bill selection (for send type)
                        Forms\Components\Select::make('vendor_bill_id')
                            ->label(__('billing::billing.fields.vendor_bill'))
                            ->options(function () {
                                return VendorBill::whereIn('status', [VendorBill::STATUS_VALIDATED, VendorBill::STATUS_PARTIALLY_PAID])
                                    ->orderBy('created_at', 'desc')
                                    ->limit(100)
                                    ->get()
                                    ->filter(fn ($bill) => $bill->remaining_minor > 0)
                                    ->mapWithKeys(fn ($bill) => [
                                        $bill->id => "{$bill->code} - {$bill->supplier?->getTranslation('name', app()->getLocale())} (" . format_money($bill->remaining_minor) . " remaining)"
                                    ]);
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                $bill = VendorBill::find($value);
                                if (!$bill) return null;
                                return "{$bill->code} - {$bill->supplier?->getTranslation('name', app()->getLocale())}";
                            })
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get) => $get('type') === Payment::TYPE_SEND)
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state) {
                                    $bill = VendorBill::find($state);
                                    if ($bill) {
                                        $set('supplier_id', $bill->supplier_id);
                                        $set('branch_id', $bill->branch_id);
                                        $set('amount_minor', $bill->remaining_minor / 100);
                                    }
                                }
                            })
                            ->disabled(fn ($record) => $record !== null)
                            ->columnSpan(2),

                        // Patient (auto-filled for receive, hidden)
                        Forms\Components\Hidden::make('patient_id'),
                        Forms\Components\Hidden::make('supplier_id'),
                        Forms\Components\Hidden::make('branch_id'),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('amount_minor')
                                    ->label(__('billing::billing.fields.amount'))
                                    ->numeric()
                                    ->required()
                                    ->prefix(current_currency())
                                    ->disabled(fn ($record) => $record !== null),

                                Forms\Components\Select::make('journal_id')
                                    ->label(__('billing::billing.fields.payment_method'))
                                    ->options(fn () => Journal::active()
                                        ->whereIn('type', ['cash', 'bank'])
                                        ->get()
                                        ->pluck('display_name', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->default(fn () => Journal::getCashJournal()?->id)
                                    ->disabled(fn ($record) => $record !== null),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DateTimePicker::make('paid_at')
                                    ->label(__('billing::billing.fields.paid_at'))
                                    ->required()
                                    ->default(now())
                                    ->disabled(fn ($record) => $record !== null),

                                Forms\Components\TextInput::make('reference_number')
                                    ->label(__('billing::billing.fields.reference'))
                                    ->maxLength(255)
                                    ->disabled(fn ($record) => $record !== null),
                            ]),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('billing::billing.fields.notes'))
                            ->rows(2)
                            ->columnSpanFull()
                            ->disabled(fn ($record) => $record !== null),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('billing::billing.fields.code'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('billing::billing.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === Payment::TYPE_RECEIVE
                        ? __('billing::billing.payment_resource.receive')
                        : __('billing::billing.payment_resource.send'))
                    ->color(fn (Payment $record) => $record->type_color)
                    ->icon(fn (Payment $record) => $record->isReceive() ? 'heroicon-o-arrow-down-tray' : 'heroicon-o-arrow-up-tray'),

                Tables\Columns\TextColumn::make('document')
                    ->label(__('billing::billing.payment_resource.document'))
                    ->getStateUsing(fn (Payment $record) => $record->invoice?->code ?? $record->vendorBill?->code ?? '-')
                    ->url(fn (Payment $record) => $record->invoice_id
                        ? InvoiceResource::getUrl('view', ['record' => $record->invoice_id])
                        : ($record->vendor_bill_id
                            ? \Modules\Inventory\Filament\Resources\VendorBillResource::getUrl('view', ['record' => $record->vendor_bill_id])
                            : null))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->whereHas('invoice', fn ($q) => $q->where('code', 'like', "%{$search}%"))
                              ->orWhereHas('vendorBill', fn ($q) => $q->where('code', 'like', "%{$search}%"));
                        });
                    }),

                Tables\Columns\TextColumn::make('party')
                    ->label(__('billing::billing.payment_resource.patient_supplier'))
                    ->getStateUsing(fn (Payment $record) => $record->patient?->full_name ?? $record->supplier?->getTranslation('name', app()->getLocale()) ?? '-'),

                Tables\Columns\TextColumn::make('amount_minor')
                    ->label(__('billing::billing.fields.amount'))
                    ->formatStateUsing(fn ($state) => format_money($state))
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->color(fn (Payment $record) => $record->isReceive() ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('journal.name')
                    ->label(__('billing::billing.fields.payment_method'))
                    ->badge()
                    ->color(fn (Payment $record) => $record->journal?->type_color ?? 'gray'),

                Tables\Columns\TextColumn::make('reference_number')
                    ->label(__('billing::billing.fields.reference'))
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('receivedBy.name')
                    ->label(__('billing::billing.payment_resource.recorded_by'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label(__('billing::billing.relation.date_time'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('billing::billing.fields.type'))
                    ->options([
                        Payment::TYPE_RECEIVE => __('billing::billing.payment_resource.receive_money_in'),
                        Payment::TYPE_SEND => __('billing::billing.payment_resource.send_money_out'),
                    ]),

                Tables\Filters\SelectFilter::make('journal_id')
                    ->label(__('billing::billing.fields.payment_method'))
                    ->relationship('journal', 'code')
                    ->getOptionLabelFromRecordUsing(fn (Journal $record) => $record->display_name)
                    ->multiple(),

                Tables\Filters\Filter::make('paid_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label(__('billing::billing.filters.from')),
                        Forms\Components\DatePicker::make('until')
                            ->label(__('billing::billing.filters.until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('paid_at', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('paid_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('paid_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['invoice.patient', 'vendorBill.supplier', 'supplier', 'patient', 'journal', 'receivedBy']);
    }
}
