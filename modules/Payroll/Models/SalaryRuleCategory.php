<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;

class SalaryRuleCategory extends BaseModel
{
    use HasTenancy;

    protected $table = 'salary_rule_categories';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'type',
        'is_active',
        'odoo_id',
        'odoo_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    // Category types
    public const TYPE_EARNING = 'earning';

    public const TYPE_DEDUCTION = 'deduction';

    public const TYPE_ALLOWANCE = 'allowance';

    public const TYPE_BENEFIT = 'benefit';

    public const TYPE_GROSS = 'gross';

    public const TYPE_NET = 'net';

    public const TYPES = [
        self::TYPE_EARNING => 'Earning',
        self::TYPE_DEDUCTION => 'Deduction',
        self::TYPE_ALLOWANCE => 'Allowance',
        self::TYPE_BENEFIT => 'Benefit',
        self::TYPE_GROSS => 'Gross',
        self::TYPE_NET => 'Net',
    ];

    public const TYPE_COLORS = [
        self::TYPE_EARNING => 'success',
        self::TYPE_DEDUCTION => 'danger',
        self::TYPE_ALLOWANCE => 'info',
        self::TYPE_BENEFIT => 'primary',
        self::TYPE_GROSS => 'warning',
        self::TYPE_NET => 'gray',
    ];

    /**
     * Get salary rules in this category.
     */
    public function rules(): HasMany
    {
        return $this->hasMany(SalaryRule::class, 'category_id');
    }

    /**
     * Scope to active categories.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to categories of a specific type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to earnings categories.
     */
    public function scopeEarnings($query)
    {
        return $query->whereIn('type', [self::TYPE_EARNING, self::TYPE_ALLOWANCE, self::TYPE_BENEFIT]);
    }

    /**
     * Scope to deduction categories.
     */
    public function scopeDeductions($query)
    {
        return $query->where('type', self::TYPE_DEDUCTION);
    }

    /**
     * Check if this is an earning type.
     */
    public function isEarning(): bool
    {
        return in_array($this->type, [self::TYPE_EARNING, self::TYPE_ALLOWANCE, self::TYPE_BENEFIT, self::TYPE_GROSS]);
    }

    /**
     * Check if this is a deduction type.
     */
    public function isDeduction(): bool
    {
        return $this->type === self::TYPE_DEDUCTION;
    }

    /**
     * Get the type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Get the type color.
     */
    public function getTypeColorAttribute(): string
    {
        return self::TYPE_COLORS[$this->type] ?? 'gray';
    }

    /**
     * Derive the local 'type' value from the Odoo category code + name.
     * Odoo's hr.salary.rule.category has no 'type' field; we infer it from
     * (1) explicit code map, (2) name keywords, (3) code keywords.
     */
    public static function applyOdooImport(array $data, $mapping = null, ?array $odooData = null): array
    {
        if (! empty($data['type'])) {
            return $data;
        }

        $code = (string) ($data['code'] ?? '');
        $name = (string) ($data['name'] ?? '');
        $codeU = strtoupper($code);
        $nameU = strtoupper($name);

        $explicitCodes = [
            'BASIC' => self::TYPE_EARNING,
            'GROSS' => self::TYPE_GROSS,
            'NET' => self::TYPE_NET,
            'ALW' => self::TYPE_ALLOWANCE,
            'BEN' => self::TYPE_BENEFIT,
            'COMP' => self::TYPE_BENEFIT,
            'PRS' => self::TYPE_BENEFIT,
        ];

        if (isset($explicitCodes[$codeU])) {
            $data['type'] = $explicitCodes[$codeU];

            return $data;
        }

        // Name-based heuristics (most authoritative for ambiguous codes).
        $isDeduction = str_contains($nameU, 'DEDUCTION')
            || str_contains($nameU, 'INSURANCE')
            || str_contains($nameU, 'TAX')
            || str_contains($nameU, 'LOAN')
            || str_contains($nameU, 'PENALTY');
        $isAllowance = str_contains($nameU, 'ALLOWANCE');
        $isBonus = str_contains($nameU, 'BONUS') || str_contains($nameU, 'CONTRIBUTION') || str_contains($nameU, 'PROFIT SHARE');

        if ($isDeduction) {
            $data['type'] = self::TYPE_DEDUCTION;
        } elseif ($isAllowance) {
            $data['type'] = self::TYPE_ALLOWANCE;
        } elseif ($isBonus) {
            $data['type'] = self::TYPE_BENEFIT;
        } elseif (str_contains($codeU, 'DED') || str_contains($codeU, 'INS') || str_contains($codeU, 'TAX') || str_contains($codeU, 'LOAN')) {
            $data['type'] = self::TYPE_DEDUCTION;
        } elseif (str_contains($codeU, 'ALW') || str_contains($codeU, 'ALLOW')) {
            $data['type'] = self::TYPE_ALLOWANCE;
        } else {
            $data['type'] = self::TYPE_EARNING;
        }

        return $data;
    }
}
