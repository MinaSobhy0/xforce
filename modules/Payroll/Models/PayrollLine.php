<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Staff\Models\StaffProfile;
use Modules\Staff\Models\StaffCommissionRecord;
use XLinic\Framework\Core\Model\BaseModel;

class PayrollLine extends BaseModel
{
    protected $table = 'payroll_lines';

    protected $fillable = [
        'tenant_id',
        'payroll_run_id',
        'staff_profile_id',
        'base_salary_minor',
        'commissions_minor',
        'bonuses_minor',
        'deductions_minor',
        'tax_minor',
        'social_insurance_minor',
        'net_salary_minor',
        'commission_records_json',
        'bonus_details_json',
        'deduction_details_json',
        'notes',
    ];

    protected $casts = [
        'id' => 'string',
        'base_salary_minor' => 'integer',
        'commissions_minor' => 'integer',
        'bonuses_minor' => 'integer',
        'deductions_minor' => 'integer',
        'tax_minor' => 'integer',
        'social_insurance_minor' => 'integer',
        'net_salary_minor' => 'integer',
        'commission_records_json' => 'array',
        'bonus_details_json' => 'array',
        'deduction_details_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'base_salary_minor' => 0,
        'commissions_minor' => 0,
        'bonuses_minor' => 0,
        'deductions_minor' => 0,
        'tax_minor' => 0,
        'social_insurance_minor' => 0,
        'net_salary_minor' => 0,
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

        // Calculate net salary on save
        static::saving(function (self $line) {
            $line->net_salary_minor = $line->base_salary_minor
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
     * Mark related commission records as paid.
     */
    public function markCommissionsAsPaid(): void
    {
        if (!empty($this->commission_records_json)) {
            StaffCommissionRecord::whereIn('id', $this->commission_records_json)
                ->where('status', StaffCommissionRecord::STATUS_APPROVED)
                ->each(function ($record) {
                    $record->markAsPaid();
                });
        }
    }

    /**
     * Get gross salary (before deductions).
     */
    public function getGrossSalaryMinorAttribute(): int
    {
        return $this->base_salary_minor
            + $this->commissions_minor
            + $this->bonuses_minor;
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
}
