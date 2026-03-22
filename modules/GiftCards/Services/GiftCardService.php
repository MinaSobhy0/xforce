<?php

namespace Modules\GiftCards\Services;

use Modules\GiftCards\Models\GiftCard;
use Modules\GiftCards\Models\GiftCardTemplate;
use Modules\GiftCards\Models\GiftCardTransaction;
use Modules\GiftCards\Models\GiftCardBatchExport;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Patients\Models\Patient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GiftCardService
{
    protected GiftCardGeneratorService $generator;
    protected GiftCardGLService $glService;

    public function __construct(
        GiftCardGeneratorService $generator,
        GiftCardGLService $glService
    ) {
        $this->generator = $generator;
        $this->glService = $glService;
    }

    /**
     * Generate batch of gift cards from template
     */
    public function generateBatch(
        GiftCardTemplate $template,
        int $quantity,
        ?array $options = []
    ): Collection {
        $valueMinor = $template->amount_minor;
        $cards = collect();

        DB::transaction(function () use ($template, $quantity, $valueMinor, $options, &$cards) {
            for ($i = 0; $i < $quantity; $i++) {
                $serial = $this->generator->generateSerialNumber();

                $card = GiftCard::create([
                    'tenant_id' => $template->tenant_id,
                    'template_id' => $template->id,
                    'code' => $this->generator->formatCardNumber($serial),
                    'encrypted_code' => $this->generator->encryptCode($serial),
                    'code_hash' => $this->generator->hashCode($serial),
                    'initial_value_minor' => $valueMinor,
                    'remaining_value_minor' => $valueMinor,
                    'status' => $template->requires_activation
                        ? GiftCard::STATUS_DRAFT
                        : GiftCard::STATUS_ACTIVE,
                    'expires_at' => $template->calculateExpiryDate(),
                    'pin_code' => ($options['generate_pin'] ?? false)
                        ? $this->generator->generatePinCode()
                        : null,
                ]);

                // If not requiring activation, record the activation transaction
                if (!$template->requires_activation) {
                    GiftCardTransaction::create([
                        'tenant_id' => $card->tenant_id,
                        'gift_card_id' => $card->id,
                        'type' => GiftCardTransaction::TYPE_ACTIVATE,
                        'amount_minor' => $card->initial_value_minor,
                        'running_balance_minor' => $card->initial_value_minor,
                        'notes' => 'Auto-activated on generation',
                        'created_by_user_id' => auth()->id(),
                    ]);
                }

                $cards->push($card);
            }

            // Create batch export record
            GiftCardBatchExport::create([
                'tenant_id' => $template->tenant_id,
                'template_id' => $template->id,
                'quantity' => $quantity,
                'card_ids' => $cards->pluck('id')->toArray(),
                'metadata' => [
                    'value_minor' => $valueMinor,
                    'options' => $options,
                ],
                'exported_by' => auth()->id(),
            ]);
        });

        Log::info("Generated {$quantity} gift cards from template {$template->code}", [
            'template_id' => $template->id,
            'quantity' => $quantity,
            'value_minor' => $valueMinor,
        ]);

        return $cards;
    }

    /**
     * Assign cards to staff member
     */
    public function assignToStaff(Collection $cards, string $staffId): int
    {
        $count = 0;

        foreach ($cards as $card) {
            // Allow assignment for draft cards (can reassign)
            if ($card->status === GiftCard::STATUS_DRAFT) {
                $card->update([
                    'assigned_to_staff_id' => $staffId,
                    'assigned_at' => now(),
                ]);
                $count++;
            }
        }

        Log::info("Assigned {$count} gift cards to staff", [
            'staff_id' => $staffId,
            'count' => $count,
        ]);

        return $count;
    }

    /**
     * Unassign cards from staff
     */
    public function unassignFromStaff(Collection $cards): int
    {
        $count = 0;

        foreach ($cards as $card) {
            if ($card->isDraft() && $card->assigned_to_staff_id) {
                $card->update([
                    'assigned_to_staff_id' => null,
                    'assigned_at' => null,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Process gift card sale with GL entry
     *
     * @param GiftCard $card
     * @param string $journalId
     * @param string|array|null $purchaserPatientIdOrData - Patient ID string or array with new patient data
     * @param string|null $recipientPatientId
     * @param string|null $notes
     * @param int $extraDiscountMinor - Additional discount beyond template discount
     * @return array
     */
    public function processSale(
        GiftCard $card,
        string $journalId,
        string|array|null $purchaserPatientIdOrData = null,
        ?string $recipientPatientId = null,
        ?string $notes = null,
        int $extraDiscountMinor = 0
    ): array {
        if (!$card->isDraft()) {
            return ['success' => false, 'error' => 'Card must be in draft status to sell'];
        }

        // Handle patient resolution BEFORE the transaction to avoid FK issues
        $purchaserPatientId = null;

        if (is_array($purchaserPatientIdOrData) && !empty($purchaserPatientIdOrData['first_name'])) {
            // Create new patient using model to trigger sequence generation
            $patient = Patient::create([
                'tenant_id' => $card->tenant_id,
                'first_name' => $purchaserPatientIdOrData['first_name'],
                'last_name' => $purchaserPatientIdOrData['last_name'] ?? '',
                'phone' => $purchaserPatientIdOrData['phone'] ?? null,
                'email' => $purchaserPatientIdOrData['email'] ?? null,
            ]);

            $purchaserPatientId = $patient->id;

            Log::info("Created new patient for gift card sale", [
                'patient_id' => $patient->id,
                'patient_code' => $patient->code,
                'card_id' => $card->id,
            ]);
        } elseif (is_string($purchaserPatientIdOrData) && !empty($purchaserPatientIdOrData)) {
            // Verify existing patient exists
            $exists = Patient::where('id', $purchaserPatientIdOrData)->exists();
            if (!$exists) {
                Log::error("Patient not found for gift card sale", [
                    'patient_id' => $purchaserPatientIdOrData,
                    'card_id' => $card->id,
                ]);
                return ['success' => false, 'error' => 'Patient not found'];
            }
            $purchaserPatientId = $purchaserPatientIdOrData;
        }

        // Calculate discounts
        $templateDiscountMinor = $card->calculateTemplateDiscount();
        $totalDiscountMinor = $templateDiscountMinor + $extraDiscountMinor;
        $soldPriceMinor = $card->initial_value_minor - $totalDiscountMinor;

        // Ensure sold price is not negative
        if ($soldPriceMinor < 0) {
            return ['success' => false, 'error' => 'Total discount exceeds card value'];
        }

        DB::transaction(function () use ($card, $journalId, $purchaserPatientId, $recipientPatientId, $notes, $templateDiscountMinor, $extraDiscountMinor, $totalDiscountMinor, $soldPriceMinor) {
            // Update card
            $card->update([
                'purchaser_patient_id' => $purchaserPatientId,
                'recipient_patient_id' => $recipientPatientId,
                'sold_by_staff_id' => auth()->id(),
                'sold_price_minor' => $soldPriceMinor,
                'template_discount_minor' => $templateDiscountMinor,
                'extra_discount_minor' => $extraDiscountMinor,
                'total_discount_minor' => $totalDiscountMinor,
                'status' => GiftCard::STATUS_ACTIVE,
                'activated_at' => now(),
                'notes' => $notes,
            ]);

            // Create GL entry with discount
            $journalEntry = $this->glService->postGiftCardSale($card, $journalId, $totalDiscountMinor);

            if ($journalEntry) {
                $card->update(['sale_journal_entry_id' => $journalEntry->id]);
            }

            // Record transaction
            GiftCardTransaction::create([
                'tenant_id' => $card->tenant_id,
                'gift_card_id' => $card->id,
                'type' => GiftCardTransaction::TYPE_ACTIVATE,
                'amount_minor' => $card->initial_value_minor,
                'running_balance_minor' => $card->initial_value_minor,
                'journal_entry_id' => $journalEntry?->id,
                'notes' => $totalDiscountMinor > 0
                    ? "Card sold and activated (Discount: " . format_money($totalDiscountMinor) . ")"
                    : 'Card sold and activated',
                'created_by_user_id' => auth()->id(),
            ]);
        });

        Log::info("Gift card sold: {$card->code}", [
            'card_id' => $card->id,
            'journal_id' => $journalId,
            'purchaser_patient_id' => $purchaserPatientId,
            'amount' => $card->initial_value_minor,
        ]);

        return [
            'success' => true,
            'card' => $card->fresh(),
        ];
    }

    /**
     * Redeem gift card with GL entry
     */
    public function redeem(
        GiftCard $card,
        int $amountMinor,
        ?Invoice $invoice = null,
        ?Payment $payment = null
    ): array {
        $journalEntry = null;
        $result = ['success' => false, 'error' => 'Unknown error'];

        DB::transaction(function () use ($card, $amountMinor, $invoice, $payment, &$journalEntry, &$result) {
            // SECURITY: Lock the card row to prevent race conditions
            $card = GiftCard::lockForUpdate()->find($card->id);

            if (!$card || !$card->canRedeem()) {
                $result = ['success' => false, 'error' => 'Card cannot be redeemed'];
                return;
            }

            $amountToRedeem = min($amountMinor, $card->remaining_value_minor);

            if ($amountToRedeem <= 0) {
                $result = ['success' => false, 'error' => 'No balance available to redeem'];
                return;
            }

            // Update balance
            $newBalance = $card->remaining_value_minor - $amountToRedeem;
            $newStatus = $newBalance > 0
                ? GiftCard::STATUS_PARTIALLY_USED
                : GiftCard::STATUS_FULLY_USED;

            $card->update([
                'remaining_value_minor' => $newBalance,
                'status' => $newStatus,
            ]);

            // Create GL entry
            $journalEntry = $this->glService->postGiftCardRedemption(
                $card,
                $amountToRedeem,
                $invoice,
                $payment
            );

            // Record transaction
            GiftCardTransaction::create([
                'tenant_id' => $card->tenant_id,
                'gift_card_id' => $card->id,
                'type' => GiftCardTransaction::TYPE_REDEEM,
                'amount_minor' => -$amountToRedeem,
                'running_balance_minor' => $newBalance,
                'invoice_id' => $invoice?->id,
                'payment_id' => $payment?->id,
                'journal_entry_id' => $journalEntry?->id,
                'notes' => $invoice ? "Redemption for invoice {$invoice->code}" : 'Redemption',
                'created_by_user_id' => auth()->id(),
            ]);

            Log::info("Gift card redeemed: {$card->code}", [
                'card_id' => $card->id,
                'amount' => $amountToRedeem,
                'invoice_id' => $invoice?->id,
            ]);

            $result = [
                'success' => true,
                'redeemed_amount' => $amountToRedeem,
                'remaining_balance' => $newBalance,
            ];
        });

        return $result;
    }

    /**
     * Expire card with GL entry for breakage
     */
    public function expireWithGL(GiftCard $card): array
    {
        if (!$card->canTransitionTo(GiftCard::STATUS_EXPIRED)) {
            return ['success' => false, 'error' => 'Card cannot be expired'];
        }

        $breakageAmount = $card->remaining_value_minor;
        $journalEntry = null;

        DB::transaction(function () use ($card, $breakageAmount, &$journalEntry) {
            // Create GL entry for breakage before expiring
            if ($breakageAmount > 0) {
                $journalEntry = $this->glService->postGiftCardExpiration($card);
            }

            // Record transaction
            if ($breakageAmount > 0) {
                GiftCardTransaction::create([
                    'tenant_id' => $card->tenant_id,
                    'gift_card_id' => $card->id,
                    'type' => GiftCardTransaction::TYPE_EXPIRE,
                    'amount_minor' => -$breakageAmount,
                    'running_balance_minor' => 0,
                    'journal_entry_id' => $journalEntry?->id,
                    'notes' => 'Card expired - breakage revenue recognized',
                    'created_by_user_id' => auth()->id(),
                ]);
            }

            $card->update([
                'status' => GiftCard::STATUS_EXPIRED,
                'remaining_value_minor' => 0,
            ]);
        });

        return [
            'success' => true,
            'breakage_amount' => $breakageAmount,
        ];
    }

    /**
     * Find card by serial number (using hash lookup)
     */
    public function findBySerial(string $serial): ?GiftCard
    {
        $cleanSerial = $this->generator->parseCardCode($serial);
        $hash = $this->generator->hashCode($cleanSerial);

        return GiftCard::where('code_hash', $hash)->first();
    }

    /**
     * Find card by formatted code
     */
    public function findByCode(string $code): ?GiftCard
    {
        return GiftCard::where('code', $code)->first()
            ?? $this->findBySerial($code);
    }

    /**
     * Validate card for payment
     */
    public function validateForPayment(string $code): array
    {
        $card = $this->findByCode($code);

        if (!$card) {
            return [
                'valid' => false,
                'error' => 'Gift card not found',
            ];
        }

        if (!$card->canRedeem()) {
            $reason = match ($card->status) {
                GiftCard::STATUS_DRAFT => 'Card has not been activated',
                GiftCard::STATUS_FULLY_USED => 'Card has no remaining balance',
                GiftCard::STATUS_EXPIRED => 'Card has expired',
                GiftCard::STATUS_CANCELLED => 'Card has been cancelled',
                default => 'Card cannot be redeemed',
            };

            return [
                'valid' => false,
                'error' => $reason,
                'card' => $card,
            ];
        }

        return [
            'valid' => true,
            'card' => $card,
            'available_balance' => $card->remaining_value_minor,
            'formatted_balance' => $card->formatted_remaining_value,
        ];
    }

    /**
     * Get staff statistics
     */
    public function getStaffStatistics(string $staffId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = GiftCard::where('assigned_to_staff_id', $staffId);

        if ($from) {
            $query->where('assigned_at', '>=', $from);
        }
        if ($to) {
            $query->where('assigned_at', '<=', $to);
        }

        $assigned = $query->count();
        $sold = (clone $query)->whereNotNull('sold_by_staff_id')
            ->where('sold_by_staff_id', $staffId)
            ->count();
        $available = (clone $query)->where('status', GiftCard::STATUS_DRAFT)->count();
        $totalSalesValue = (clone $query)
            ->whereNotNull('sold_by_staff_id')
            ->where('sold_by_staff_id', $staffId)
            ->sum('initial_value_minor');

        return [
            'assigned' => $assigned,
            'sold' => $sold,
            'available' => $available,
            'total_sales_value' => $totalSalesValue,
            'formatted_sales_value' => format_money($totalSalesValue),
        ];
    }

    /**
     * Get available cards for staff by denomination
     */
    public function getAvailableCardsForStaff(string $staffId): Collection
    {
        return GiftCard::where('assigned_to_staff_id', $staffId)
            ->where('status', GiftCard::STATUS_DRAFT)
            ->with('template')
            ->get()
            ->groupBy('initial_value_minor')
            ->map(function ($cards, $value) {
                return [
                    'value_minor' => $value,
                    'formatted_value' => format_money($value),
                    'count' => $cards->count(),
                    'cards' => $cards,
                ];
            })
            ->values();
    }
}
