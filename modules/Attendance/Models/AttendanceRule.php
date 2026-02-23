<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\Models\User;
use Modules\Booking\Models\WorkSchedule;
use Modules\Staff\Models\StaffProfile;
use XLinic\Framework\Core\Model\BaseModel;

class AttendanceRule extends BaseModel
{
    use SoftDeletes;

    protected $table = 'attendance_rules';

    protected $fillable = [
        'tenant_id',
        'working_schedule_id',
        'name',
        'code',
        'description',
        'category',
        'is_active',
        'sequence',
        'auto_apply',
        'send_notification',
        'notify_manager',
        'notify_hr',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'is_active' => 'boolean',
        'sequence' => 'integer',
        'auto_apply' => 'boolean',
        'send_notification' => 'boolean',
        'notify_manager' => 'boolean',
        'notify_hr' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'is_active' => true,
        'sequence' => 0,
        'auto_apply' => false,
        'send_notification' => true,
        'notify_manager' => false,
        'notify_hr' => false,
    ];

    // Rule Categories
    public const CATEGORY_LATE_CHECKIN = 'late_checkin';
    public const CATEGORY_EARLY_CHECKOUT = 'early_checkout';
    public const CATEGORY_MISSED_CHECKIN = 'missed_checkin';
    public const CATEGORY_MISSED_CHECKOUT = 'missed_checkout';
    public const CATEGORY_OVERSTAY = 'overstay';
    public const CATEGORY_UNAUTHORIZED_ABSENCE = 'unauthorized_absence';

    public const CATEGORIES = [
        self::CATEGORY_LATE_CHECKIN => 'Late Check-In',
        self::CATEGORY_EARLY_CHECKOUT => 'Early Check-Out',
        self::CATEGORY_MISSED_CHECKIN => 'Missed Check-In',
        self::CATEGORY_MISSED_CHECKOUT => 'Missed Check-Out',
        self::CATEGORY_OVERSTAY => 'Overstay',
        self::CATEGORY_UNAUTHORIZED_ABSENCE => 'Unauthorized Absence',
    ];

    public const CATEGORY_COLORS = [
        self::CATEGORY_LATE_CHECKIN => 'warning',
        self::CATEGORY_EARLY_CHECKOUT => 'danger',
        self::CATEGORY_MISSED_CHECKIN => 'danger',
        self::CATEGORY_MISSED_CHECKOUT => 'warning',
        self::CATEGORY_OVERSTAY => 'info',
        self::CATEGORY_UNAUTHORIZED_ABSENCE => 'danger',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the working schedule (uses Booking module's WorkSchedule).
     */
    public function workingSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'working_schedule_id');
    }

    /**
     * Get the rule actions.
     */
    public function actions(): HasMany
    {
        return $this->hasMany(AttendanceRuleAction::class, 'attendance_rule_id')
            ->orderBy('threshold_value');
    }

    /**
     * Get the violations.
     */
    public function violations(): HasMany
    {
        return $this->hasMany(AttendanceViolation::class, 'attendance_rule_id');
    }

    /**
     * Get the creator.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the updater.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope for active rules.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for auto-apply rules.
     */
    public function scopeAutoApply($query)
    {
        return $query->where('auto_apply', true);
    }

    /**
     * Scope for rules with notifications.
     */
    public function scopeWithNotifications($query)
    {
        return $query->where('send_notification', true);
    }

    /**
     * Scope by working schedule.
     */
    public function scopeForSchedule($query, $scheduleId)
    {
        return $query->where(function ($q) use ($scheduleId) {
            $q->where('working_schedule_id', $scheduleId)
              ->orWhereNull('working_schedule_id');
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if rule applies to a staff member.
     */
    public function appliesToStaff(StaffProfile $staff): bool
    {
        // If no schedule specified, applies to all
        if (!$this->working_schedule_id) {
            return true;
        }

        // Check if staff's assigned schedule matches
        // This would need to be implemented based on how staff are assigned to schedules
        return true;
    }

    /**
     * Get the applicable action based on violation minutes and occurrence count.
     */
    public function getApplicableAction(int $violationMinutes, int $occurrenceCount = 1): ?AttendanceRuleAction
    {
        return $this->actions()
            ->where('is_active', true)
            ->where(function ($query) use ($violationMinutes, $occurrenceCount) {
                $query->where(function ($q) use ($violationMinutes) {
                    $q->where('threshold_type', AttendanceRuleAction::THRESHOLD_TIME)
                      ->where('threshold_value', '<=', $violationMinutes);
                })->orWhere(function ($q) use ($occurrenceCount) {
                    $q->where('threshold_type', AttendanceRuleAction::THRESHOLD_OCCURRENCE)
                      ->where('threshold_value', '<=', $occurrenceCount);
                });
            })
            ->orderByDesc('threshold_value')
            ->first();
    }

    /**
     * Get actions count.
     */
    public function getActionsCountAttribute(): int
    {
        return $this->actions()->count();
    }

    /**
     * Get violations count.
     */
    public function getViolationsCountAttribute(): int
    {
        return $this->violations()->count();
    }

    /**
     * Get category label.
     */
    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    /**
     * Check if rule is global (not tied to a specific schedule).
     */
    public function isGlobal(): bool
    {
        return $this->working_schedule_id === null;
    }
}
