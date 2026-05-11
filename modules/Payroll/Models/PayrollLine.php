<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Staff\Models\StaffCommissionRecord;
use Modules\Staff\Models\StaffProfile;
use XLinic\Framework\Core\Model\BaseModel;

class PayrollLine extends BaseModel
{
    protected $table = 'payroll_lines';

    protected $fillable = [
        'tenant_id',
        'payroll_run_id',
        'staff_profile_id',
        'status',
        'base_salary_minor',
        'allowances_minor',
        'commissions_minor',
        'bonuses_minor',
        'deductions_minor',
        'tax_minor',
        'social_insurance_minor',
        'net_salary_minor',
        'commission_records_json',
        'bonus_details_json',
        'deduction_details_json',
        'rule_amounts_json',
        'notes',
        'odoo_id',
        'odoo_synced_at',
    ];

    protected $casts = [
        'base_salary_minor' => 'integer',
        'allowances_minor' => 'integer',
        'commissions_minor' => 'integer',
        'bonuses_minor' => 'integer',
        'deductions_minor' => 'integer',
        'tax_minor' => 'integer',
        'social_insurance_minor' => 'integer',
        'net_salary_minor' => 'integer',
        'commission_records_json' => 'array',
        'bonus_details_json' => 'array',
        'deduction_details_json' => 'array',
        'rule_amounts_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'base_salary_minor' => 0,
        'allowances_minor' => 0,
        'commissions_minor' => 0,
        'bonuses_minor' => 0,
        'deductions_minor' => 0,
        'tax_minor' => 0,
        'social_insurance_minor' => 0,
        'net_salary_minor' => 0,
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_PENDING => 'Pending',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_PAID => 'Paid',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const STATUS_COLORS = [
        self::STATUS_DRAFT => 'gray',
        self::STATUS_PENDING => 'warning',
        self::STATUS_APPROVED => 'info',
        self::STATUS_PAID => 'success',
        self::STATUS_CANCELLED => 'danger',
    ];

    /**
     * Get the payroll run.
     */
    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    /**
     * Get the staff profile.
     */
    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        parent::booted();

        // Calculate net salary on save — but trust Odoo's value for synced lines.
        // Odoo's NET line accounts for company-vs-employee splits we can't always
        // infer from our category buckets (e.g. "Insurance From Company"), so for
        // imported rows we keep whatever applyOdooImport wrote.
        static::saving(function (self $line) {
            if ($line->odoo_id) {
                return;
            }
            $line->net_salary_minor = $line->base_salary_minor
                + $line->allowances_minor
                + $line->commissions_minor
                + $line->bonuses_minor
                - $line->deductions_minor
                - $line->tax_minor
                - $line->social_insurance_minor;
        });

        // Update payroll run totals
        static::saved(function (self $line) {
            $line->payrollRun?->recalculateTotals();
        });

        static::deleted(function (self $line) {
            $line->payrollRun?->recalculateTotals();
        });
    }

    /**
     * Editable when in draft/pending. Once approved/paid the slip is locked,
     * and a parent run that's been finalized also locks its slips.
     */
    public function isEditable(): bool
    {
        if (in_array($this->status, [self::STATUS_APPROVED, self::STATUS_PAID, self::STATUS_CANCELLED], true)) {
            return false;
        }

        return $this->payrollRun?->isEditable() ?? true;
    }

    /**
     * Mark related commission records as paid.
     */
    public function markCommissionsAsPaid(): void
    {
        if (! empty($this->commission_records_json)) {
            StaffCommissionRecord::whereIn('id', $this->commission_records_json)
                ->where('status', StaffCommissionRecord::STATUS_APPROVED)
                ->each(function ($record) {
                    $record->markAsPaid();
                });
        }
    }

    /**
     * Get gross salary (before deductions).
     *
     * For Odoo-synced rows, Odoo's GROSS formula includes company-paid contributions
     * and other items our local "earnings" buckets don't carry. We trust Odoo's GROSS
     * line stored in rule_amounts_json. For in-app calculations we fall back to the
     * sum of earnings buckets.
     */
    public function getGrossSalaryMinorAttribute(): int
    {
        if ($this->odoo_id) {
            foreach ($this->rule_amounts_json ?? [] as $rule) {
                if (($rule['rule_code'] ?? null) === 'GROSS') {
                    return (int) ($rule['amount_minor'] ?? 0);
                }
            }
        }

        return $this->base_salary_minor
            + $this->allowances_minor
            + $this->commissions_minor
            + $this->bonuses_minor;
    }

    /**
     * Get allowances in major units.
     */
    public function getAllowancesAttribute(): float
    {
        return $this->allowances_minor / 100;
    }

    /**
     * Get total deductions.
     */
    public function getTotalDeductionsMinorAttribute(): int
    {
        return $this->deductions_minor
            + $this->tax_minor
            + $this->social_insurance_minor;
    }

    /**
     * Get base salary in major units.
     */
    public function getBaseSalaryAttribute(): float
    {
        return $this->base_salary_minor / 100;
    }

