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
        'sub_type',
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

    // Account type constants
    public const TYPE_ASSET = 'asset';
    public const TYPE_LIABILITY = 'liability';
    public const TYPE_EQUITY = 'equity';
    public const TYPE_REVENUE = 'revenue';
    public const TYPE_EXPENSE = 'expense';

    public const TYPES = [
        self::TYPE_ASSET => 'Asset',
        self::TYPE_LIABILITY => 'Liability',
        self::TYPE_EQUITY => 'Equity',
        self::TYPE_REVENUE => 'Revenue',
        self::TYPE_EXPENSE => 'Expense',
    ];

    public const TYPE_COLORS = [
        self::TYPE_ASSET => 'primary',
        self::TYPE_LIABILITY => 'danger',
        self::TYPE_EQUITY => 'info',
        self::TYPE_REVENUE => 'success',
        self::TYPE_EXPENSE => 'warning',
    ];

    // Sub-types for better categorization (Odoo-compatible)
    public const SUB_TYPES = [
        // Assets
        'accounts_receivable' => 'Receivable',
        'bank_cash' => 'Bank and Cash',
        'current_asset' => 'Current Assets',
        'non_current_asset' => 'Non-current Assets',
        'fixed_asset' => 'Fixed Assets',
        'prepayments' => 'Prepayments',
        // Liabilities
        'accounts_payable' => 'Payable',
        'current_liability' => 'Current Liabilities',
        'non_current_liability' => 'Non-current Liabilities',
        'credit_card' => 'Credit Card',
        // Equity
        'equity' => 'Equity',
        'current_year_earnings' => 'Current Year Earnings',
        // Revenue
        'income' => 'Income',
        'other_income' => 'Other Income',
        // Expenses
        'expense' => 'Expenses',
        'depreciation' => 'Depreciation',
        'cost_of_revenue' => 'Cost of Revenue',
    ];

    // Map sub_types to their parent types
    public const SUB_TYPE_PARENT = [
        'accounts_receivable' => self::TYPE_ASSET,
        'bank_cash' => self::TYPE_ASSET,
        'current_asset' => self::TYPE_ASSET,
        'non_current_asset' => self::TYPE_ASSET,
        'fixed_asset' => self::TYPE_ASSET,
        'prepayments' => self::TYPE_ASSET,
        'accounts_payable' => self::TYPE_LIABILITY,
        'current_liability' => self::TYPE_LIABILITY,
        'non_current_liability' => self::TYPE_LIABILITY,
        'credit_card' => self::TYPE_LIABILITY,
        'equity' => self::TYPE_EQUITY,
        'current_year_earnings' => self::TYPE_EQUITY,
        'income' => self::TYPE_REVENUE,
        'other_income' => self::TYPE_REVENUE,
        'expense' => self::TYPE_EXPENSE,
        'depreciation' => self::TYPE_EXPENSE,
        'cost_of_revenue' => self::TYPE_EXPENSE,
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
        return self::TYPES[$this->type] ?? $this->type;
    }

    // Check if account is debit-normal (Asset, Expense)
    public function isDebitNormal(): bool
    {
        return in_array($this->type, [self::TYPE_ASSET, self::TYPE_EXPENSE]);
    }

    // Check if account is credit-normal (Liability, Equity, Revenue)
    public function isCreditNormal(): bool
    {
        return in_array($this->type, [self::TYPE_LIABILITY, self::TYPE_EQUITY, self::TYPE_REVENUE]);
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
