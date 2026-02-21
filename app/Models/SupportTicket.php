<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\Tenant;

class SupportTicket extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'central';

    protected $table = 'public.support_tickets';

    protected $fillable = [
        'tenant_id',
        'ticket_number',
        'subject',
        'description',
        'priority',
        'status',
        'category',
        'assigned_to',
        'reporter_user_id',
        'reporter_name',
        'reporter_email',
        'resolved_at',
        'resolution_notes',
        'internal_notes',
        'escalated_at',
        'escalated_to',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'escalated_at' => 'datetime',
    ];

    public const PRIORITIES = [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'urgent' => 'Urgent',
    ];

    public const STATUSES = [
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'waiting_customer' => 'Waiting on Customer',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];

    public const CATEGORIES = [
        'billing' => 'Billing',
        'technical' => 'Technical Issue',
        'feature_request' => 'Feature Request',
        'bug' => 'Bug Report',
        'account' => 'Account',
        'other' => 'Other',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $ticket) {
            if (empty($ticket->ticket_number)) {
                $lastNumber = static::whereYear('created_at', now()->year)
                    ->max('ticket_number');
                $nextNumber = $lastNumber ? (int) substr($lastNumber, -4) + 1 : 1;
                $ticket->ticket_number = 'TK-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SupportTicketReply::class, 'ticket_id');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'in_progress', 'waiting_customer']);
    }

    public function scopeResolved($query)
    {
        return $query->whereIn('status', ['resolved', 'closed']);
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            'urgent' => 'danger',
            'high' => 'warning',
            'normal' => 'info',
            'low' => 'gray',
            default => 'gray',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'open' => 'warning',
            'in_progress' => 'info',
            'waiting_customer' => 'gray',
            'resolved' => 'success',
            'closed' => 'gray',
            default => 'gray',
        };
    }

    public function getAgeSinceCreatedAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    public function resolve(string $notes = null): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);
    }

    public function assignTo(int $userId): void
    {
        $this->update([
            'assigned_to' => $userId,
            'status' => $this->status === 'open' ? 'in_progress' : $this->status,
        ]);
    }
}
