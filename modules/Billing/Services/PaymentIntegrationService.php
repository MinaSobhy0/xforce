<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Facades\DB;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\GiftCards\Models\GiftCard;
use Modules\Loyalty\Services\LoyaltyService;
use Modules\Memberships\Models\MembershipSubscription;
use Modules\Patients\Models\Patient;
use Modules\Accounting\Models\Journal;

class PaymentIntegrationService
{
    protected LoyaltyService $loyaltyService;
    protected InvoiceCalculationService $calculationService;

    public function __construct(
        LoyaltyService $loyaltyService,
        InvoiceCalculationService $calculationService
    ) {
        $this->loyaltyService = $loyaltyService;
        $this->calculationService = $calculationService;
    }

    /**
     * Process a gift card payment for an invoice.
     */
    public function payWithGiftCard(
        Invoice $invoice,
        string $giftCardCode,
        ?int $amountMinor = null
    ): array {
        $giftCard = GiftCard::where('code', $giftCardCode)
            ->where('tenant_id', $invoice->tenant_id)
            ->first();

        if (!$giftCard) {
            return [
                'success' => false,
                'error' => __('billing::billing.errors.gift_card_not_found'),
            ];
        }

        if (!$giftCard->canRedeem()) {
            return [
                'success' => false,
                'error' => __('billing::billing.errors.gift_card_not_redeemable', [
                    'status' => $giftCard->status_label,
                ]),
            ];
        }

        // Calculate how much to pay
        $remainingOnInvoice = $invoice->remaining_minor;
        $availableOnCard = $giftCard->remaining_value_minor;
        $paymentAmount = $amountMinor ?? min($remainingOnInvoice, $availableOnCard);

        // Ensure we don't pay more than available
        $paymentAmount = min($paymentAmount, $availableOnCard, $remainingOnInvoice);

        if ($paymentAmount <= 0) {
            return [
                'success' => false,
                'error' => __('billing::billing.errors.nothing_to_pay'),
            ];
        }

        return DB::transaction(function () use ($invoice, $giftCard, $paymentAmount) {
            // Get or create a Gift Card payment journal
            $journal = $this->getOrCreateGiftCardJournal($invoice->tenant_id);

            // Create the payment
            $payment = Payment::create([
                'tenant_id' => $invoice->tenant_id,
                'invoice_id' => $invoice->id,
                'journal_id' => $journal->id,
                'amount_minor' => $paymentAmount,
                'gift_card_id' => $giftCard->id,
                'reference_number' => $giftCard->code,
                'notes' => __('billing::billing.notes.gift_card_payment', [
                    'code' => $giftCard->code,
                ]),
                'paid_at' => now(),
                'received_by_user_id' => auth()->id(),
            ]);

            // Redeem from gift card
            $giftCard->redeem(
                $paymentAmount,
                $invoice->id,
                $payment->id,
                __('giftcards::giftcards.redemption_for_invoice', [
                    'invoice' => $invoice->code,
                ])
            );

            return [
                'success' => true,
                'payment' => $payment,
                'amount_paid' => $paymentAmount,
                'gift_card_remaining' => $giftCard->fresh()->remaining_value_minor,
                'invoice_remaining' => $invoice->fresh()->remaining_minor,
            ];
        });
    }

