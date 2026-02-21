<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\Tenant;

class PlatformInvoice extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'central';

    protected $table = 'public.platform_invoices';

    protected $fillable = [
        'tenant_id',
        'number',
        'period_start',
        'period_end',
        'plan_code',
        'plan_charge_minor',
        'addon_charges_minor',
        'overage_charges_minor',
        'discount_minor',
        'discount_code',
        'subtotal_minor',
        'tax_minor',
        'tax_rate',
        'total_minor',
        'currency',
        'status',
        'due_date',
        'paid_at',
        'payment_method',
        'payment_reference',
        'notes',
        'line_items',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'plan_charge_minor' => 'integer',
        'addon_charges_minor' => 'integer',
        'overage_charges_minor' => 'integer',
        'discount_minor' => 'integer',
        'subtotal_minor' => 'integer',
        'tax_minor' => 'integer',
        'tax_rate' => 'decimal:4',
        'total_minor' => 'integer',
        'line_items' => 'array',
    ];

    public const STATUSES = [
        'draft' => 'Draft',
        'pending' => 'Pending',
        'paid' => 'Paid',
        'overdue' => 'Overdue',
        'refunded' => 'Refunded',
        'cancelled' => 'Cancelled',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $invoice) {
            if (empty($invoice->number)) {
                $lastNumber = static::whereYear('created_at', now()->year)
                    ->max('number');
                $nextNumber = $lastNumber ? (int) substr($lastNumber, -5) + 1 : 1;
                $invoice->number = 'PLT-' . now()->format('Y') . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('period_start', now()->month)
            ->whereYear('period_start', now()->year);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'paid' => 'success',
            'pending' => 'warning',
            'overdue' => 'danger',
            'refunded' => 'info',
            'draft' => 'gray',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    public function getFormattedTotalAttribute(): string
    {
        return number_format($this->total_minor / 100, 2) . ' ' . ($this->currency ?? 'EGP');
    }

    public function markAsPaid(string $paymentReference = null): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_reference' => $paymentReference,
        ]);
    }

    public function markAsOverdue(): void
    {
        if ($this->status === 'pending' && $this->due_date?->isPast()) {
            $this->update(['status' => 'overdue']);
        }
    }
}
