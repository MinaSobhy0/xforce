<?php

namespace Modules\Billing\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\User;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;

class Payment extends BaseModel
{
    use HasTenancy, HasActivity;

    protected $fillable = [
        'tenant_id',
        'code',
        'invoice_id',
        'journal_id',
        'amount_minor',
        'reference_number',
        'gateway_transaction_id',
        'gift_card_id',
        'received_by_user_id',
        'notes',
        'paid_at',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Payment $payment) {
            if (empty($payment->paid_at)) {
                $payment->paid_at = now();
            }
            // Generate code from journal's sequence
            if (empty($payment->code) && $payment->journal_id) {
                $payment->code = $payment->journal->getNextSequence();
            }
        });

        static::created(function (Payment $payment) {
            // Update invoice paid amount
            if ($payment->invoice) {
                $payment->invoice->recordPayment($payment->amount_minor);
            }

            // Create journal entry for payment
            app(\Modules\Billing\Services\AccountingIntegrationService::class)
                ->createPaymentJournalEntry($payment);
        });
    }

    // Relationships
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    // Accessors
    public function getMethodAttribute(): string
    {
        return $this->journal?->type ?? 'cash';
    }

    public function getMethodLabelAttribute(): string
    {
        return $this->journal?->name ?? 'Cash';
    }

    public function getMethodIconAttribute(): string
    {
        $icons = [
            'cash' => 'heroicon-o-banknotes',
            'bank' => 'heroicon-o-building-library',
            'sales' => 'heroicon-o-credit-card',
            'purchase' => 'heroicon-o-shopping-cart',
            'general' => 'heroicon-o-currency-dollar',
        ];

        return $icons[$this->journal?->type] ?? 'heroicon-o-currency-dollar';
    }

    // Scopes
    public function scopeByJournal($query, string $journalId)
    {
        return $query->where('journal_id', $journalId);
    }

    public function scopeByJournalType($query, string $type)
    {
        return $query->whereHas('journal', fn ($q) => $q->where('type', $type));
    }

    public function scopeForDateRange($query, $start, $end)
    {
        return $query->whereBetween('paid_at', [$start, $end]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('paid_at', today());
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year);
    }
}
