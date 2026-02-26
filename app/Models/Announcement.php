<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Announcement extends Model
{
    use SoftDeletes, HasTranslations;

    protected $connection = 'central';

    protected $table = 'public.announcements';

    public array $translatable = ['title', 'body'];

    protected $fillable = [
        'title',
        'body',
        'type',
        'target_plans',
        'delivery_method',
        'status',
        'scheduled_at',
        'sent_at',
        'read_count',
        'created_by',
    ];

    protected $casts = [
        'target_plans' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'read_count' => 'integer',
    ];

    public const TYPES = [
        'info' => 'Information',
        'feature' => 'New Feature',
        'maintenance' => 'Maintenance',
        'urgent' => 'Urgent',
    ];

    public const DELIVERY_METHODS = [
        'in_app' => 'In-App Only',
        'email' => 'Email Only',
        'both' => 'Both',
    ];

    public const STATUSES = [
        'draft' => 'Draft',
        'scheduled' => 'Scheduled',
        'sent' => 'Sent',
    ];

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'gray',
            'scheduled' => 'warning',
            'sent' => 'success',
            default => 'gray',
        };
    }

    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            'info' => 'info',
            'feature' => 'success',
            'maintenance' => 'warning',
            'urgent' => 'danger',
            default => 'gray',
        };
    }

    public function getTypeIconAttribute(): string
    {
        return match ($this->type) {
            'info' => 'ℹ️',
            'feature' => '🆕',
            'maintenance' => '🔧',
            'urgent' => '🚨',
            default => '📢',
        };
    }

    public function send(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        // In a real implementation, this would dispatch jobs to send emails
        // and create in-app notification records
    }

    public function incrementReadCount(): void
    {
        $this->increment('read_count');
    }
}
