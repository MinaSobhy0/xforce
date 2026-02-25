<?php

namespace Modules\Accounting\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends BaseModel
{
    use HasTenancy;
    use HasTranslations;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'parent_id',
        'type',
        'is_system',
        'balance_minor',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'balance_minor' => 'integer',
    ];

    public array $translatable = ['name', 'description'];

    // Account type constants (Odoo-compatible)
    // Assets
    public const TYPE_RECEIVABLE = 'receivable';
    public const TYPE_BANK_CASH = 'bank_cash';
    public const TYPE_CURRENT_ASSET = 'current_asset';
    public const TYPE_NON_CURRENT_ASSET = 'non_current_asset';
    public const TYPE_FIXED_ASSET = 'fixed_asset';
    public const TYPE_PREPAYMENTS = 'prepayments';
    // Liabilities
    public const TYPE_PAYABLE = 'payable';
    public const TYPE_CURRENT_LIABILITY = 'current_liability';
    public const TYPE_NON_CURRENT_LIABILITY = 'non_current_liability';
    public const TYPE_CREDIT_CARD = 'credit_card';
    // Equity
    public const TYPE_EQUITY = 'equity';
    public const TYPE_CURRENT_YEAR_EARNINGS = 'current_year_earnings';
    // Revenue
    public const TYPE_INCOME = 'income';
    public const TYPE_OTHER_INCOME = 'other_income';
    // Expenses
    public const TYPE_EXPENSE = 'expense';
    public const TYPE_DEPRECIATION = 'depreciation';
    public const TYPE_COST_OF_REVENUE = 'cost_of_revenue';

    // All account types grouped by category (for grouped selects)
    public const TYPES = [
        'Assets' => [
            self::TYPE_RECEIVABLE => 'Receivable',
            self::TYPE_BANK_CASH => 'Bank and Cash',
            self::TYPE_CURRENT_ASSET => 'Current Assets',
            self::TYPE_NON_CURRENT_ASSET => 'Non-current Assets',
            self::TYPE_FIXED_ASSET => 'Fixed Assets',
            self::TYPE_PREPAYMENTS => 'Prepayments',
        ],
        'Liabilities' => [
            self::TYPE_PAYABLE => 'Payable',
            self::TYPE_CREDIT_CARD => 'Credit Card',
            self::TYPE_CURRENT_LIABILITY => 'Current Liabilities',
            self::TYPE_NON_CURRENT_LIABILITY => 'Non-current Liabilities',
        ],
        'Equity' => [
            self::TYPE_EQUITY => 'Equity',
            self::TYPE_CURRENT_YEAR_EARNINGS => 'Current Year Earnings',
        ],
        'Income' => [
            self::TYPE_INCOME => 'Income',
            self::TYPE_OTHER_INCOME => 'Other Income',
        ],
        'Expenses' => [
            self::TYPE_EXPENSE => 'Expenses',
            self::TYPE_DEPRECIATION => 'Depreciation',
            self::TYPE_COST_OF_REVENUE => 'Cost of Revenue',
        ],
    ];

    // Flat list of all types for dropdowns
    public const TYPES_FLAT = [
        self::TYPE_RECEIVABLE => 'Receivable',
        self::TYPE_BANK_CASH => 'Bank and Cash',
        self::TYPE_CURRENT_ASSET => 'Current Assets',
        self::TYPE_NON_CURRENT_ASSET => 'Non-current Assets',
        self::TYPE_FIXED_ASSET => 'Fixed Assets',
        self::TYPE_PREPAYMENTS => 'Prepayments',
        self::TYPE_PAYABLE => 'Payable',
        self::TYPE_CREDIT_CARD => 'Credit Card',
        self::TYPE_CURRENT_LIABILITY => 'Current Liabilities',
        self::TYPE_NON_CURRENT_LIABILITY => 'Non-current Liabilities',
        self::TYPE_EQUITY => 'Equity',
        self::TYPE_CURRENT_YEAR_EARNINGS => 'Current Year Earnings',
        self::TYPE_INCOME => 'Income',
        self::TYPE_OTHER_INCOME => 'Other Income',
        self::TYPE_EXPENSE => 'Expenses',
        self::TYPE_DEPRECIATION => 'Depreciation',
        self::TYPE_COST_OF_REVENUE => 'Cost of Revenue',
    ];

    // Map type to category
    public const TYPE_CATEGORY = [
        self::TYPE_RECEIVABLE => 'asset',
        self::TYPE_BANK_CASH => 'asset',
        self::TYPE_CURRENT_ASSET => 'asset',
        self::TYPE_NON_CURRENT_ASSET => 'asset',
        self::TYPE_FIXED_ASSET => 'asset',
        self::TYPE_PREPAYMENTS => 'asset',
        self::TYPE_PAYABLE => 'liability',
        self::TYPE_CREDIT_CARD => 'liability',
        self::TYPE_CURRENT_LIABILITY => 'liability',
        self::TYPE_NON_CURRENT_LIABILITY => 'liability',
        self::TYPE_EQUITY => 'equity',
        self::TYPE_CURRENT_YEAR_EARNINGS => 'equity',
        self::TYPE_INCOME => 'income',
        self::TYPE_OTHER_INCOME => 'income',
        self::TYPE_EXPENSE => 'expense',
        self::TYPE_DEPRECIATION => 'expense',
        self::TYPE_COST_OF_REVENUE => 'expense',
    ];

    public const TYPE_COLORS = [
        self::TYPE_RECEIVABLE => 'info',
        self::TYPE_BANK_CASH => 'primary',
        self::TYPE_CURRENT_ASSET => 'primary',
        self::TYPE_NON_CURRENT_ASSET => 'primary',
        self::TYPE_FIXED_ASSET => 'primary',
        self::TYPE_PREPAYMENTS => 'primary',
        self::TYPE_PAYABLE => 'danger',
        self::TYPE_CREDIT_CARD => 'danger',
        self::TYPE_CURRENT_LIABILITY => 'danger',
        self::TYPE_NON_CURRENT_LIABILITY => 'danger',
        self::TYPE_EQUITY => 'info',
        self::TYPE_CURRENT_YEAR_EARNINGS => 'info',
        self::TYPE_INCOME => 'success',
        self::TYPE_OTHER_INCOME => 'success',
        self::TYPE_EXPENSE => 'warning',
        self::TYPE_DEPRECIATION => 'warning',
        self::TYPE_COST_OF_REVENUE => 'warning',
    ];

    // Relationships
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_id')->orderBy('code');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    // Get the full account path (e.g., "1000 - Assets > 1100 - Cash")
    public function getFullPathAttribute(): string
    {
        $path = [];
        $current = $this;

        while ($current) {
            $path[] = "{$current->code} - {$current->name}";
            $current = $current->parent;
        }

        return implode(' > ', array_reverse($path));
    }

    // Get display name with code
    public function getDisplayNameAttribute(): string
    {
        return "{$this->code} - {$this->name}";
    }

    // Get the translated name attribute
    public function getTranslatedNameAttribute(): string
    {
        return $this->getTranslation('name', app()->getLocale())
            ?: $this->getTranslation('name', 'en')
            ?: '';
    }

    // Get type label
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES_FLAT[$this->type] ?? $this->type;
    }

    // Check if account is debit-normal (Asset, Expense categories)
    public function isDebitNormal(): bool
    {
        $category = self::TYPE_CATEGORY[$this->type] ?? null;
        return in_array($category, ['asset', 'expense']);
    }

    // Check if account is credit-normal (Liability, Equity, Income categories)
    public function isCreditNormal(): bool
    {
        $category = self::TYPE_CATEGORY[$this->type] ?? null;
        return in_array($category, ['liability', 'equity', 'income']);
    }

    // Get the category for this account type
    public function getCategoryAttribute(): ?string
    {
        return self::TYPE_CATEGORY[$this->type] ?? null;
    }

    // Calculate current balance from journal entries
    public function calculateBalance(): int
    {
        $lines = $this->journalLines()
            ->whereHas('journalEntry', function ($q) {
                $q->where('status', JournalEntry::STATUS_POSTED);
            })
            ->get();

        $totalDebit = $lines->sum('debit_minor');
        $totalCredit = $lines->sum('credit_minor');

        if ($this->isDebitNormal()) {
            return $totalDebit - $totalCredit;
        }

        return $totalCredit - $totalDebit;
    }

    // Update the cached balance
    public function updateCachedBalance(): void
    {
        $this->balance_minor = $this->calculateBalance();
        $this->save();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopePostable($query)
    {
        // Only accounts without children can be posted to
        return $query->whereDoesntHave('children');
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('code', 'ilike', "%{$term}%")
                ->orWhereRaw("name->>'en' ILIKE ?", ["%{$term}%"])
                ->orWhereRaw("name->>'ar' ILIKE ?", ["%{$term}%"]);
        });
    }
}
