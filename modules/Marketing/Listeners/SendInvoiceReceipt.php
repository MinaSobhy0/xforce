<?php

namespace Modules\Marketing\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Modules\Billing\Events\InvoicePaid;
use Modules\Marketing\Models\AutomationRule;
use Modules\Marketing\Services\NotificationService;

class SendInvoiceReceipt implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create the event listener.
     */
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(InvoicePaid $event): void
    {
        $invoice = $event->invoice;
        $patient = $invoice->patient;

        if (!$patient) {
            Log::warning('InvoicePaid event: No patient found', [
                'invoice_id' => $invoice->id,
            ]);
            return;
        }

        // Check if patient has consented to messaging
        if (!$this->hasMessagingConsent($patient)) {
            Log::info('InvoicePaid: Patient has not consented to messaging', [
                'invoice_id' => $invoice->id,
                'patient_id' => $patient->id,
            ]);
            return;
        }

        // Find active automation rules for invoice paid
        $rules = AutomationRule::query()
            ->where('tenant_id', $invoice->tenant_id)
            ->where('trigger_type', AutomationRule::TRIGGER_INVOICE_PAID)
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->with('template')
            ->get();

        if ($rules->isEmpty()) {
            Log::info('InvoicePaid: No active automation rules found', [
                'invoice_id' => $invoice->id,
                'tenant_id' => $invoice->tenant_id,
            ]);
            return;
        }

        // Build context for condition matching
        $context = [
            'branch_id' => $invoice->branch_id,
            'patient_gender' => $patient->gender,
            'total_minor' => $invoice->total_minor,
        ];

        // Process each matching rule
        foreach ($rules as $rule) {
            if (!$rule->template || !$rule->template->is_active) {
                continue;
            }

            // Check conditions
            if (!$rule->matchesConditions($context)) {
                continue;
            }

            // Build variables for the template
            $variables = $this->buildTemplateVariables($invoice);

            // Send using the notification service
            try {
                $log = $this->notificationService->sendTemplate(
                    $rule->template,
                    $patient,
                    $variables,
                    'invoice',
                    (string) $invoice->id
                );

                Log::info('InvoicePaid: Notification sent', [
                    'invoice_id' => $invoice->id,
                    'rule_id' => $rule->id,
                    'template_id' => $rule->template_id,
                    'notification_log_id' => $log->id,
                    'status' => $log->status,
                ]);

                // Only send one notification per trigger (first matching rule)
                break;
            } catch (\Exception $e) {
                Log::error('InvoicePaid: Failed to send notification', [
                    'invoice_id' => $invoice->id,
                    'rule_id' => $rule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Check if patient has consented to receive messages.
     */
    protected function hasMessagingConsent($patient): bool
    {
        return $patient->marketing_consent
            || $patient->sms_consent
            || $patient->whatsapp_consent
            || $patient->email_consent;
    }

    /**
     * Build template variables from invoice data.
     */
    protected function buildTemplateVariables($invoice): array
    {
        return [
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => $invoice->invoice_date?->format('d/m/Y'),
            'invoice_total' => number_format($invoice->total_minor / 100, 2) . ' ' . ($invoice->currency_code ?? 'EGP'),
            'payment_amount' => number_format($invoice->paid_minor / 100, 2) . ' ' . ($invoice->currency_code ?? 'EGP'),
            'branch_name' => $invoice->branch?->name ?? '',
            'branch_phone' => $invoice->branch?->phone ?? '',
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(InvoicePaid $event, \Throwable $exception): void
    {
        Log::error('SendInvoiceReceipt job failed', [
            'invoice_id' => $event->invoice->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
