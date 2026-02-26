<?php

namespace Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;

class JournalSeeder extends Seeder
{
    /**
     * Resolve the tenant ID from various sources.
     */
    protected function resolveTenantId(): ?string
    {
        // Try TenantManager first
        try {
            $tenantManager = app(\XLinic\Framework\Core\Tenancy\TenantManager::class);
            if ($tenantManager->current()) {
                return $tenantManager->current()->id;
            }
        } catch (\Exception $e) {
            // Ignore
        }

        // Try app('currentTenant')
        try {
            if ($tenant = app('currentTenant')) {
                return $tenant->id;
            }
        } catch (\Exception $e) {
            // Ignore
        }

        // Try to resolve from database search_path (for CLI seeding)
        foreach (['pgsql', 'tenant'] as $conn) {
            try {
                $result = \DB::connection($conn)->select('SHOW search_path');
                $searchPath = $result[0]->search_path ?? 'public';

                if (preg_match('/tenant[_-]([^,\s"]+)/', $searchPath, $matches)) {
                    $slug = str_replace('_', '-', $matches[1]);

                    $tenant = \DB::connection('pgsql')
                        ->table('tenants')
                        ->where('slug', $slug)
                        ->orWhere('slug', $matches[1])
                        ->first();

                    if ($tenant) {
                        return $tenant->id;
                    }
                }
            } catch (\Exception $e) {
                // Ignore errors
            }
        }

        return null;
    }

    /**
     * Get account ID by code.
     */
    protected function getAccountId(string $code, ?string $tenantId): ?int
    {
        $account = ChartOfAccount::where('code', $code)
            ->where('tenant_id', $tenantId)
            ->first();

        return $account?->id;
    }

    public function run(): void
    {
        $tenantId = $this->resolveTenantId();

        // Default journals with their default accounts
        $journals = [
            [
                'code' => 'SAL',
                'name' => ['en' => 'Sales Journal', 'ar' => 'يومية المبيعات'],
                'type' => Journal::TYPE_SALES,
                'sequence_prefix' => 'SAL',
                'default_debit_account' => '1110', // Patient Receivables
                'default_credit_account' => '4110', // Treatment Revenue
            ],
            [
                'code' => 'PUR',
                'name' => ['en' => 'Purchase Journal', 'ar' => 'يومية المشتريات'],
                'type' => Journal::TYPE_PURCHASE,
                'sequence_prefix' => 'PUR',
                'default_debit_account' => '1211', // Medical Supplies
                'default_credit_account' => '2010', // Supplier Payables
            ],
            [
                'code' => 'CSH',
                'name' => ['en' => 'Cash Journal', 'ar' => 'يومية النقدية'],
                'type' => Journal::TYPE_CASH,
                'sequence_prefix' => 'CSH',
                'default_debit_account' => '1010', // Cash on Hand
                'default_credit_account' => '1110', // Patient Receivables
            ],
            [
                'code' => 'BNK',
                'name' => ['en' => 'Bank Journal', 'ar' => 'يومية البنك'],
                'type' => Journal::TYPE_BANK,
                'sequence_prefix' => 'BNK',
                'default_debit_account' => '1030', // Bank Account
                'default_credit_account' => '1110', // Patient Receivables
            ],
            [
                'code' => 'GEN',
                'name' => ['en' => 'General Journal', 'ar' => 'اليومية العامة'],
                'type' => Journal::TYPE_GENERAL,
                'sequence_prefix' => 'GEN',
                'default_debit_account' => null,
                'default_credit_account' => null,
            ],
            [
                'code' => 'GC',
                'name' => ['en' => 'Gift Card Journal', 'ar' => 'يومية بطاقات الهدايا'],
                'type' => Journal::TYPE_GIFT_CARD,
                'sequence_prefix' => 'GC',
                'default_debit_account' => '2220', // Gift Card Liability
                'default_credit_account' => '1110', // Patient Receivables
            ],
        ];

        foreach ($journals as $journal) {
            $existing = Journal::where('code', $journal['code'])
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$existing) {
                Journal::create([
                    'tenant_id' => $tenantId,
                    'code' => $journal['code'],
                    'name' => $journal['name'],
                    'type' => $journal['type'],
                    'sequence_prefix' => $journal['sequence_prefix'],
                    'default_debit_account_id' => $journal['default_debit_account']
                        ? $this->getAccountId($journal['default_debit_account'], $tenantId)
                        : null,
                    'default_credit_account_id' => $journal['default_credit_account']
                        ? $this->getAccountId($journal['default_credit_account'], $tenantId)
                        : null,
                    'is_active' => true,
                    'next_sequence' => 1,
                ]);
            }
        }
    }
}
