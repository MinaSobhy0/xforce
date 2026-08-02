<?php

namespace Modules\Booking\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\User;
use Modules\Core\Models\Branch;
use Modules\Staff\Models\StaffProfile;
use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;

class PractitionerTimeOff extends BaseModel
{
    use HasTenancy;

    protected $table = 'practitioner_time_off';

    protected $fillable = [
        'tenant_id',
        'staff_profile_id',
        'branch_id',
        'time_off_type_id',
        'type',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'is_full_day',
        'days_requested',
        'hours_requested',
        'reason',
        'status',
        'approved_by_user_id',
        'approved_at',
        'notes',
        'odoo_id',
        'odoo_synced_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_full_day' => 'boolean',
        'days_requested' => 'decimal:2',
        'hours_requested' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    // Time off types
    public const TYPE_VACATION = 'vacation';

    public const TYPE_SICK = 'sick';

    public const TYPE_PERSONAL = 'personal';

    public const TYPE_TRAINING = 'training';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_VACATION => 'Vacation',
        self::TYPE_SICK => 'Sick Leave',
        self::TYPE_PERSONAL => 'Personal',
        self::TYPE_TRAINING => 'Training',
        self::TYPE_OTHER => 'Other',
    ];

    // Status
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const STATUS_COLORS = [
        self::STATUS_PENDING => 'warning',
        self::STATUS_APPROVED => 'success',
        self::STATUS_REJECTED => 'danger',
        self::STATUS_CANCELLED => 'gray',
    ];

    protected static function booted(): void
    {
        parent::booted();

        // For hours-based time off types, ensure is_full_day is false
        static::saving(function (self $timeOff) {
            if ($timeOff->time_off_type_id) {
                $type = TimeOffType::find($timeOff->time_off_type_id);
                if ($type && $type->isHourBased()) {
                    $timeOff->is_full_day = false;

                    // Ensure end_date is same as start_date for hours-based
                    if ($timeOff->start_date && ! $timeOff->end_date) {
                        $timeOff->end_date = $timeOff->start_date;
                    }
                }
            }
        });

        static::creating(function (PractitionerTimeOff $timeOff) {
            if (empty($timeOff->status)) {
                $timeOff->status = self::STATUS_PENDING;
            }
            if (is_null($timeOff->is_full_day)) {
                $timeOff->is_full_day = true;
            }
            // Auto-set type from TimeOffType if not provided
            if (empty($timeOff->type)) {
                if ($timeOff->time_off_type_id) {
                    $timeOffType = TimeOffType::find($timeOff->time_off_type_id);
                    $timeOff->type = $timeOffType?->code ?? self::TYPE_OTHER;
                } else {
                    $timeOff->type = self::TYPE_OTHER;
                }
            }
        });
    }

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function timeOffType(): BelongsTo
    {
        return $this->belongsTo(TimeOffType::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    public function getDurationDaysAttribute(): int
    {
        if (! $this->start_date || ! $this->end_date) {
            return 0;
        }

        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function getFormattedPeriodAttribute(): string
    {
        if (! $this->start_date) {
            return '';
        }

        if ($this->start_date->equalTo($this->end_date ?? $this->start_date)) {
            return $this->start_date->format('M d, Y');
        }

        return $this->start_date->format('M d').' - '.($this->end_date ?? $this->start_date)->format('M d, Y');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function approve(string $approvedByUserId): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        try {
            return $this->getConnection()->transaction(function () use ($approvedByUserId) {
                // Deduct first: useDays() refuses (without writing) when the
                // remaining balance can't cover the request, and approval must
                // fail in that case rather than silently over-approving.
                if ($this->time_off_type_id && $this->staff_profile_id) {
                    $deductAmount = $this->getDeductionAmount();

                    if ($deductAmount > 0) {
                        // Allocations are the source of truth (Odoo-synced
                        // when the integration is on): approving must never
                        // mint a default allocation — no covering allocation
                        // means the request cannot be approved.
                        $allocation = TimeOffAllocation::getForDate(
                            $this->staff_profile_id,
                            $this->time_off_type_id,
                            $this->start_date
                        );

                        if (! $allocation || ! $allocation->useDays($deductAmount)) {
                            return false;
                        }
                    }
                }

                if (! $this->update([
                    'status' => self::STATUS_APPROVED,
                    'approved_by_user_id' => $approvedByUserId,
                    'approved_at' => now(),
                ])) {
                    throw new \Modules\Booking\Exceptions\TimeOffApprovalException('Time off approval failed to persist.');
                }

                return true;
            });
        } catch (\Modules\Booking\Exceptions\TimeOffApprovalException) {
            return false;
        }
    }

    public function reject(string $approvedByUserId, ?string $notes = null): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        return $this->update([
            'status' => self::STATUS_REJECTED,
            'approved_by_user_id' => $approvedByUserId,
            'approved_at' => now(),
            'notes' => $notes ?? $this->notes,
        ]);
    }

    public function cancel(): bool
    {
        if ($this->isRejected()) {
            return false;
        }

        $wasApproved = $this->isApproved();

        $result = $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);

        // Return allocation if was approved and using typed time off
        if ($result && $wasApproved && $this->time_off_type_id && $this->staff_profile_id) {
            $returnAmount = $this->getDeductionAmount();

            if ($returnAmount > 0) {
                $allocation = TimeOffAllocation::getForDate(
                    $this->staff_profile_id,
                    $this->time_off_type_id,
                    $this->start_date
                );

                if ($allocation) {
                    $allocation->returnDays($returnAmount);
                }
            }
        }

        return $result;
    }

    public function coversDate(\Carbon\Carbon $date): bool
    {
        if (! $this->isApproved()) {
            return false;
        }

        return $date->between($this->start_date, $this->end_date ?? $this->start_date);
    }

    public function coversDateTime(\Carbon\Carbon $datetime): bool
    {
        if (! $this->coversDate($datetime)) {
            return false;
        }

        if ($this->is_full_day) {
            return true;
        }

        $time = $datetime->format('H:i');

        return $time >= $this->start_time && $time < $this->end_time;
    }

    // Scopes
    public function scopeForStaffProfile($query, int $staffProfileId)
    {
        return $query->where('staff_profile_id', $staffProfileId);
    }

    public function scopeForBranch($query, ?string $branchId)
    {
        if ($branchId === null) {
            return $query;
        }

        return $query->where('branch_id', $branchId);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_APPROVED)
            ->where('end_date', '>=', today());
    }

