<?php

namespace Modules\Assets\Services;

use Modules\Assets\Models\Asset;
use Modules\Assets\Models\AssetType;
use Modules\Assets\Models\AssetDepreciationEntry;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\AccountingIntegrationService;
use Modules\Accounting\Services\DefaultAccountsService;
use Illuminate\Support\Facades\Log;

class AssetGLService
{
    protected AccountingIntegrationService $accountingService;
    protected DefaultAccountsService $defaultAccounts;

    public function __construct(AccountingIntegrationService $accountingService)
    {
        $this->accountingService = $accountingService;
        $this->defaultAccounts = new DefaultAccountsService();
    }

    /**
     * Post asset acquisition journal entry
     * DR: Fixed Asset Account (from asset type)     [acquisition_cost]
     * CR: Accounts Payable / Cash                   [acquisition_cost]
     */
    public function postAssetAcquisition(Asset $asset): ?JournalEntry
    {
        $type = $asset->assetType;

        if (!$type || !$type->fixed_asset_account_id) {
            Log::warning('Asset acquisition GL accounts not configured', [
                'asset_id' => $asset->id,
                'asset_type_id' => $type?->id,
            ]);
            return null;
        }

        $fixedAssetAccount = $type->fixedAssetAccount;
        $payableAccount = $this->defaultAccounts->getSupplierPayableAccount();

        if (!$fixedAssetAccount || !$payableAccount) {
            Log::warning('Asset acquisition GL accounts not found', [
                'asset_id' => $asset->id,
                'has_fixed_asset' => (bool) $fixedAssetAccount,
                'has_payable' => (bool) $payableAccount,
            ]);
            return null;
        }

        $lines = [
            [
                'account_code' => $fixedAssetAccount->code,
                'debit' => $asset->acquisition_cost_minor,
                'credit' => 0,
                'description' => "Asset acquisition: {$asset->code} - {$asset->name}",
            ],
            [
                'account_code' => $payableAccount->code,
                'debit' => 0,
                'credit' => $asset->acquisition_cost_minor,
                'description' => "Asset acquisition: {$asset->code}",
            ],
        ];

        return $this->accountingService->createJournalEntry(
            $asset->acquisition_date ?? now(),
            "Asset acquisition: {$asset->code} - {$asset->name}",
            $lines,
            Asset::class,
            $asset->id,
            true,
            $asset->tenant_id
        );
    }

    /**
     * Post depreciation journal entry
     * DR: Depreciation Expense Account              [depreciation_amount]
     * CR: Accumulated Depreciation Account          [depreciation_amount]
     */
    public function postDepreciationEntry(
        Asset $asset,
        AssetDepreciationEntry $entry
    ): ?JournalEntry {
        $type = $asset->assetType;

        if (!$type) {
            Log::warning('Asset depreciation: no asset type', [
                'asset_id' => $asset->id,
            ]);
            return null;
        }

        $expenseAccount = $type->depreciationExpenseAccount;
        $accumulatedAccount = $type->accumulatedDepreciationAccount;

        if (!$expenseAccount || !$accumulatedAccount) {
            Log::warning('Asset depreciation GL accounts not configured', [
                'asset_id' => $asset->id,
                'has_expense' => (bool) $expenseAccount,
                'has_accumulated' => (bool) $accumulatedAccount,
            ]);
            return null;
        }

        $lines = [
            [
                'account_code' => $expenseAccount->code,
                'debit' => $entry->depreciation_amount_minor,
                'credit' => 0,
                'description' => "Depreciation: {$asset->code} - {$entry->period_label}",
            ],
            [
                'account_code' => $accumulatedAccount->code,
                'debit' => 0,
                'credit' => $entry->depreciation_amount_minor,
                'description' => "Accumulated depreciation: {$asset->code}",
            ],
        ];

        return $this->accountingService->createJournalEntry(
            $entry->period_end,
            "Depreciation: {$asset->code} - {$entry->period_label}",
            $lines,
            AssetDepreciationEntry::class,
            $entry->id,
            true,
            $asset->tenant_id
        );
    }

