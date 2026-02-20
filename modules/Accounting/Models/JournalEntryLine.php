<?php

namespace Modules\Accounting\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Core\Models\Branch;

class JournalEntryLine extends BaseModel
{
    use HasTenancy;

    protected $table = 'journal_entry_lines';

    protected $fillable = [
        'tenant_id',
        'journal_entry_id',
        'account_id',
        'debit_minor',
        'credit_minor',
        'description',
        'branch_id',
        'partner_type',
        'partner_id',
    ];

    protected $casts = [
        'debit_minor' => 'integer',
        'credit_minor' => 'integer',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::saved(function (JournalEntryLine $line) {
            $line->journalEntry?->recalculateTotals();
        });

        static::deleted(function (JournalEntryLine $line) {
            $line->journalEntry?->recalculateTotals();
        });
    }

    // Relationships
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    // Polymorphic partner (e.g., Patient, Vendor)
    public function partner(): MorphTo
    {
        return $this->morphTo();
    }

    // Get net amount (debit positive, credit negative)
    public function getNetAmountAttribute(): int
    {
        return $this->debit_minor - $this->credit_minor;
    }

    // Check if this is a debit line
    public function isDebit(): bool
    {
        return $this->debit_minor > 0;
    }

    // Check if this is a credit line
    public function isCredit(): bool
    {
        return $this->credit_minor > 0;
    }
}
