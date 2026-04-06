<?php

namespace Modules\Projects\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\Models\User;
use Modules\Projects\Enums\TimesheetStatus;
use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasActivity;

class TimesheetSubmission extends BaseModel
{
    use HasActivity;
    use SoftDeletes;

    protected $table = 'timesheet_submissions';

    // Disable auto branch assignment
    protected bool $autoSetBranchId = false;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'week_start',
        'week_end',
        'total_hours',
        'status',
        'approved_by',
        'submitted_at',
        'approved_at',
        'rejection_reason',
        'odoo_id',
        'odoo_synced_at',
    ];

    protected $casts = [
        'week_start' => 'date',
        'week_end' => 'date',
        'total_hours' => 'decimal:2',
        'status' => TimesheetStatus::class,
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'odoo_synced_at' => 'datetime',
    ];

    protected $attributes = [
        'total_hours' => 0,
        'status' => 'draft',
    ];

    /**
     * Get the user who submitted the timesheet.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the approver user.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get time entries in this submission.
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(ProjectTimeEntry::class, 'submission_id');
    }

    /**
     * Scope for pending review submissions.
     */
    public function scopePendingReview($query)
    {
        return $query->where('status', TimesheetStatus::SUBMITTED);
    }

    /**
     * Scope for approved submissions.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', TimesheetStatus::APPROVED);
    }

    /**
     * Scope for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for a specific week.
     */
    public function scopeForWeek($query, $weekStart)
    {
        return $query->where('week_start', $weekStart);
    }

    /**
     * Submit the timesheet for approval.
     */
    public function submit(): bool
    {
        if (!$this->status->canSubmit()) {
            return false;
        }

        // Calculate total hours
        $totalHours = $this->timeEntries()->sum('hours');

        $this->update([
            'status' => TimesheetStatus::SUBMITTED,
            'total_hours' => $totalHours,
            'submitted_at' => now(),
        ]);

        return true;
    }

    /**
     * Approve the submission.
     */
    public function approve(int $userId): bool
    {
        if (!$this->status->canReview()) {
            return false;
        }

        $this->update([
            'status' => TimesheetStatus::APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        return true;
    }

    /**
     * Reject the submission.
     */
    public function reject(int $userId, string $reason): bool
    {
        if (!$this->status->canReview()) {
            return false;
        }

        $this->update([
            'status' => TimesheetStatus::REJECTED,
            'approved_by' => $userId,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return true;
    }

    /**
     * Reset to draft (after rejection).
     */
    public function resetToDraft(): void
    {
        $this->update([
            'status' => TimesheetStatus::DRAFT,
            'submitted_at' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    /**
     * Recalculate total hours.
     */
    public function recalculateHours(): void
    {
        $totalHours = $this->timeEntries()->sum('hours');
        $this->update(['total_hours' => $totalHours]);
    }

    /**
     * Get the week date range as string.
     */
    public function getWeekRangeAttribute(): string
    {
        return $this->week_start->format('M d') . ' - ' . $this->week_end->format('M d, Y');
    }

    /**
     * Get the user name for display.
     */
    public function getUserNameAttribute(): string
    {
        return $this->user?->name ?? 'Unknown User';
    }

    /**
     * Get the approver name for display.
     */
    public function getApproverNameAttribute(): ?string
    {
        return $this->approver?->name;
    }

    /**
     * Check if the submission can be edited.
     */
    public function canEdit(): bool
    {
        return $this->status->isEditable();
    }

    /**
     * Check if the submission is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === TimesheetStatus::APPROVED;
    }

    /**
     * Check if the submission is pending review.
     */
    public function isPendingReview(): bool
    {
        return $this->status === TimesheetStatus::SUBMITTED;
    }

    /**
     * Get the number of days with entries.
     */
    public function getDaysWorkedAttribute(): int
    {
        return (int) $this->timeEntries()
            ->selectRaw('COUNT(DISTINCT date) as count')
            ->value('count');
    }

    /**
     * Get billable hours for this submission.
     */
    public function getBillableHoursAttribute(): float
    {
        return (float) $this->timeEntries()->where('is_billable', true)->sum('hours');
    }

    /**
     * Get non-billable hours for this submission.
     */
    public function getNonBillableHoursAttribute(): float
    {
        return (float) $this->timeEntries()->where('is_billable', false)->sum('hours');
    }

    /**
     * Get or create submission for a week.
     */
    public static function getOrCreateForWeek(int $userId, $weekStart, ?int $tenantId = null): self
    {
        $weekStart = Carbon::parse($weekStart)->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        return static::firstOrCreate(
            [
                'user_id' => $userId,
                'week_start' => $weekStart,
            ],
            [
                'tenant_id' => $tenantId,
                'week_end' => $weekEnd,
                'status' => TimesheetStatus::DRAFT,
                'total_hours' => 0,
            ]
        );
    }

    /**
     * Get submissions requiring attention from a manager.
     */
    public static function pendingApproval(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('status', TimesheetStatus::SUBMITTED)
            ->orderBy('submitted_at', 'asc')
            ->get();
    }
}
