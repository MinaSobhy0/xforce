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

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('billing::billing.payments');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('amount_minor')
                    ->label(__('billing::billing.fields.amount'))
                    ->numeric()
                    ->required()
                    ->prefix(current_currency())
                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                    ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0),

                Forms\Components\Select::make('journal_id')
                    ->label(__('billing::billing.fields.payment_method'))
                    ->options(fn () => Journal::active()
                        ->whereIn('type', ['cash', 'bank'])
                        ->get()
                        ->pluck('display_name', 'id'))
                    ->required()
                    ->searchable()
                    ->default(fn () => Journal::getCashJournal()?->id),

                Forms\Components\DateTimePicker::make('paid_at')
                    ->label(__('billing::billing.fields.paid_at'))
                    ->required()
                    ->default(now()),

                Forms\Components\TextInput::make('reference_number')
                    ->label(__('billing::billing.fields.reference'))
                    ->maxLength(255),

                Forms\Components\Textarea::make('notes')
                    ->label(__('billing::billing.fields.notes'))
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('billing::billing.fields.code'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount_minor')
                    ->label(__('billing::billing.fields.amount'))
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->suffix(' ' . current_currency())
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('journal.name')
                    ->label(__('billing::billing.relation.method'))
                    ->badge()
                    ->color(fn (Payment $record) => $record->journal?->type_color ?? 'gray'),

                Tables\Columns\TextColumn::make('reference_number')
                    ->label(__('billing::billing.fields.reference'))
                    ->placeholder(__('billing::billing.placeholders.no_reference')),

                Tables\Columns\TextColumn::make('receivedBy.name')
                    ->label(__('billing::billing.relation.received_by')),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label(__('billing::billing.relation.date_time'))
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
                    ->label(__('billing::billing.relation.link_payment'))
                    ->icon('heroicon-o-link')
                    ->color('info')
                    ->visible(fn () => $this->ownerRecord->canRecordPayment())
                    ->form([
                        Forms\Components\Select::make('payment_id')
                            ->label(__('billing::billing.relation.select_unassigned_payment'))
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
                            ->helperText(__('billing::billing.relation.unassigned_payments_help')),
                    ])
                    ->action(function (array $data) {
                        $payment = Payment::find($data['payment_id']);
                        if ($payment) {
                            /** @var Invoice $invoice */
                            $invoice = $this->ownerRecord;
                            $invoice->applyUnassignedPayment($payment);

                            Notification::make()
                                ->title(__('billing::billing.relation.payment_linked'))
                                ->success()
                                ->send();

                            $this->redirect(request()->header('Referer'));
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('unlink')
                    ->label(__('billing::billing.relation.unlink'))
                    ->icon('heroicon-o-x-mark')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(__('billing::billing.relation.unlink_payment'))
                    ->modalDescription(__('billing::billing.relation.unlink_description'))
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
                            ->title(__('billing::billing.relation.payment_unlinked'))
                            ->success()
                            ->send();

                        $this->redirect(request()->header('Referer'));
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('paid_at', 'desc');
    }
}
