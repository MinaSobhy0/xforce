<?php

namespace Modules\Accounting\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Auth\Models\User;

class JournalEntry extends BaseModel
{
    use HasTenancy, HasActivity;

    protected $table = 'journal_entries';

    protected $fillable = [
        'tenant_id',
        'journal_id',
        'code',
        'date',
        'reference',
        'description',
        'source_type',
        'source_id',
        'status',
        'total_debit_minor',
        'total_credit_minor',
        'fiscal_period_id',
        'created_by_user_id',
        'posted_at',
        'reversed_by_id',
        'reversal_of_id',
    ];

    protected $casts = [
        'date' => 'date',
        'total_debit_minor' => 'integer',
        'total_credit_minor' => 'integer',
        'posted_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_POSTED => 'Posted',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const STATUS_COLORS = [
        self::STATUS_DRAFT => 'gray',
        self::STATUS_POSTED => 'success',
        self::STATUS_CANCELLED => 'danger',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (JournalEntry $entry) {
            if (empty($entry->status)) {
                $entry->status = self::STATUS_DRAFT;
            }
            if (empty($entry->date)) {
                $entry->date = now();
            }
            // Generate code from journal's sequence
            if (empty($entry->code) && $entry->journal_id) {
                $entry->code = $entry->journal->getNextSequence();
            }
        });
    }

    // Relationships
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // Polymorphic source (e.g., Invoice, Payment)
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    // Reversal relationships
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversed_by_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_of_id');
    }

    // Check if entry is balanced
    public function isBalanced(): bool
    {
        return $this->total_debit_minor === $this->total_credit_minor;
    }

    // Recalculate totals from lines
    public function recalculateTotals(): void
    {
        $this->total_debit_minor = $this->lines()->sum('debit_minor');
        $this->total_credit_minor = $this->lines()->sum('credit_minor');
        $this->save();
    }

    // State machine
    public function canTransitionTo(string $status): bool
    {
        $transitions = [
            self::STATUS_DRAFT => [self::STATUS_POSTED, self::STATUS_CANCELLED],
            self::STATUS_POSTED => [], // Cannot change status, only reverse
            self::STATUS_CANCELLED => [],
        ];

        return in_array($status, $transitions[$this->status] ?? []);
    }

    public function transitionTo(string $status): bool
    {
        if (!$this->canTransitionTo($status)) {
            return false;
        }

        $this->status = $status;

        if ($status === self::STATUS_POSTED) {
            $this->posted_at = now();
        }

        return $this->save();
    }

    // Post the journal entry
    public function post(): bool
    {
        // Validate balance
        if (!$this->isBalanced()) {
            return false;
        }

        // Validate fiscal period is open
        if ($this->fiscalPeriod && !$this->fiscalPeriod->isOpen()) {
            return false;
        }

        $result = $this->transitionTo(self::STATUS_POSTED);

        if ($result) {
            // Update account balances
            foreach ($this->lines as $line) {
                $line->account->updateCachedBalance();
            }
        }

        return $result;
    }

    // Create reversal entry
    public function reverse(?string $description = null): ?JournalEntry
    {
        if ($this->status !== self::STATUS_POSTED) {
            return null;
        }

        $reversal = self::create([
            'tenant_id' => $this->tenant_id,
            'date' => now(),
            'reference' => 'REV-' . $this->code,
            'description' => $description ?? 'Reversal of ' . $this->code,
            'reversal_of_id' => $this->id,
            'fiscal_period_id' => $this->fiscal_period_id,
            'created_by_user_id' => auth()->id(),
        ]);

        // Create reversed lines
        foreach ($this->lines as $line) {
            $reversal->lines()->create([
                'tenant_id' => $this->tenant_id,
                'account_id' => $line->account_id,
                'debit_minor' => $line->credit_minor, // Swap
                'credit_minor' => $line->debit_minor, // Swap
                'description' => $line->description,
                'branch_id' => $line->branch_id,
            ]);
        }

        $reversal->recalculateTotals();
        $reversal->post();

        // Mark this entry as reversed
        $this->reversed_by_id = $reversal->id;
        $this->save();

        return $reversal;
    }

    // State checks
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isReversed(): bool
    {
        return $this->reversed_by_id !== null;
    }

    public function isReversal(): bool
    {
        return $this->reversal_of_id !== null;
    }

    // Accessors
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    // Scopes
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopePosted($query)
    {
        return $query->where('status', self::STATUS_POSTED);
    }

    public function scopeForPeriod($query, string $periodId)
    {
        return $query->where('fiscal_period_id', $periodId);
    }

    public function scopeForDateRange($query, $start, $end)
    {
        return $query->whereBetween('date', [$start, $end]);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('code', 'ilike', "%{$term}%")
                ->orWhere('reference', 'ilike', "%{$term}%")
                ->orWhere('description', 'ilike', "%{$term}%");
        });
    }
}