    /**
     * Post asset disposal journal entry
     * DR: Accumulated Depreciation (remove)         [total_accumulated]
     * DR: Cash/Receivable (if proceeds)             [proceeds]
     * DR/CR: Gain/Loss on Disposal                  [difference]
     * CR: Fixed Asset Account (remove cost)         [acquisition_cost]
     */
    public function postAssetDisposal(
        Asset $asset,
        int $proceedsMinor = 0
    ): ?JournalEntry {
        $type = $asset->assetType;

        if (!$type) {
            Log::warning('Asset disposal: no asset type', [
                'asset_id' => $asset->id,
            ]);
            return null;
        }

        $fixedAssetAccount = $type->fixedAssetAccount;
        $accumulatedAccount = $type->accumulatedDepreciationAccount;
        $gainLossAccount = $type->gainLossAccount;
        $cashAccount = $this->defaultAccounts->getCashAccount();

        if (!$fixedAssetAccount || !$accumulatedAccount) {
            Log::warning('Asset disposal GL accounts not configured', [
                'asset_id' => $asset->id,
            ]);
            return null;
        }

        $lines = [];

        // DR: Accumulated Depreciation (remove accumulated amount)
        if ($asset->accumulated_depreciation_minor > 0 && $accumulatedAccount) {
            $lines[] = [
                'account_code' => $accumulatedAccount->code,
                'debit' => $asset->accumulated_depreciation_minor,
                'credit' => 0,
                'description' => "Remove accumulated depreciation: {$asset->code}",
            ];
        }

        // DR: Cash/Receivable (if there are proceeds)
        if ($proceedsMinor > 0 && $cashAccount) {
            $lines[] = [
                'account_code' => $cashAccount->code,
                'debit' => $proceedsMinor,
                'credit' => 0,
                'description' => "Asset disposal proceeds: {$asset->code}",
            ];
        }

        // Calculate gain/loss
        $gainLoss = $asset->calculateDisposalGainLoss($proceedsMinor);

        // DR/CR: Gain/Loss on Disposal
        if ($gainLoss !== 0 && $gainLossAccount) {
            if ($gainLoss > 0) {
                // Gain - Credit
                $lines[] = [
                    'account_code' => $gainLossAccount->code,
                    'debit' => 0,
                    'credit' => $gainLoss,
                    'description' => "Gain on disposal: {$asset->code}",
                ];
            } else {
                // Loss - Debit
                $lines[] = [
                    'account_code' => $gainLossAccount->code,
                    'debit' => abs($gainLoss),
                    'credit' => 0,
                    'description' => "Loss on disposal: {$asset->code}",
                ];
            }
        }

        // CR: Fixed Asset Account (remove original cost)
        $lines[] = [
            'account_code' => $fixedAssetAccount->code,
            'debit' => 0,
            'credit' => $asset->acquisition_cost_minor,
            'description' => "Remove asset cost: {$asset->code}",
        ];

        return $this->accountingService->createJournalEntry(
            $asset->disposal_date ?? now(),
            "Asset disposal: {$asset->code} - {$asset->name}",
            $lines,
            Asset::class,
            $asset->id,
            true,
            $asset->tenant_id
        );
    }

    /**
     * Reverse a depreciation entry
     */
    public function reverseDepreciationEntry(
        Asset $asset,
        AssetDepreciationEntry $entry
    ): ?JournalEntry {
        if (!$entry->journal_entry_id) {
            return null;
        }

        $type = $asset->assetType;
        $expenseAccount = $type?->depreciationExpenseAccount;
        $accumulatedAccount = $type?->accumulatedDepreciationAccount;

        if (!$expenseAccount || !$accumulatedAccount) {
            return null;
        }

        // Reverse the entry (swap debits and credits)
        $lines = [
            [
                'account_code' => $accumulatedAccount->code,
                'debit' => $entry->depreciation_amount_minor,
                'credit' => 0,
                'description' => "Reverse depreciation: {$asset->code} - {$entry->period_label}",
            ],
            [
                'account_code' => $expenseAccount->code,
                'debit' => 0,
                'credit' => $entry->depreciation_amount_minor,
                'description' => "Reverse depreciation expense: {$asset->code}",
            ],
        ];

        return $this->accountingService->createJournalEntry(
            now(),
            "Reverse depreciation: {$asset->code} - {$entry->period_label}",
            $lines,
            AssetDepreciationEntry::class,
            $entry->id,
            true,
            $asset->tenant_id
        );
    }
}