    /**
     * Get commissions in major units.
     */
    public function getCommissionsAttribute(): float
    {
        return $this->commissions_minor / 100;
    }

    /**
     * Get bonuses in major units.
     */
    public function getBonusesAttribute(): float
    {
        return $this->bonuses_minor / 100;
    }

    /**
     * Get deductions in major units.
     */
    public function getDeductionsAttribute(): float
    {
        return $this->deductions_minor / 100;
    }

    /**
     * Get net salary in major units.
     */
    public function getNetSalaryAttribute(): float
    {
        return $this->net_salary_minor / 100;
    }

    /**
     * Get tax in major units.
     */
    public function getTaxAttribute(): float
    {
        return $this->tax_minor / 100;
    }

    /**
     * Get social insurance in major units.
     */
    public function getSocialInsuranceAttribute(): float
    {
        return $this->social_insurance_minor / 100;
    }

    /**
     * Calculate from staff profile for a period.
     */
    public static function generateFromStaffProfile(
        StaffProfile $staff,
        PayrollRun $payrollRun
    ): self {
        // Get approved commissions for this staff for the period
        $startDate = "{$payrollRun->period_year}-{$payrollRun->period_month}-01";
        $endDate = date('Y-m-t', strtotime($startDate));

        $commissionRecords = StaffCommissionRecord::where('staff_profile_id', $staff->id)
            ->where('status', StaffCommissionRecord::STATUS_APPROVED)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $commissionsMinor = $commissionRecords->sum('amount_minor');
        $commissionIds = $commissionRecords->pluck('id')->toArray();

        // Calculate tax (simplified)
        $grossAnnual = ($staff->base_salary_minor + $commissionsMinor) * 12;
        $taxMinor = self::calculateTax($grossAnnual / 12);

        // Calculate social insurance
        $socialInsuranceMinor = (int) ($staff->base_salary_minor * 0.11); // 11%

        return new self([
            'tenant_id' => $staff->tenant_id,
            'payroll_run_id' => $payrollRun->id,
            'staff_profile_id' => $staff->id,
            'base_salary_minor' => $staff->base_salary_minor,
            'commissions_minor' => $commissionsMinor,
            'bonuses_minor' => 0,
            'deductions_minor' => 0,
            'tax_minor' => $taxMinor,
            'social_insurance_minor' => $socialInsuranceMinor,
            'commission_records_json' => $commissionIds,
        ]);
    }

    /**
     * Calculate tax based on Egyptian tax brackets (simplified).
     */
    protected static function calculateTax(int $monthlyGrossMinor): int
    {
        // Convert to annual for tax calculation
        $annualGross = $monthlyGrossMinor * 12 / 100; // Convert to EGP

        // Egyptian tax brackets (simplified)
        $tax = 0;

        if ($annualGross > 400000) {
            $tax += ($annualGross - 400000) * 0.25;
            $annualGross = 400000;
        }
        if ($annualGross > 200000) {
            $tax += ($annualGross - 200000) * 0.225;
            $annualGross = 200000;
        }
        if ($annualGross > 60000) {
            $tax += ($annualGross - 60000) * 0.20;
            $annualGross = 60000;
        }
        if ($annualGross > 45000) {
            $tax += ($annualGross - 45000) * 0.15;
            $annualGross = 45000;
        }
        if ($annualGross > 30000) {
            $tax += ($annualGross - 30000) * 0.10;
            $annualGross = 30000;
        }
        if ($annualGross > 15000) {
            $tax += ($annualGross - 15000) * 0.025;
        }

        // Return monthly tax in minor units
        return (int) ($tax / 12 * 100);
    }

