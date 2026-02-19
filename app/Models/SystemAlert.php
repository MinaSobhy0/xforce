<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemAlert extends Model
{
    use HasUuids;

    protected $fillable = [
        'type',
        'severity',
        'title',
        'message',
        'source',
        'metadata',
        'tenant_id',
        'is_resolved',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public const TYPES = [
        'api_rate_limit' => 'API Rate Limit',
        'storage_warning' => 'Storage Warning',
        'payment_overdue' => 'Payment Overdue',
        'trial_expiring' => 'Trial Expiring',
        'ssl_expiring' => 'SSL Certificate Expiring',
        'queue_backlog' => 'Queue Backlog',
        'error_spike' => 'Error Spike',
        'security' => 'Security Alert',
        'maintenance' => 'Maintenance Required',
        'quota_exceeded' => 'Quota Exceeded',
    ];

    public const SEVERITIES = [
        'critical' => 'Critical',
        'warning' => 'Warning',
        'info' => 'Info',
    ];

    public const SOURCES = [
        'system' => 'System',
        'whatsapp' => 'WhatsApp API',
        'sms' => 'SMS Provider',
        'storage' => 'Storage',
        'queue' => 'Queue/Horizon',
        'billing' => 'Billing',
        'ssl' => 'SSL/DNS',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\Modules\Core\Models\Tenant::class);
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\Models\User::class, 'resolved_by');
    }

    public function resolve(string $userId, ?string $notes = null): void
    {
        $this->update([
            'is_resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => $userId,
            'resolution_notes' => $notes,
        ]);
    }

    public function scopeUnresolved($query)
    {
        return $query->whereRaw('is_resolved = false');
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    public function scopeWarning($query)
    {
        return $query->where('severity', 'warning');
    }

    public function scopeInfo($query)
    {
        return $query->where('severity', 'info');
    }

    public static function createAlert(
        string $type,
        string $severity,
        string $title,
        string $message,
        string $source = 'system',
        ?string $tenantId = null,
        array $metadata = []
    ): self {
        return self::create([
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'source' => $source,
            'tenant_id' => $tenantId,
            'metadata' => $metadata,
        ]);
    }
}
