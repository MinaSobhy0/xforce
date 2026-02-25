<?php

namespace Modules\GiftCards\Console\Commands;

use Illuminate\Console\Command;
use Modules\GiftCards\Models\GiftCard;
use Modules\GiftCards\Services\GiftCardGLService;
use Modules\Core\Models\Tenant;
use Illuminate\Support\Facades\Log;

class ExpireGiftCardsCommand extends Command
{
    protected $signature = 'giftcards:expire
                            {--tenant= : Specific tenant slug to process}
                            {--dry-run : Show what would be expired without making changes}';

    protected $description = 'Expire gift cards that have passed their expiration date and post GL entries';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $tenantSlug = $this->option('tenant');

        if ($dryRun) {
            $this->info('DRY RUN - No changes will be made');
        }

        $tenants = $tenantSlug
            ? Tenant::where('slug', $tenantSlug)->get()
            : Tenant::all();

        $totalExpired = 0;
        $totalBreakage = 0;

        foreach ($tenants as $tenant) {
            $this->info("Processing tenant: {$tenant->name}");

            try {
                $tenant->run(function () use ($tenant, $dryRun, &$totalExpired, &$totalBreakage) {
                    $result = $this->processExpiredCards($tenant, $dryRun);
                    $totalExpired += $result['count'];
                    $totalBreakage += $result['breakage'];
                });
            } catch (\Exception $e) {
                $this->error("Error processing tenant {$tenant->name}: {$e->getMessage()}");
                Log::error('Gift card expiration error', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->info("  - Cards expired: {$totalExpired}");
        $this->info("  - Total breakage value: " . number_format($totalBreakage / 100, 2));

        return self::SUCCESS;
    }

    protected function processExpiredCards(Tenant $tenant, bool $dryRun): array
    {
        $glService = app(GiftCardGLService::class);

        // Find cards that should expire
        $expiredCards = GiftCard::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereIn('status', [
                GiftCard::STATUS_ACTIVE,
                GiftCard::STATUS_PARTIALLY_USED,
            ])
            ->where('remaining_value_minor', '>', 0)
            ->get();

        $count = 0;
        $breakageValue = 0;

        foreach ($expiredCards as $card) {
            $this->line("  - Expiring card: {$card->code} (Balance: " . number_format($card->remaining_value_minor / 100, 2) . ")");

            if (!$dryRun) {
                $breakageValue += $card->remaining_value_minor;

                // Post GL entry for breakage
                $journalEntry = $glService->postGiftCardExpiration($card);

                // Update card status
                $card->update([
                    'status' => GiftCard::STATUS_EXPIRED,
                ]);

                // Record transaction
                $card->transactions()->create([
                    'tenant_id' => $card->tenant_id,
                    'type' => 'expire',
                    'amount_minor' => $card->remaining_value_minor,
                    'balance_after_minor' => 0,
                    'description' => 'Card expired',
                    'journal_entry_id' => $journalEntry?->id,
                    'created_by_user_id' => null,
                ]);

                // Set remaining balance to 0 (breakage recognized)
                $card->update([
                    'remaining_value_minor' => 0,
                ]);

                $count++;
            } else {
                $breakageValue += $card->remaining_value_minor;
                $count++;
            }
        }

        if ($count > 0) {
            $this->info("  Expired {$count} cards with breakage value: " . number_format($breakageValue / 100, 2));
        } else {
            $this->line("  No cards to expire");
        }

        return [
            'count' => $count,
            'breakage' => $breakageValue,
        ];
    }
}