    /**
     * Build a PayrollLine from an Odoo hr.payslip.
     *
     * Odoo's hr.payslip = one slip per (employee, period). Locally we model that as
     * a PayrollLine inside a parent PayrollRun (one Run per company-month). This hook:
     *   1. Pulls date_from + line_ids from Odoo (separate API call — line_ids is one2many).
     *   2. Finds-or-creates the PayrollRun for (period_year, period_month).
     *   3. Buckets line totals by the rule's category code (BASIC / ALW / DED / etc.)
     *      into the local aggregate columns (base_salary_minor, allowances_minor, ...).
     *   4. Stores the full per-rule breakdown in rule_amounts_json.
     */
    public static function applyOdooImport(array $data, $mapping = null, ?array $odooData = null): array
    {
        if (! $mapping || ! $odooData || empty($odooData['id'])) {
            return $data;
        }

        $connection = \Modules\OdooIntegration\Models\OdooConnection::find($mapping->odoo_connection_id);
        if (! $connection) {
            return $data;
        }
        $client = app(\Modules\OdooIntegration\Services\Api\OdooApiFactory::class)->make($connection);
        $client->authenticate();

        // Fetch the slip's period + line IDs (not in default field mapping).
        $slipDetails = $client->read('hr.payslip', [(int) $odooData['id']], [
            'date_from', 'date_to', 'number', 'line_ids',
        ])[0] ?? null;
        if (! $slipDetails) {
            return $data;
        }

        // Standalone slips (no period info) are allowed — leave payroll_run_id null.
        $dateFrom = $slipDetails['date_from'] ?? null;
        if ($dateFrom) {
            $period = \Carbon\Carbon::parse($dateFrom);
            $year = (int) $period->year;
            $month = (int) $period->month;

            // Parent PayrollRun — find by (tenant, year, month) or create one.
            $tenantId = $data['tenant_id'] ?? $mapping->tenant_id ?? current_tenant_id();
            $run = PayrollRun::query()
                ->where('tenant_id', $tenantId)
                ->where('period_year', $year)
                ->where('period_month', $month)
                ->first();
            if (! $run) {
                $run = PayrollRun::create([
                    'tenant_id' => $tenantId,
                    'period_year' => $year,
                    'period_month' => $month,
                    'status' => PayrollRun::STATUS_DRAFT,
                    'employee_count' => 0,
                    'notes' => 'Imported from Odoo',
                ]);
            }
            $data['payroll_run_id'] = $run->id;
        }

        // Pull the slip's line items + their salary rules + categories.
        $lineIds = array_values(array_filter((array) ($slipDetails['line_ids'] ?? [])));
        $bucketedTotals = [
            'base_salary_minor' => 0, 'allowances_minor' => 0, 'commissions_minor' => 0,
            'bonuses_minor' => 0, 'deductions_minor' => 0, 'tax_minor' => 0,
            'social_insurance_minor' => 0, 'net_salary_minor' => 0,
        ];
        $ruleBreakdown = [];

        if (! empty($lineIds)) {
            $lines = $client->read('hr.payslip.line', $lineIds, ['code', 'name', 'category_id', 'salary_rule_id', 'total']);
            foreach ($lines as $line) {
                $catOdooId = is_array($line['category_id'] ?? null) ? $line['category_id'][0] : null;
                $cat = self::resolveCategory($catOdooId);
                $ruleOdooId = is_array($line['salary_rule_id'] ?? null) ? $line['salary_rule_id'][0] : null;
                $totalMinor = (int) round(((float) ($line['total'] ?? 0)) * 100);

                // Match the canonical rule_amounts_json shape used by PayrollCalculationService
                // and consumed by the Filament infolist (resources/views/.../salary-rules-table).
                $ruleBreakdown[] = [
                    'rule_id' => self::resolveLocalRuleId($ruleOdooId),
                    'rule_code' => $line['code'] ?? null,
                    'rule_name' => $line['name'] ?? null,
                    'category_type' => $cat['type'] ?? null,
                    'amount_minor' => $totalMinor,
                ];

                $bucket = self::categoryToBucket($cat['code'] ?? null);
                if ($bucket && isset($bucketedTotals[$bucket])) {
                    $bucketedTotals[$bucket] += $totalMinor;
                }
            }
        }

        $data = array_merge($data, $bucketedTotals);
        $data['rule_amounts_json'] = $ruleBreakdown;

        return $data;
    }

    /**
     * Resolve an Odoo category id to local {code, type} (cached per request).
     */
    protected static function resolveCategory(?int $odooCategoryId): array
    {
        if (! $odooCategoryId) {
            return [];
        }
        static $cache = [];
        if (array_key_exists($odooCategoryId, $cache)) {
            return $cache[$odooCategoryId];
        }
        $row = SalaryRuleCategory::query()
            ->where('odoo_id', $odooCategoryId)
            ->first(['code', 'type']);

        return $cache[$odooCategoryId] = $row
            ? ['code' => $row->code, 'type' => $row->type]
            : [];
    }

    /**
     * Resolve an Odoo salary_rule id to a local SalaryRule.id (cached per request).
     */
    protected static function resolveLocalRuleId(?int $odooRuleId): ?int
    {
        if (! $odooRuleId) {
            return null;
        }
        static $cache = [];
        if (array_key_exists($odooRuleId, $cache)) {
            return $cache[$odooRuleId];
        }

        return $cache[$odooRuleId] = SalaryRule::query()
            ->where('odoo_id', $odooRuleId)
            ->value('id');
    }

    /**
     * Map a SalaryRuleCategory.code to one of the PayrollLine aggregate columns.
     * Net is intentionally not bucketed (it's a derived total, not an addition).
     */
    protected static function categoryToBucket(?string $categoryCode): ?string
    {
        if (! $categoryCode) {
            return null;
        }
        $u = strtoupper($categoryCode);

        return match (true) {
            $u === 'BASIC' => 'base_salary_minor',
            $u === 'NET' => 'net_salary_minor',
            $u === 'ALW' || str_contains($u, 'ALLOW') => 'allowances_minor',
            str_contains($u, 'TAX') => 'tax_minor',
            str_contains($u, 'INS') => 'social_insurance_minor',
            $u === 'COMP' || $u === 'PRS' || str_contains($u, 'BONUS') || str_contains($u, 'COMMISSION') => 'bonuses_minor',
            str_contains($u, 'DED') || str_contains($u, 'LOAN') || str_contains($u, 'PENALTY') => 'deductions_minor',
            $u === 'GROSS' => null,  // gross is informational, don't bucket
            default => 'deductions_minor', // safer default for unknowns
        };
    }
}
