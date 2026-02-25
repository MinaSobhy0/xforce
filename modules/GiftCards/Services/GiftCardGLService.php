<?php

namespace Modules\GiftCards\Services;

use Modules\GiftCards\Models\GiftCard;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\AccountingIntegrationService;
use Modules\Accounting\Services\DefaultAccountsService;
use Illuminate\Support\Facades\Log;

class GiftCardGLService
{
    protected AccountingIntegrationService $accountingService;
    protected DefaultAccountsService $defaultAccounts;

    public function __construct(AccountingIntegrationService $accountingService)
    {
        $this->accountingService = $accountingService;
        $this->defaultAccounts = new DefaultAccountsService();
    }

    /**
     * Post gift card sale journal entry
     * DR: Cash/Bank (payment method)
     * CR: Gift Card Liability
     */
    public function postGiftCardSale(
        GiftCard $card,
        Payment $payment,
        ?int $discountMinor = null
    ): ?JournalEntry {
        $template = $card->template;

        // Get accounts - template specific or system defaults
        $liabilityAccount = $template?->liabilityAccount
            ?? $this->defaultAccounts->getGiftCardLiabilityAccount();
        $cashAccount = $this->defaultAccounts->getCashAccount();

        if (!$liabilityAccount || !$cashAccount) {
            Log::warning('Gift card GL accounts not configured', [
                'card_id' => $card->id,
                'has_liability' => (bool) $liabilityAccount,
                'has_cash' => (bool) $cashAccount,
            ]);
            return null;
        }

        $lines = [
            [
                'account_code' => $cashAccount->code,
                'debit' => $payment->amount_minor,
                'credit' => 0,
                'description' => "Gift card sale: {$card->code}",
            ],
            [
                'account_code' => $liabilityAccount->code,
                'debit' => 0,
                'credit' => $card->initial_value_minor,
                'description' => "Gift card liability: {$card->code}",
            ],
        ];

        // Handle discount if sold below face value
        if ($discountMinor && $discountMinor > 0) {
            $expenseAccount = $template?->expenseAccount
                ?? $this->defaultAccounts->getDiscountAccount();

            if ($expenseAccount) {
                $lines[] = [
                    'account_code' => $expenseAccount->code,
                    'debit' => $discountMinor,
                    'credit' => 0,
                    'description' => "Gift card discount: {$card->code}",
                ];
            }
        }

        return $this->accountingService->createJournalEntry(
            now(),
            "Gift card sale: {$card->code}",
            $lines,
            GiftCard::class,
            $card->id,
            true
        );
    }

    /**
     * Post gift card redemption journal entry
     * DR: Gift Card Liability
     * CR: Service Revenue
     */
    public function postGiftCardRedemption(
        GiftCard $card,
        int $amountMinor,
        ?Invoice $invoice = null
    ): ?JournalEntry {
        $template = $card->template;

        $liabilityAccount = $template?->liabilityAccount
            ?? $this->defaultAccounts->getGiftCardLiabilityAccount();
        $revenueAccount = $this->defaultAccounts->getServiceRevenueAccount();

        if (!$liabilityAccount || !$revenueAccount) {
            Log::warning('Gift card redemption GL accounts not configured', [
                'card_id' => $card->id,
            ]);
            return null;
        }

        $lines = [
            [
                'account_code' => $liabilityAccount->code,
                'debit' => $amountMinor,
                'credit' => 0,
                'description' => "Redemption: {$card->code}",
            ],
            [
                'account_code' => $revenueAccount->code,
                'debit' => 0,
                'credit' => $amountMinor,
                'description' => $invoice
                    ? "Invoice {$invoice->code}"
                    : "Gift card redemption",
            ],
        ];

        return $this->accountingService->createJournalEntry(
            now(),
            "Gift card redemption: {$card->code}" . ($invoice ? " - Invoice {$invoice->code}" : ''),
            $lines,
            GiftCard::class,
            $card->id,
            true
        );
    }

    /**
     * Post gift card expiration journal entry (breakage revenue)
     * DR: Gift Card Liability
     * CR: Breakage Revenue
     */
    public function postGiftCardExpiration(GiftCard $card): ?JournalEntry
    {
        if ($card->remaining_value_minor <= 0) {
            return null;
        }

        $template = $card->template;

        $liabilityAccount = $template?->liabilityAccount
            ?? $this->defaultAccounts->getGiftCardLiabilityAccount();
        $breakageAccount = $this->defaultAccounts->getGiftCardBreakageAccount();

        if (!$liabilityAccount || !$breakageAccount) {
            Log::warning('Gift card expiration GL accounts not configured', [
                'card_id' => $card->id,
            ]);
            return null;
        }

        $lines = [
            [
                'account_code' => $liabilityAccount->code,
                'debit' => $card->remaining_value_minor,
                'credit' => 0,
                'description' => "Expiration: {$card->code}",
            ],
            [
                'account_code' => $breakageAccount->code,
                'debit' => 0,
                'credit' => $card->remaining_value_minor,
                'description' => "Breakage revenue: {$card->code}",
            ],
        ];

        return $this->accountingService->createJournalEntry(
            now(),
            "Gift card expired: {$card->code} - Breakage revenue",
            $lines,
            GiftCard::class,
            $card->id,
            true
        );
    }

    /**
     * Post gift card cancellation journal entry (reverse the liability)
     * DR: Gift Card Liability
     * CR: Cash/Bank (refund to customer)
     */
    public function postGiftCardCancellation(
        GiftCard $card,
        ?Payment $refundPayment = null
    ): ?JournalEntry {
        if ($card->remaining_value_minor <= 0) {
            return null;
        }

        $template = $card->template;

        $liabilityAccount = $template?->liabilityAccount
            ?? $this->defaultAccounts->getGiftCardLiabilityAccount();
        $cashAccount = $this->defaultAccounts->getCashAccount();

        if (!$liabilityAccount || !$cashAccount) {
            return null;
        }

        $lines = [
            [
                'account_code' => $liabilityAccount->code,
                'debit' => $card->remaining_value_minor,
                'credit' => 0,
                'description' => "Cancellation: {$card->code}",
            ],
            [
                'account_code' => $cashAccount->code,
                'debit' => 0,
                'credit' => $card->remaining_value_minor,
                'description' => "Refund for cancelled gift card: {$card->code}",
            ],
        ];

        return $this->accountingService->createJournalEntry(
            now(),
            "Gift card cancelled: {$card->code}",
            $lines,
            GiftCard::class,
            $card->id,
            true
        );
    }
}