    public function scopeForDateRange($query, $start, $end)
    {
        return $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('start_date', [$start, $end])
                ->orWhereBetween('end_date', [$start, $end])
                ->orWhere(function ($q2) use ($start, $end) {
                    $q2->where('start_date', '<=', $start)
                        ->where('end_date', '>=', $end);
                });
        });
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('start_date', 'desc');
    }

    /**
     * Get the amount to deduct/return from allocation.
     * Returns hours_requested for hour-based types, days_requested otherwise.
     */
    public function getDeductionAmount(): float
    {
        $type = $this->timeOffType;

        if ($type && $type->isHourBased()) {
            return (float) ($this->hours_requested ?? 0);
        }

        return (float) ($this->days_requested ?? 0);
    }

    /**
     * Get the requested amount in hours.
     */
    public function getRequestedInHours(): float
    {
        if ($this->hours_requested !== null) {
            return (float) $this->hours_requested;
        }

        $type = $this->timeOffType;

        if (! $type) {
            return ($this->days_requested ?? 0) * 8; // Default 8 hours per day
        }

        return $type->convertToHours($this->days_requested ?? 0);
    }

    /**
     * Get formatted display duration with appropriate unit.
     */
    public function getDisplayDurationAttribute(): string
    {
        $type = $this->timeOffType;

        if (! $type) {
            return number_format($this->days_requested ?? 0, 1).' '.__('booking::time_off.request_units.day');
        }

        if ($type->isHourBased() && $this->hours_requested !== null) {
            return $type->formatValue($this->hours_requested);
        }

        return $type->formatValue($this->days_requested ?? 0);
    }

    /**
     * Check if this is an hours-based request.
     */
    public function isHoursBased(): bool
    {
        return $this->timeOffType?->isHourBased() ?? false;
    }

    /**
     * Convert raw Odoo data into the local storage shape before persistence.
     *
     * Odoo's `hr.leave.date_from` / `date_to` are full datetimes (UTC, then timezone-converted
     * by the field transformer to the app TZ). Locally we split them: the date portion goes
     * into `start_date` / `end_date` (cast as date), and the time portion into
     * `start_time` / `end_time`. Without this hook the time is silently truncated.
     */
    public static function applyOdooImport(array $data, $mapping = null, ?array $odooData = null): array
    {
        // Split datetime strings produced by transformImport into date + time pairs.
        // The `is_full_day` flag is handled by the model's saving hook based on the type.
        foreach ([['start_date', 'start_time'], ['end_date', 'end_time']] as [$dateKey, $timeKey]) {
            if (! empty($data[$dateKey]) && is_string($data[$dateKey]) && str_contains($data[$dateKey], ' ')) {
                [$d, $t] = explode(' ', $data[$dateKey], 2);
                $data[$dateKey] = $d;
                $data[$timeKey] = substr($t, 0, 5); // HH:MM
            }
        }

        return $data;
    }

    /**
     * Post-transform hook on export.
     *
     * Inject Odoo-specific fields not present in the local schema:
     *   - replacement_emp: custom field added by some Odoo HR installs,
     *     marked required on certain leave types. We send `false` (no
     *     replacement) since the local model doesn't track this.
     *   - state: Odoo's hr.leave workflow expects specific transitions.
     *     On create, omit state and let Odoo default to 'confirm'/'draft'.
     *     On update for an approved leave, we still send the enum-mapped
     *     state and let Odoo accept or reject it.
     */
    public static function applyOdooExport(array $data, $mapping = null, $localRecord = null): array
    {
        // Some Odoo HR installs add a `replacement_emp` field (Many2one to hr.employee)
        // and mark it required for certain leave types. The local schema has no
        // "replacement employee" concept, so we default to self-replacement —
        // satisfies the required-field check without picking an arbitrary person.
        if (! empty($data['employee_id'])) {
            $data['replacement_emp'] = (int) $data['employee_id'];
        }

        $isCreate = $localRecord && empty($localRecord->odoo_id);

        // Odoo 16+: hr.leave dates are DRIVEN by request_date_from/
        // request_date_to — date_from/date_to are computed from them.
        // Sending only the computed pair makes Odoo recompute from an unset
        // request date, which DEFAULTS TO TODAY on create (surfaced by the
        // realtime QA run: an Aug 20-21 request landed in Odoo dated today,
        // and the import sweep then mirrored the wrong dates back locally).
        if ($localRecord && $localRecord->start_date) {
            $data['request_date_from'] = $localRecord->start_date->toDateString();
            $data['request_date_to'] = ($localRecord->end_date ?? $localRecord->start_date)->toDateString();
            unset($data['date_from'], $data['date_to']);

            if ($localRecord->timeOffType?->isHourBased()
                && $localRecord->start_time && $localRecord->end_time) {
                $data['request_unit_hours'] = true;
                $data['request_hour_from'] = self::timeStringToFloat($localRecord->start_time);
                $data['request_hour_to'] = self::timeStringToFloat($localRecord->end_time);
            }
        }

        // Odoo's hr.leave state transitions are gated by workflow methods —
        // direct state writes are blocked once the record leaves draft.
        // So we never send `state` and instead emit __odoo_actions, which
        // ExportService translates into execute_kw calls after the write.
        unset($data['state']);

        if (! $isCreate) {
            $status = $localRecord?->status;
            $action = match ($status) {
                self::STATUS_APPROVED => 'action_approve',
                self::STATUS_REJECTED, self::STATUS_CANCELLED => 'action_refuse',
                default => null,
            };

            if ($action) {
                $data['__odoo_actions'] = [$action];
            }
        }

        return $data;
    }

    /**
     * Working days between two dates per the staff member's assigned work
     * schedule (like Odoo's leave computation): days the schedule marks as
     * non-working (weekends, days off) do not consume balance. Falls back to
     * plain calendar days when no schedule is assigned.
     */
    public static function workingDaysBetween($staffProfile, \Carbon\Carbon $start, \Carbon\Carbon $end): float
    {
        $schedule = null;
        if ($staffProfile && class_exists(\Modules\Attendance\Services\AttendanceService::class)) {
            try {
                $schedule = app(\Modules\Attendance\Services\AttendanceService::class)->getStaffSchedule($staffProfile);
            } catch (\Throwable) {
                $schedule = null;
            }
        }

        $days = 0;
        for ($d = $start->copy()->startOfDay(); $d->lte($end); $d->addDay()) {
            if (! $schedule) {
                $days++;

                continue;
            }

            $daySchedule = $schedule->getDaySchedule((int) $d->dayOfWeek);
            if ($daySchedule && ! empty($daySchedule['is_working'])) {
                $days++;
            }
        }

        return (float) $days;
    }

    /**
     * "08:30" → 8.5 (Odoo request_hour_from/_to format).
     */
    protected static function timeStringToFloat(string $time): float
    {
        $t = \Carbon\Carbon::parse($time);

        return $t->hour + $t->minute / 60;
    }

    /**
     * Calculate hours from time range.
     */
    public static function calculateHoursFromTimeRange(?string $startTime, ?string $endTime): float
    {
        if (! $startTime || ! $endTime) {
            return 0;
        }

        $start = \Carbon\Carbon::parse($startTime);
        $end = \Carbon\Carbon::parse($endTime);

        return round($start->diffInMinutes($end) / 60, 2);
    }
}
