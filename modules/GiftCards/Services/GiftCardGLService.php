<?php

namespace Modules\GiftCards\Services;

use Modules\GiftCards\Models\GiftCard;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Accounting\Models\Journal;
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
     * DR: Cash/Bank (payment method from journal)
     * CR: Gift Card Liability
     */
    public function postGiftCardSale(
        GiftCard $card,
        string $journalId
    ): ?JournalEntry {
        $template = $card->template;
        $journal = Journal::find($journalId);

        if (!$journal || !$journal->default_debit_account_id) {
            Log::warning('Gift card sale journal not configured', [
                'card_id' => $card->id,
                'journal_id' => $journalId,
            ]);
            return null;
        }

        // Get accounts - template specific or system defaults
        $liabilityAccount = $template?->liabilityAccount
            ?? $this->defaultAccounts->getGiftCardLiabilityAccount();
        $paymentAccount = $journal->defaultDebitAccount;

        if (!$liabilityAccount || !$paymentAccount) {
            Log::warning('Gift card GL accounts not configured', [
                'card_id' => $card->id,
                'has_liability' => (bool) $liabilityAccount,
                'has_payment_account' => (bool) $paymentAccount,
            ]);
            return null;
        }

        $lines = [
            [
                'account_code' => $paymentAccount->code,
                'debit' => $card->initial_value_minor,
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
     *
     * For invoice payments:
     *   DR: Gift Card Liability
     *   CR: Accounts Receivable (reduces patient balance)
     *
     * For direct redemption (no invoice):
     *   DR: Gift Card Liability
     *   CR: Service Revenue
     */
    public function postGiftCardRedemption(
        GiftCard $card,
        int $amountMinor,
        ?Invoice $invoice = null
    ): ?JournalEntry {
        $template = $card->template;

        $liabilityAccount = $template?->liabilityAccount
            ?? $this->defaultAccounts->getGiftCardLiabilityAccount();

        // For invoice payments, credit AR; for direct redemption, credit revenue
        if ($invoice) {
            $creditAccount = $this->defaultAccounts->getPatientReceivableAccount();
        } else {
            $creditAccount = $this->defaultAccounts->getServiceRevenueAccount();
        }

        if (!$liabilityAccount || !$creditAccount) {
            Log::warning('Gift card redemption GL accounts not configured', [
                'card_id' => $card->id,
                'has_liability' => (bool) $liabilityAccount,
                'has_credit_account' => (bool) $creditAccount,
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
                'account_code' => $creditAccount->code,
                'debit' => 0,
                'credit' => $amountMinor,
                'description' => $invoice
                    ? "Payment for Invoice {$invoice->code}"
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
