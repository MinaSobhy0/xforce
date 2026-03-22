<?php

namespace Modules\MobileApi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Auth\Models\User;
use XLinic\Framework\Core\Model\BaseModel;

class PushNotification extends BaseModel
{
    protected $table = 'push_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'data',
        'reference_type',
        'reference_id',
        'status',
        'fcm_message_id',
        'error_message',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    // Notification Types
    public const TYPE_ATTENDANCE_VIOLATION = 'attendance_violation';

    public const TYPE_VIOLATION_STATUS_CHANGED = 'violation_status_changed';

    public const TYPE_CHECK_IN_REMINDER = 'check_in_reminder';

    public const TYPE_TIME_OFF_APPROVED = 'time_off_approved';

    public const TYPE_TIME_OFF_REJECTED = 'time_off_rejected';

    public const TYPE_APPOINTMENT_ASSIGNED = 'appointment_assigned';

    public const TYPE_APPOINTMENT_REMINDER = 'appointment_reminder';

    public const TYPE_APPOINTMENT_CANCELLED = 'appointment_cancelled';

    public const TYPE_PAYSLIP_READY = 'payslip_ready';

    public const TYPE_COMMISSION_EARNED = 'commission_earned';

    public const TYPE_SCHEDULE_CHANGED = 'schedule_changed';

    public const TYPES = [
        self::TYPE_ATTENDANCE_VIOLATION => 'Attendance Violation',
        self::TYPE_VIOLATION_STATUS_CHANGED => 'Violation Status Changed',
        self::TYPE_CHECK_IN_REMINDER => 'Check-In Reminder',
        self::TYPE_TIME_OFF_APPROVED => 'Time Off Approved',
        self::TYPE_TIME_OFF_REJECTED => 'Time Off Rejected',
        self::TYPE_APPOINTMENT_ASSIGNED => 'Appointment Assigned',
        self::TYPE_APPOINTMENT_REMINDER => 'Appointment Reminder',
        self::TYPE_APPOINTMENT_CANCELLED => 'Appointment Cancelled',
        self::TYPE_PAYSLIP_READY => 'Payslip Ready',
        self::TYPE_COMMISSION_EARNED => 'Commission Earned',
        self::TYPE_SCHEDULE_CHANGED => 'Schedule Changed',
    ];

    // Status
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const STATUS_READ = 'read';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_SENT => 'Sent',
        self::STATUS_DELIVERED => 'Delivered',
        self::STATUS_FAILED => 'Failed',
        self::STATUS_READ => 'Read',
    ];

    public const STATUS_COLORS = [
        self::STATUS_PENDING => 'warning',
        self::STATUS_SENT => 'info',
        self::STATUS_DELIVERED => 'success',
        self::STATUS_FAILED => 'danger',
        self::STATUS_READ => 'gray',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the user that owns this notification.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the reference model (polymorphic).
     */
    public function reference(): MorphTo
    {
        return $this->morphTo('reference', 'reference_type', 'reference_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope for specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for specific type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for pending notifications.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for sent notifications.
     */
    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    /**
     * Scope for failed notifications.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope for read notifications.
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope for recent notifications.
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Mark as sent.
     */
    public function markAsSent(?string $fcmMessageId = null): bool
    {
        return $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
            'fcm_message_id' => $fcmMessageId,
        ]);
    }

    /**
     * Mark as delivered.
     */
    public function markAsDelivered(): bool
    {
        return $this->update([
            'status' => self::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);
    }

    /**
     * Mark as failed.
     */
    public function markAsFailed(string $errorMessage): bool
    {
        return $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Mark as read.
     */
    public function markAsRead(): bool
    {
        if ($this->read_at) {
            return true;
        }

        return $this->update([
            'status' => self::STATUS_READ,
            'read_at' => now(),
        ]);
    }

    /**
     * Check if notification was successfully sent.
     */
    public function wasSent(): bool
    {
        return in_array($this->status, [
            self::STATUS_SENT,
            self::STATUS_DELIVERED,
            self::STATUS_READ,
        ]);
    }

    /**
     * Check if notification failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if notification is read.
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Get the type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Get the status color.
     */
    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    /**
     * Get all notification types for settings/preferences.
     */
    public static function getAvailableTypes(): array
    {
        return self::TYPES;
    }
}
