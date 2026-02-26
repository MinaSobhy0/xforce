<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;

class EmployeeSalaryStructure extends BaseModel
{
    use HasTenancy;

    protected $table = 'employee_salary_structures';

    protected $fillable = [
        'tenant_id',
        'staff_profile_id',
        'salary_structure_id',
        'base_salary_minor',
        'effective_date',
        'end_date',
        'is_current',
        'assigned_by',
        'notes',
    ];

    protected $casts = [
        'base_salary_minor' => 'integer',
        'effective_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
    ];

    protected $attributes = [
        'base_salary_minor' => 0,
        'is_current' => false,
    ];

    protected static function boot(): void
    {
        parent::boot();

        // Ensure only one current structure per employee
        static::saving(function ($model) {
            if ($model->is_current) {
                $query = static::query()
                    ->where('staff_profile_id', $model->staff_profile_id);

                // Only exclude current record if it exists (has an ID)
                if ($model->exists && $model->id) {
                    $query->where('id', '!=', $model->id);
                }

                $query->update(['is_current' => false]);
            }
        });
    }

    /**
     * Get the staff profile.
     */
    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(\Modules\Staff\Models\StaffProfile::class, 'staff_profile_id');
    }

    /**
     * Get the salary structure.
     */
    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id');
    }

    /**
     * Get the user who assigned this structure.
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\Models\User::class, 'assigned_by');
    }

    /**
     * Scope to current assignments.
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Scope to active (not ended) assignments.
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('end_date')
              ->orWhere('end_date', '>=', now()->toDateString());
        });
    }

    /**
     * Get base salary in major units.
     */
    public function getBaseSalaryAttribute(): float
    {
        return $this->base_salary_minor / 100;
    }

    /**
     * Set base salary from major units.
     */
    public function setBaseSalaryAttribute($value): void
    {
        $this->attributes['base_salary_minor'] = (int) round($value * 100);
    }

    /**
     * Check if this assignment is currently effective.
     */
    public function isEffective(): bool
    {
        $now = now()->toDateString();

        if ($this->effective_date > $now) {
            return false;
        }

        if ($this->end_date && $this->end_date < $now) {
            return false;
        }

        return true;
    }

    /**
     * Make this the current structure for the employee.
     */
    public function makeCurrent(): self
    {
        $this->update(['is_current' => true]);
        return $this;
    }
}
