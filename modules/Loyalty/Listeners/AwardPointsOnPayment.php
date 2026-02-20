<?php

namespace Modules\Loyalty\Listeners;

use Modules\Loyalty\Models\LoyaltyRule;
use Modules\Loyalty\Models\LoyaltyTransaction;
use Modules\Loyalty\Services\LoyaltyService;
use Illuminate\Contracts\Queue\ShouldQueue;

class AwardPointsOnPayment implements ShouldQueue
{
    protected LoyaltyService $loyaltyService;

    public function __construct(LoyaltyService $loyaltyService)
    {
        $this->loyaltyService = $loyaltyService;
    }

    public function handle($event): void
    {
        $payment = $event->payment ?? $event;

        // Get the invoice and patient
        $invoice = $payment->invoice;
        if (!$invoice) {
            return;
        }

        $patient = $invoice->patient;
        if (!$patient) {
            return;
        }

        // Calculate points based on payment amount
        $amountMinor = $payment->amount_minor;

        // Get treatment info from invoice lines for targeted rules
        $treatmentId = null;
        $categoryId = null;

        $firstLine = $invoice->lines()->whereNotNull('treatment_id')->first();
        if ($firstLine && $firstLine->treatment) {
            $treatmentId = $firstLine->treatment_id;
            $categoryId = $firstLine->treatment->category_id;
        }

        // Find applicable rule
        $rule = LoyaltyRule::active()
            ->byType(LoyaltyRule::TYPE_PER_SPEND)
            ->ordered()
            ->first();

        if (!$rule) {
            return;
        }

        $points = $rule->calculatePoints($amountMinor);

        if ($points <= 0) {
            return;
        }

        // Award points
        $this->loyaltyService->awardPoints(
            $patient,
            $points,
            LoyaltyTransaction::TYPE_EARN,
            __('loyalty::loyalty.transaction_descriptions.payment_bonus', [
                'amount' => number_format($amountMinor / 100, 2),
            ]),
            $rule,
            get_class($payment),
            $payment->id,
            [
                'payment_amount_minor' => $amountMinor,
                'invoice_code' => $invoice->code,
            ]
        );

        // Check for first purchase bonus
        $this->checkFirstPurchaseBonus($patient, $payment);
    }

    protected function checkFirstPurchaseBonus($patient, $payment): void
    {
        // Check if this is the patient's first payment
        $paymentCount = $patient->invoices()
            ->whereHas('payments')
            ->count();

        if ($paymentCount > 1) {
            return;
        }

        $rule = LoyaltyRule::active()
            ->byType(LoyaltyRule::TYPE_FIRST_PURCHASE)
            ->ordered()
            ->first();

        if (!$rule || $rule->points_amount <= 0) {
            return;
        }

        $this->loyaltyService->awardPoints(
            $patient,
            $rule->points_amount,
            LoyaltyTransaction::TYPE_BONUS,
            __('loyalty::loyalty.transaction_descriptions.first_purchase_bonus'),
            $rule
        );
    }
}