    /**
     * Process a loyalty points payment for an invoice.
     */
    public function payWithLoyaltyPoints(
        Invoice $invoice,
        ?int $points = null
    ): array {
        $patient = $invoice->patient;

        if (!$patient) {
            return [
                'success' => false,
                'error' => __('billing::billing.errors.no_patient_on_invoice'),
            ];
        }

        $availablePoints = $this->loyaltyService->getBalance($patient);
        $pointsToUse = $points ?? $availablePoints;

        // Limit to available points
        $pointsToUse = min($pointsToUse, $availablePoints);

        if ($pointsToUse <= 0) {
            return [
                'success' => false,
                'error' => __('billing::billing.errors.no_loyalty_points'),
            ];
        }

        // Convert points to currency
        $pointsValue = $this->loyaltyService->pointsToCurrency($pointsToUse);

        // Don't pay more than remaining
        $remainingOnInvoice = $invoice->remaining_minor;
        if ($pointsValue > $remainingOnInvoice) {
            // Calculate how many points needed for remaining balance
            $pointsToUse = $this->loyaltyService->currencyToPoints($remainingOnInvoice);
            $pointsValue = $remainingOnInvoice;
        }

        if ($pointsValue <= 0) {
            return [
                'success' => false,
                'error' => __('billing::billing.errors.nothing_to_pay'),
            ];
        }

        return DB::transaction(function () use ($invoice, $patient, $pointsToUse, $pointsValue) {
            // Get or create a Loyalty Points payment journal
            $journal = $this->getOrCreateLoyaltyJournal($invoice->tenant_id);

            // Create the payment
            $payment = Payment::create([
                'tenant_id' => $invoice->tenant_id,
                'invoice_id' => $invoice->id,
                'journal_id' => $journal->id,
                'amount_minor' => $pointsValue,
                'reference_number' => "LP-{$pointsToUse}",
                'notes' => __('billing::billing.notes.loyalty_points_payment', [
                    'points' => $pointsToUse,
                ]),
                'paid_at' => now(),
                'received_by_user_id' => auth()->id(),
            ]);

            // Redeem loyalty points
            $this->loyaltyService->redeemPoints(
                $patient,
                $pointsToUse,
                __('loyalty::loyalty.transaction_descriptions.invoice_payment', [
                    'invoice' => $invoice->code,
                ]),
                Invoice::class,
                $invoice->id
            );

            return [
                'success' => true,
                'payment' => $payment,
                'points_used' => $pointsToUse,
                'amount_paid' => $pointsValue,
                'points_remaining' => $this->loyaltyService->getBalance($patient->fresh()),
                'invoice_remaining' => $invoice->fresh()->remaining_minor,
            ];
        });
    }

    /**
     * Calculate member discount for a patient.
     */
    public function calculateMemberDiscount(Patient $patient, int $subtotalMinor): array
    {
        $subscription = MembershipSubscription::forPatient($patient->id)
            ->active()
            ->with('membership')
            ->first();

        if (!$subscription || !$subscription->membership) {
            return [
                'has_discount' => false,
                'discount_percentage' => 0,
                'discount_amount_minor' => 0,
                'membership_tier' => null,
            ];
        }

        $membership = $subscription->membership;
        $discountPercentage = $membership->discount_percentage ?? 0;
        $discountAmount = (int) round($subtotalMinor * $discountPercentage / 100);

        return [
            'has_discount' => $discountPercentage > 0,
            'discount_percentage' => $discountPercentage,
            'discount_amount_minor' => $discountAmount,
            'membership_tier' => $membership->tier,
            'membership_name' => $membership->translated_name,
        ];
    }

    /**
     * Check if patient has included sessions for a treatment.
     */
    public function getIncludedSessions(Patient $patient, string $treatmentId): array
    {
        $subscription = MembershipSubscription::forPatient($patient->id)
            ->active()
            ->with('membership')
            ->first();

        if (!$subscription || !$subscription->membership) {
            return [
                'has_sessions' => false,
                'included_monthly' => 0,
                'used_this_month' => 0,
                'remaining' => 0,
            ];
        }

        $membership = $subscription->membership;
        $includedMonthly = $membership->getIncludedSessionsForTreatment($treatmentId);

        if ($includedMonthly <= 0) {
            return [
                'has_sessions' => false,
                'included_monthly' => 0,
                'used_this_month' => 0,
                'remaining' => 0,
            ];
        }

        // Count sessions used this month
        $usedThisMonth = $subscription->getSessionsUsedThisMonth($treatmentId);
        $remaining = max(0, $includedMonthly - $usedThisMonth);

        return [
            'has_sessions' => $remaining > 0,
            'included_monthly' => $includedMonthly,
            'used_this_month' => $usedThisMonth,
            'remaining' => $remaining,
            'membership_tier' => $membership->tier,
        ];
    }

