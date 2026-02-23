<?php

namespace Modules\TreatmentPlans\Filament\Resources\TreatmentPlanResource\Pages;

use Modules\TreatmentPlans\Filament\Resources\TreatmentPlanResource;
use Modules\TreatmentPlans\Models\TreatmentPlan;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Accounting\Models\Journal;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use App\Filament\Resources\Pages\BaseViewRecord;

class ViewTreatmentPlan extends BaseViewRecord
{
    protected static string $resource = TreatmentPlanResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->isEditable()),

            Actions\Action::make('activate')
                ->label(__('treatment_plans::treatment_plans.actions.activate'))
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn () => $this->record->canTransitionTo(TreatmentPlan::STATUS_ACTIVE))
                ->requiresConfirmation()
                ->modalHeading(__('treatment_plans::treatment_plans.confirmations.activate'))
                ->action(function () {
                    if ($this->record->activate()) {
                        Notification::make()
                            ->title(__('treatment_plans::treatment_plans.messages.activated'))
                            ->success()
                            ->send();
                    }
                }),

            Actions\Action::make('complete')
                ->label(__('treatment_plans::treatment_plans.actions.complete'))
                ->icon('heroicon-o-check-circle')
                ->color('info')
                ->visible(fn () => $this->record->canTransitionTo(TreatmentPlan::STATUS_COMPLETED))
                ->requiresConfirmation()
                ->modalHeading(__('treatment_plans::treatment_plans.confirmations.complete'))
                ->action(function () {
                    if ($this->record->complete()) {
                        Notification::make()
                            ->title(__('treatment_plans::treatment_plans.messages.completed'))
                            ->success()
                            ->send();
                    }
                }),

            Actions\Action::make('cancel')
                ->label(__('treatment_plans::treatment_plans.actions.cancel'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->canTransitionTo(TreatmentPlan::STATUS_CANCELLED))
                ->requiresConfirmation()
                ->modalHeading(__('treatment_plans::treatment_plans.confirmations.cancel'))
                ->form([
                    \Filament\Forms\Components\Textarea::make('cancellation_reason')
                        ->label(__('treatment_plans::treatment_plans.fields.cancellation_reason'))
                        ->required(),
                ])
                ->action(function (array $data) {
                    if ($this->record->cancel($data['cancellation_reason'])) {
                        Notification::make()
                            ->title(__('treatment_plans::treatment_plans.messages.cancelled'))
                            ->success()
                            ->send();
                    }
                }),

            Actions\Action::make('book_appointment')
                ->label(__('treatment_plans::treatment_plans.actions.book_appointment'))
                ->icon('heroicon-o-calendar')
                ->color('primary')
                ->visible(fn () => $this->record->isActive() && $this->record->items_needing_scheduling->count() > 0)
                ->url(fn () => route('filament.tenant.pages.create-booking', [
                    'tenant' => current_tenant_id(),
                    'booking_type' => 'treatment_plan',
                    'treatment_plan_id' => $this->record->id,
                ])),

            Actions\Action::make('collect_deposit')
                ->label(__('treatment_plans::treatment_plans.actions.collect_deposit'))
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn () => $this->record->isActive())
                ->form([
                    Forms\Components\TextInput::make('amount')
                        ->label(__('treatment_plans::treatment_plans.financials.amount'))
                        ->required()
                        ->numeric()
                        ->minValue(0.01)
                        ->prefix(config('app.currency_symbol', '$')),

                    Forms\Components\Select::make('journal_id')
                        ->label(__('treatment_plans::treatment_plans.financials.payment_method'))
                        ->options(fn () => Journal::whereIn('type', ['cash', 'bank', 'sales'])
                            ->pluck('name', 'id'))
                        ->required()
                        ->searchable(),

                    Forms\Components\TextInput::make('reference_number')
                        ->label(__('treatment_plans::treatment_plans.financials.reference'))
                        ->maxLength(100),

                    Forms\Components\Textarea::make('notes')
                        ->label(__('treatment_plans::treatment_plans.fields.notes'))
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    $payment = Payment::create([
                        'tenant_id' => $this->record->tenant_id,
                        'patient_id' => $this->record->patient_id,
                        'branch_id' => $this->record->branch_id,
                        'treatment_plan_id' => $this->record->id,
                        'invoice_id' => null, // Unassigned payment (deposit)
                        'journal_id' => $data['journal_id'],
                        'amount_minor' => (int) ($data['amount'] * 100),
                        'reference_number' => $data['reference_number'] ?? null,
                        'notes' => $data['notes'] ?? null,
                        'received_by_user_id' => auth()->id(),
                        'status' => Payment::STATUS_COMPLETED,
                        'paid_at' => now(),
                    ]);

                    $this->record->recalculateFinancials();

                    Notification::make()
                        ->title(__('treatment_plans::treatment_plans.messages.deposit_collected'))
                        ->body(__('treatment_plans::treatment_plans.messages.deposit_amount', ['amount' => $data['amount']]))
                        ->success()
                        ->send();
                }),

            Actions\Action::make('generate_invoice')
                ->label(__('treatment_plans::treatment_plans.actions.generate_invoice'))
                ->icon('heroicon-o-document-text')
                ->color('warning')
                ->visible(fn () => ($this->record->isActive() || $this->record->isCompleted())
                    && $this->record->items->where('can_be_invoiced', true)->count() > 0)
                ->form([
                    Forms\Components\CheckboxList::make('items')
                        ->label(__('treatment_plans::treatment_plans.financials.items_to_invoice'))
                        ->options(fn () => $this->record->items
                            ->where('can_be_invoiced', true)
                            ->mapWithKeys(fn ($item) => [
                                $item->id => $item->item_name . ' (' . $item->remaining_to_invoice . ' ' . __('treatment_plans::treatment_plans.financials.to_invoice') . ')'
                            ]))
                        ->required()
                        ->columns(1),

                    Forms\Components\Toggle::make('apply_deposits')
                        ->label(__('treatment_plans::treatment_plans.financials.apply_deposits'))
                        ->default(true)
                        ->visible(fn () => $this->record->available_deposits_amount > 0)
                        ->helperText(fn () => __('treatment_plans::treatment_plans.financials.available_deposits', [
                            'amount' => number_format($this->record->available_deposits_amount / 100, 2)
                        ])),

                    Forms\Components\DatePicker::make('due_date')
                        ->label(__('treatment_plans::treatment_plans.financials.due_date'))
                        ->default(now()->addDays(30)),
                ])
                ->action(function (array $data) {
                    $invoice = Invoice::create([
                        'tenant_id' => $this->record->tenant_id,
                        'patient_id' => $this->record->patient_id,
                        'branch_id' => $this->record->branch_id,
                        'treatment_plan_id' => $this->record->id,
                        'type' => Invoice::TYPE_STANDARD,
                        'status' => Invoice::STATUS_DRAFT,
                        'due_date' => $data['due_date'] ?? now()->addDays(30),
                        'created_by_user_id' => auth()->id(),
                    ]);

                    // Add items to invoice
                    $sortOrder = 0;
                    foreach ($data['items'] as $itemId) {
                        $planItem = $this->record->items->find($itemId);
                        if (!$planItem) continue;

                        $quantity = $planItem->remaining_to_invoice;
                        $unitPrice = $planItem->unit_price_minor;

                        InvoiceLine::create([
                            'tenant_id' => $this->record->tenant_id,
                            'invoice_id' => $invoice->id,
                            'service_id' => $planItem->service_id,
                            'treatment_plan_item_id' => $planItem->id,
                            'description' => $planItem->item_name,
                            'quantity' => $quantity,
                            'unit_price_minor' => $unitPrice,
                            'discount_minor' => 0,
                            'tax_rate' => config('billing.default_tax_rate', 0),
                            'sort_order' => $sortOrder++,
                        ]);
                    }

                    $invoice->recalculateTotals();

                    // Apply deposits if requested
                    if ($data['apply_deposits'] ?? false) {
                        $deposits = $this->record->unassignedPayments()->orderBy('paid_at')->get();
                        foreach ($deposits as $deposit) {
                            if ($invoice->remaining_minor <= 0) break;
                            $invoice->applyUnassignedPayment($deposit);
                        }
                    }

                    // Issue the invoice
                    $invoice->issue();

                    $this->record->recalculateFinancials();

                    Notification::make()
                        ->title(__('treatment_plans::treatment_plans.messages.invoice_generated'))
                        ->body(__('treatment_plans::treatment_plans.messages.invoice_code', ['code' => $invoice->code]))
                        ->success()
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('view')
                                ->label(__('treatment_plans::treatment_plans.actions.view_invoice'))
                                ->url(route('filament.tenant.resources.invoices.view', [
                                    'tenant' => current_tenant_id(),
                                    'record' => $invoice->id,
                                ]))
                                ->button(),
                        ])
                        ->send();
                }),
        ];
    }
}
