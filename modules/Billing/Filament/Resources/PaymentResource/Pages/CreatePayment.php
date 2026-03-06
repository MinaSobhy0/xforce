<?php

namespace Modules\Billing\Filament\Resources\PaymentResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Billing\Filament\Resources\PaymentResource;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    public ?string $prefilledPatientId = null;

    public function mount(): void
    {
        parent::mount();

        // Check for patient_id in query string to pre-fill form
        $this->prefilledPatientId = request()->query('patient_id');

        if ($this->prefilledPatientId) {
            // Find the first unpaid invoice for this patient
            $invoice = Invoice::where('patient_id', $this->prefilledPatientId)
                ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIALLY_PAID])
                ->where('remaining_minor', '>', 0)
                ->orderBy('created_at', 'asc')
                ->first();

            if ($invoice) {
                $this->form->fill([
                    'type' => Payment::TYPE_RECEIVE,
                    'invoice_id' => $invoice->id,
                    'patient_id' => $invoice->patient_id,
                    'branch_id' => $invoice->branch_id,
                    'amount_minor' => $invoice->remaining_minor / 100,
                ]);
            }
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Convert amount to minor units
        if (isset($data['amount_minor'])) {
            $data['amount_minor'] = (int) ($data['amount_minor'] * 100);
        }

        // Set the user who recorded this payment
        $data['received_by_user_id'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
