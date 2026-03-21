<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;
use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;

class Journal extends BaseModel
{
    use HasTenancy;
    use HasTranslations;

    protected $table = 'journals';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'type',
        'default_debit_account_id',
        'default_credit_account_id',
        'sequence_prefix',
        'next_sequence',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'next_sequence' => 'integer',
    ];

    public array $translatable = ['name'];

    // Journal types
    public const TYPE_SALES = 'sales';

    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_CASH = 'cash';

    public const TYPE_BANK = 'bank';

    public const TYPE_GENERAL = 'general';

    public const TYPE_GIFT_CARD = 'gift_card';

    public const TYPES = [
        self::TYPE_SALES => 'Sales',
        self::TYPE_PURCHASE => 'Purchase',
        self::TYPE_CASH => 'Cash',
        self::TYPE_BANK => 'Bank',
        self::TYPE_GENERAL => 'Miscellaneous',
        self::TYPE_GIFT_CARD => 'Gift Card',
    ];

    public const TYPE_COLORS = [
        self::TYPE_SALES => 'success',
        self::TYPE_PURCHASE => 'warning',
        self::TYPE_CASH => 'info',
        self::TYPE_BANK => 'primary',
        self::TYPE_GENERAL => 'gray',
        self::TYPE_GIFT_CARD => 'danger',
    ];

    // Relationships
    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function defaultDebitAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'default_debit_account_id');
    }

    public function defaultCreditAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'default_credit_account_id');
    }

    // Generate next sequence number for this journal
    public function getNextSequence(): string
    {
        $number = $this->next_sequence;
        $this->increment('next_sequence');

        $year = now()->format('Y');
        $paddedNumber = str_pad($number, 5, '0', STR_PAD_LEFT);

        return "{$this->sequence_prefix}/{$year}/{$paddedNumber}";
    }

    // Accessors
    public function getTranslatedNameAttribute(): string
    {
        return $this->getTranslation('name', app()->getLocale())
            ?? $this->getTranslation('name', 'en')
            ?? $this->name
            ?? '';
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getTypeColorAttribute(): string
    {
        return self::TYPE_COLORS[$this->type] ?? 'gray';
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->code} - {$this->name}";
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // Get default journal by type
    public static function getByType(string $type): ?self
    {
        return static::active()->ofType($type)->first();
    }

    public static function getSalesJournal(): ?self
    {
        return static::getByType(self::TYPE_SALES);
    }

    public static function getPurchaseJournal(): ?self
    {
        return static::getByType(self::TYPE_PURCHASE);
    }

    public static function getCashJournal(): ?self
    {
        return static::getByType(self::TYPE_CASH);
    }

    public static function getBankJournal(): ?self
    {
        return static::getByType(self::TYPE_BANK);
    }

    public static function getMiscJournal(): ?self
    {
        return static::getByType(self::TYPE_GENERAL);
    }

    public static function getGiftCardJournal(): ?self
    {
        return static::getByType(self::TYPE_GIFT_CARD);
    }

    /**
     * Get journals that can be used for payments (receiving money).
     */
    public static function getPaymentJournals()
    {
        return static::active()
            ->whereIn('type', [self::TYPE_CASH, self::TYPE_BANK, self::TYPE_GIFT_CARD])
            ->get();
    }
}
