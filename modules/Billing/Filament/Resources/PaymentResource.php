<?php

namespace Modules\Billing\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Billing\Filament\Resources\PaymentResource\Pages;
use Modules\Billing\Models\Payment;
use Modules\Accounting\Models\Journal;
use Filament\Forms;
use Filament\Forms\Form;
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

    // Payments are read-only list - creation happens through invoices
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->disabled(),

                Forms\Components\Select::make('invoice_id')
                    ->relationship('invoice', 'code')
                    ->disabled(),

                Forms\Components\TextInput::make('amount_minor')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 100, 2) : 0)
                    ->suffix(current_currency())
                    ->disabled(),

                Forms\Components\Select::make('journal_id')
                    ->label('Payment Method')
                    ->relationship('journal', 'code')
                    ->getOptionLabelFromRecordUsing(fn (Journal $record) => $record->display_name)
                    ->disabled(),

                Forms\Components\TextInput::make('reference_number')
                    ->disabled(),

                Forms\Components\DateTimePicker::make('paid_at')
                    ->disabled(),

                Forms\Components\Textarea::make('notes')
                    ->disabled()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Payment #')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === Payment::TYPE_RECEIVE ? 'Receive' : 'Send')
                    ->color(fn (Payment $record) => $record->type_color)
                    ->icon(fn (Payment $record) => $record->isReceive() ? 'heroicon-o-arrow-down-tray' : 'heroicon-o-arrow-up-tray'),

                Tables\Columns\TextColumn::make('document')
                    ->label('Document')
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
                    ->label('Patient/Supplier')
                    ->getStateUsing(fn (Payment $record) => $record->patient?->full_name ?? $record->supplier?->getTranslation('name', app()->getLocale()) ?? '-'),

                Tables\Columns\TextColumn::make('amount_minor')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => format_money($state))
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->color(fn (Payment $record) => $record->isReceive() ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('journal.name')
                    ->label('Payment Method')
                    ->badge()
                    ->color(fn (Payment $record) => $record->journal?->type_color ?? 'gray'),

                Tables\Columns\TextColumn::make('reference_number')
                    ->label('Reference')
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('receivedBy.name')
                    ->label('Recorded By')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Date/Time')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        Payment::TYPE_RECEIVE => 'Receive (Money In)',
                        Payment::TYPE_SEND => 'Send (Money Out)',
                    ]),

                Tables\Filters\SelectFilter::make('journal_id')
                    ->label('Payment Method')
                    ->relationship('journal', 'code')
                    ->getOptionLabelFromRecordUsing(fn (Journal $record) => $record->display_name)
                    ->multiple(),

                Tables\Filters\Filter::make('paid_at')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
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
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['invoice.patient', 'vendorBill.supplier', 'supplier', 'patient', 'journal', 'receivedBy']);
    }
}
