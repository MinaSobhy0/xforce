<?php

use App\Services\BranchContext;
use Illuminate\Support\Collection;
use Modules\Core\Models\Branch;

if (! function_exists('current_tenant_id')) {
    /**
     * Get the current tenant ID.
     */
    function current_tenant_id(): ?string
    {
        if (app()->has('currentTenant')) {
            return app('currentTenant')?->id;
        }

        return null;
    }
}

if (! function_exists('current_tenant')) {
    /**
     * Get the current tenant.
     */
    function current_tenant(): ?\Modules\Core\Models\Tenant
    {
        if (app()->has('currentTenant')) {
            return app('currentTenant');
        }

        return null;
    }
}

if (! function_exists('current_branches')) {
    /**
     * Get the current selected branches.
     */
    function current_branches(): Collection
    {
        return BranchContext::current();
    }
}

if (! function_exists('current_branch')) {
    /**
     * Get the first/primary current branch.
     */
    function current_branch(): ?Branch
    {
        return BranchContext::first();
    }
}

if (! function_exists('current_branch_ids')) {
    /**
     * Get the current branch IDs as array.
     */
    function current_branch_ids(): array
    {
        return BranchContext::currentIds();
    }
}

if (! function_exists('current_branch_id')) {
    /**
     * Get the first current branch ID.
     */
    function current_branch_id(): ?string
    {
        return BranchContext::currentId();
    }
}

if (! function_exists('is_all_branches')) {
    /**
     * Check if viewing all branches.
     */
    function is_all_branches(): bool
    {
        return BranchContext::isAllBranches();
    }
}

if (! function_exists('current_currency')) {
    /**
     * Get the current branch's currency code.
     * Falls back to app default currency if no branch selected.
     */
    function current_currency(): string
    {
        $branch = current_branch();

        if ($branch && $branch->currency_code) {
            return $branch->currency_code;
        }

        // Fall back to app default currency
        return config('xlinic.tenant.currency', 'EGP');
    }
}

if (! function_exists('currency_minor_divisor')) {
    /**
     * How many minor units make one major unit for the given currency.
     * Most currencies are 2-decimal (100 cents per dollar), Gulf
     * currencies are 3-decimal (1000 fils per dinar), JPY-like are 0.
     *
     * Use this everywhere a `_minor` integer is converted to/from the
     * displayed major value (form field state, accessor conversions,
     * report aggregations) so the same column works correctly across
     * tenants with different currencies.
     */
    function currency_minor_divisor(?string $currency = null): int
    {
        $currency = strtoupper($currency ?? current_currency());

        return match ($currency) {
            // Gulf / Middle East 3-decimal currencies
            'KWD', 'BHD', 'OMR', 'JOD', 'LYD', 'TND', 'IQD' => 1000,
            // No-decimal currencies
            'JPY', 'KRW', 'VND', 'CLP', 'ISK' => 1,
            default => 100,
        };
    }
}

if (! function_exists('format_money')) {
    /**
     * Format an amount as money using the current branch currency.
     *
     * @param  int  $amountMinor  Amount in minor units (cents/piasters)
     * @param  string|null  $currency  Override currency code
     */
    function format_money(int $amountMinor, ?string $currency = null): string
    {
        $currency = $currency ?? current_currency();

        return \Illuminate\Support\Str::money($amountMinor, $currency);
    }
}