    /**
     * Apply member discount to an invoice.
     */
    public function applyMemberDiscountToInvoice(Invoice $invoice): bool
    {
        if (!$invoice->patient) {
            return false;
        }

        $discount = $this->calculateMemberDiscount(
            $invoice->patient,
            $invoice->subtotal_minor
        );

        if (!$discount['has_discount']) {
            return false;
        }

        // Apply discount
        $invoice->discount_minor += $discount['discount_amount_minor'];
        $invoice->total_minor = max(0, $invoice->subtotal_minor + $invoice->tax_minor - $invoice->discount_minor);
        $invoice->notes = ($invoice->notes ? $invoice->notes . "\n" : '') .
            __('billing::billing.notes.member_discount_applied', [
                'tier' => $discount['membership_tier'],
                'percentage' => $discount['discount_percentage'],
            ]);

        return $invoice->save();
    }

    /**
     * Get available payment options for an invoice.
     */
    public function getPaymentOptions(Invoice $invoice): array
    {
        $options = [];
        $patient = $invoice->patient;
        $remainingMinor = $invoice->remaining_minor;

        // Cash/Card (always available)
        $options['cash'] = [
            'available' => true,
            'label' => __('billing::billing.payment_methods.cash'),
        ];

        $options['card'] = [
            'available' => true,
            'label' => __('billing::billing.payment_methods.card'),
        ];

        // Gift cards for patient
        if ($patient) {
            $giftCards = GiftCard::forPatient($patient->id)
                ->active()
                ->withBalance()
                ->get();

            $options['gift_card'] = [
                'available' => $giftCards->isNotEmpty(),
                'label' => __('billing::billing.payment_methods.gift_card'),
                'cards' => $giftCards->map(fn ($card) => [
                    'id' => $card->id,
                    'code' => $card->code,
                    'balance' => $card->remaining_value_minor,
                    'formatted_balance' => $card->formatted_remaining_value,
                ])->toArray(),
            ];

            // Loyalty points
            $pointsBalance = $this->loyaltyService->getBalance($patient);
            $pointsValue = $this->loyaltyService->pointsToCurrency($pointsBalance);

            $options['loyalty_points'] = [
                'available' => $pointsBalance > 0 && $pointsValue > 0,
                'label' => __('billing::billing.payment_methods.loyalty_points'),
                'points_balance' => $pointsBalance,
                'points_value_minor' => min($pointsValue, $remainingMinor),
                'formatted_value' => number_format(min($pointsValue, $remainingMinor) / 100, 2) . ' EGP',
            ];

            // Member discount info
            $memberDiscount = $this->calculateMemberDiscount($patient, $invoice->subtotal_minor);
            $options['member_discount'] = $memberDiscount;
        }

        return $options;
    }

    /**
     * Get or create a journal for gift card payments.
     */
    protected function getOrCreateGiftCardJournal(string $tenantId): Journal
    {
        return Journal::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'code' => 'GC',
            ],
            [
                'name' => ['en' => 'Gift Card', 'ar' => 'بطاقة هدايا'],
                'type' => 'sales',
                'is_active' => true,
            ]
        );
    }

    /**
     * Get or create a journal for loyalty points payments.
     */
    protected function getOrCreateLoyaltyJournal(string $tenantId): Journal
    {
        return Journal::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'code' => 'LP',
            ],
            [
                'name' => ['en' => 'Loyalty Points', 'ar' => 'نقاط الولاء'],
                'type' => 'sales',
                'is_active' => true,
            ]
        );
    }
}
