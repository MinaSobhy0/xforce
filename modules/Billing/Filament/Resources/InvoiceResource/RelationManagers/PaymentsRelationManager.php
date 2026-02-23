<?php

namespace Modules\Billing\Filament\Resources\InvoiceResource\RelationManagers;

use Modules\Billing\Models\Payment;
use Modules\Billing\Models\Invoice;
use Modules\Accounting\Models\Journal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('amount_minor')
                    ->label('Amount')
                    ->numeric()
                    ->required()
                    ->prefix(current_currency())
                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                    ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0),

                Forms\Components\Select::make('journal_id')
                    ->label('Payment Method')
                    ->options(fn () => Journal::active()
                        ->whereIn('type', ['cash', 'bank'])
                        ->get()
                        ->pluck('display_name', 'id'))
                    ->required()
                    ->searchable()
                    ->default(fn () => Journal::getCashJournal()?->id),

                Forms\Components\DateTimePicker::make('paid_at')
                    ->label('Payment Date/Time')
                    ->required()
                    ->default(now()),

                Forms\Components\TextInput::make('reference_number')
                    ->label('Reference Number')
                    ->maxLength(255),

                Forms\Components\Textarea::make('notes')
                    ->label('Notes')
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Payment #')
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount_minor')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->suffix(' ' . current_currency())
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('journal.name')
                    ->label('Method')
                    ->badge()
                    ->color(fn (Payment $record) => $record->journal?->type_color ?? 'gray'),

                Tables\Columns\TextColumn::make('reference_number')
                    ->label('Reference')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('receivedBy.name')
                    ->label('Received By'),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Date/Time')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => $this->ownerRecord->canRecordPayment())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['received_by_user_id'] = auth()->id();
                        return $data;
                    }),

                Tables\Actions\Action::make('link_payment')
                    ->label('Link Payment')
                    ->icon('heroicon-o-link')
                    ->color('info')
                    ->visible(fn () => $this->ownerRecord->canRecordPayment())
                    ->form([
                        Forms\Components\Select::make('payment_id')
                            ->label('Select Unassigned Payment')
                            ->options(function () {
                                /** @var Invoice $invoice */
                                $invoice = $this->ownerRecord;
                                return Payment::unassigned()
                                    ->where('patient_id', $invoice->patient_id)
                                    ->get()
                                    ->mapWithKeys(fn (Payment $payment) => [
                                        $payment->id => sprintf(
                                            '%s - %s %s (%s)',
                                            $payment->code,
                                            current_currency(),
                                            number_format($payment->amount_minor / 100, 2),
                                            $payment->paid_at?->format('Y-m-d')
                                        ),
                                    ]);
                            })
                            ->required()
                            ->searchable()
                            ->helperText('Only showing unassigned payments for this patient'),
                    ])
                    ->action(function (array $data) {
                        $payment = Payment::find($data['payment_id']);
                        if ($payment) {
                            /** @var Invoice $invoice */
                            $invoice = $this->ownerRecord;
                            $invoice->applyUnassignedPayment($payment);

                            Notification::make()
                                ->title('Payment linked successfully')
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('unlink')
                    ->label('Unlink')
                    ->icon('heroicon-o-x-mark')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Unlink Payment')
                    ->modalDescription('This will unlink the payment from this invoice. The payment will become unassigned and can be linked to another invoice.')
                    ->action(function (Payment $record) {
                        $record->invoice_id = null;
                        $record->save();

                        // Recalculate invoice paid amount and status
                        /** @var Invoice $invoice */
                        $invoice = $this->ownerRecord;
                        $invoice->paid_minor = $invoice->payments()->sum('amount_minor');

                        // Update status based on paid amount
                        if ($invoice->paid_minor >= $invoice->total_minor) {
                            $invoice->status = Invoice::STATUS_PAID;
                        } elseif ($invoice->paid_minor > 0) {
                            $invoice->status = Invoice::STATUS_PARTIALLY_PAID;
                        } elseif ($invoice->issued_at) {
                            $invoice->status = Invoice::STATUS_ISSUED;
                        }
                        $invoice->save();

                        Notification::make()
                            ->title('Payment unlinked successfully')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('paid_at', 'desc');
    }
}
