<?php

namespace Modules\Assets\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasSequence;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Models\Branch;
use Modules\Auth\Models\User;
use Modules\Accounting\Models\JournalEntry;
use Carbon\Carbon;

class Asset extends BaseModel
{
    use HasTenancy, HasSequence, HasActivity, HasTranslations, SoftDeletes;

    protected $table = 'assets';

    protected string $sequenceCode = 'asset';
    protected string $sequenceColumn = 'code';

    public array $translatable = ['name'];

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'asset_type_id',
        'branch_id',
        'acquisition_date',
        'acquisition_cost_minor',
        'acquisition_method',
        'purchase_order_id',
        'purchase_order_line_id',
        'product_id',
        'salvage_value_minor',
        'depreciable_value_minor',
        'accumulated_depreciation_minor',
        'book_value_minor',
        'depreciation_start_date',
        'last_depreciation_date',
        'status',
        'disposal_date',
        'disposal_value_minor',
        'disposal_method',
        'disposal_notes',
        'disposal_journal_entry_id',
        'serial_number',
        'location',
        'assigned_to_user_id',
        'notes',
        'acquisition_journal_entry_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'acquisition_date' => 'date',
        'depreciation_start_date' => 'date',
        'last_depreciation_date' => 'date',
        'disposal_date' => 'date',
        'acquisition_cost_minor' => 'integer',
        'salvage_value_minor' => 'integer',
        'depreciable_value_minor' => 'integer',
        'accumulated_depreciation_minor' => 'integer',
        'book_value_minor' => 'integer',
        'disposal_value_minor' => 'integer',
    ];

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FULLY_DEPRECIATED = 'fully_depreciated';
    public const STATUS_DISPOSED = 'disposed';
    public const STATUS_WRITTEN_OFF = 'written_off';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_FULLY_DEPRECIATED => 'Fully Depreciated',
        self::STATUS_DISPOSED => 'Disposed',
        self::STATUS_WRITTEN_OFF => 'Written Off',
    ];

    public const STATUS_COLORS = [
        self::STATUS_DRAFT => 'gray',
        self::STATUS_ACTIVE => 'success',
        self::STATUS_FULLY_DEPRECIATED => 'warning',
        self::STATUS_DISPOSED => 'danger',
        self::STATUS_WRITTEN_OFF => 'danger',
    ];

    // Acquisition method constants
    public const ACQUISITION_PURCHASE = 'purchase';
    public const ACQUISITION_TRANSFER = 'transfer';
    public const ACQUISITION_DONATION = 'donation';
    public const ACQUISITION_FOUND = 'found';

    public const ACQUISITION_METHODS = [
        self::ACQUISITION_PURCHASE => 'Purchase',
        self::ACQUISITION_TRANSFER => 'Transfer',
        self::ACQUISITION_DONATION => 'Donation',
        self::ACQUISITION_FOUND => 'Found/Discovered',
    ];

    // Disposal method constants
    public const DISPOSAL_SALE = 'sale';
    public const DISPOSAL_SCRAP = 'scrap';
    public const DISPOSAL_DONATION = 'donation';
    public const DISPOSAL_THEFT = 'theft';
    public const DISPOSAL_DAMAGE = 'damage';

    public const DISPOSAL_METHODS = [
        self::DISPOSAL_SALE => 'Sale',
        self::DISPOSAL_SCRAP => 'Scrap',
        self::DISPOSAL_DONATION => 'Donation',
        self::DISPOSAL_THEFT => 'Theft',
        self::DISPOSAL_DAMAGE => 'Damage/Loss',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Asset $asset) {
            if (empty($asset->created_by_user_id)) {
                $asset->created_by_user_id = auth()->id();
            }
            if (empty($asset->status)) {
                $asset->status = self::STATUS_DRAFT;
            }
            if (empty($asset->acquisition_method)) {
                $asset->acquisition_method = self::ACQUISITION_PURCHASE;
            }
        });

        static::saving(function (Asset $asset) {
            // Calculate derived values
            if ($asset->assetType) {
                $asset->salvage_value_minor = $asset->assetType->calculateSalvageValue($asset->acquisition_cost_minor);
                $asset->depreciable_value_minor = $asset->acquisition_cost_minor - $asset->salvage_value_minor;
            }
            $asset->book_value_minor = $asset->acquisition_cost_minor - $asset->accumulated_depreciation_minor;
        });
    }

    // Relationships
    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class, 'asset_type_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function depreciationEntries(): HasMany
    {
        return $this->hasMany(AssetDepreciationEntry::class)->orderBy('period_start');
    }

    public function acquisitionJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'acquisition_journal_entry_id');
    }

    public function disposalJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'disposal_journal_entry_id');
    }

    // Scopes
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeDepreciable(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE])
            ->where('book_value_minor', '>', function ($q) {
                $q->selectRaw('COALESCE(salvage_value_minor, 0)');
            });
    }

    public function scopeForBranch(Builder $query, string $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
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

    public function getAcquisitionMethodLabelAttribute(): string
    {
        return self::ACQUISITION_METHODS[$this->acquisition_method] ?? $this->acquisition_method;
    }

    public function getDisposalMethodLabelAttribute(): ?string
    {
        return self::DISPOSAL_METHODS[$this->disposal_method] ?? $this->disposal_method;
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->code} - {$this->name}";
    }

    public function getDepreciationPercentAttribute(): float
    {
        if ($this->depreciable_value_minor <= 0) {
            return 0;
        }
        return round(($this->accumulated_depreciation_minor / $this->depreciable_value_minor) * 100, 1);
    }

    public function getAgeInMonthsAttribute(): int
    {
        if (!$this->acquisition_date) {
            return 0;
        }
        return (int) $this->acquisition_date->diffInMonths(now());
    }

    public function getRemainingLifeMonthsAttribute(): int
    {
        if (!$this->assetType || !$this->depreciation_start_date) {
            return 0;
        }

        $totalMonths = $this->assetType->useful_life_years * 12;
        $elapsedMonths = $this->depreciation_start_date->diffInMonths(now());

        return max(0, $totalMonths - $elapsedMonths);
    }

    // State checks
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isFullyDepreciated(): bool
    {
        return $this->status === self::STATUS_FULLY_DEPRECIATED;
    }

    public function isDisposed(): bool
    {
        return $this->status === self::STATUS_DISPOSED;
    }

    public function canActivate(): bool
    {
        return $this->isDraft() && $this->assetType?->isConfigured();
    }

    public function canDispose(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_FULLY_DEPRECIATED]);
    }

    public function canDepreciate(): bool
    {
        if (!$this->isActive()) {
            return false;
        }
        if (!$this->assetType?->hasDepreciation()) {
            return false;
        }
        return $this->book_value_minor > $this->salvage_value_minor;
    }

    // Depreciation calculations
    public function calculateMonthlyDepreciation(?Carbon $forMonth = null): int
    {
        $forMonth = $forMonth ?? now();

        if (!$this->canDepreciate()) {
            return 0;
        }

        $type = $this->assetType;

        switch ($type->depreciation_method) {
            case AssetType::METHOD_STRAIGHT_LINE:
                return $this->calculateStraightLineDepreciation();

            case AssetType::METHOD_DECLINING_BALANCE:
                return $this->calculateDecliningBalanceDepreciation($forMonth);

            case AssetType::METHOD_SUM_OF_YEARS:
                return $this->calculateSumOfYearsDepreciation($forMonth);

            default:
                return 0;
        }
    }

    protected function calculateStraightLineDepreciation(): int
    {
        $totalMonths = $this->assetType->useful_life_years * 12;
        if ($totalMonths <= 0) {
            return 0;
        }

        $monthlyDepreciation = (int) round($this->depreciable_value_minor / $totalMonths);

        // Don't depreciate below salvage value
        $maxDepreciation = $this->book_value_minor - $this->salvage_value_minor;
        return min($monthlyDepreciation, $maxDepreciation);
    }

    protected function calculateDecliningBalanceDepreciation(Carbon $forMonth): int
    {
        $rate = $this->assetType->declining_balance_rate;
        if (!$rate || $rate <= 0) {
            // Use double declining balance rate if not specified
            $rate = (2 / $this->assetType->useful_life_years) * 100;
        }

        // Annual depreciation = book value at year start × rate
        // Monthly = annual / 12
        $annualDepreciation = (int) round($this->book_value_minor * ($rate / 100));
        $monthlyDepreciation = (int) round($annualDepreciation / 12);

        // Don't depreciate below salvage value
        $maxDepreciation = $this->book_value_minor - $this->salvage_value_minor;
        return min($monthlyDepreciation, max(0, $maxDepreciation));
    }

    protected function calculateSumOfYearsDepreciation(Carbon $forMonth): int
    {
        $usefulLife = $this->assetType->useful_life_years;
        if ($usefulLife <= 0) {
            return 0;
        }

        // Calculate sum of years digits: n(n+1)/2
        $sumOfYears = ($usefulLife * ($usefulLife + 1)) / 2;

        // Calculate remaining years from depreciation start
        $startDate = $this->depreciation_start_date ?? $this->acquisition_date;
        $yearsElapsed = $startDate->diffInYears($forMonth);
        $remainingYears = max(0, $usefulLife - $yearsElapsed);

        if ($remainingYears <= 0 || $sumOfYears <= 0) {
            return 0;
        }

        // Annual depreciation = depreciable value × (remaining years / sum of years)
        $annualDepreciation = (int) round($this->depreciable_value_minor * ($remainingYears / $sumOfYears));
        $monthlyDepreciation = (int) round($annualDepreciation / 12);

        // Don't depreciate below salvage value
        $maxDepreciation = $this->book_value_minor - $this->salvage_value_minor;
        return min($monthlyDepreciation, max(0, $maxDepreciation));
    }

    public function calculateDisposalGainLoss(int $proceedsMinor): int
    {
        // Gain/Loss = Proceeds - Book Value
        // Positive = Gain, Negative = Loss
        return $proceedsMinor - $this->book_value_minor;
    }

    // Generate depreciation schedule for the asset's life
    public function getDepreciationSchedule(): array
    {
        if (!$this->assetType?->hasDepreciation()) {
            return [];
        }

        $schedule = [];
        $startDate = $this->depreciation_start_date ?? $this->acquisition_date?->addMonth()->startOfMonth();

        if (!$startDate) {
            return [];
        }

        $bookValue = $this->acquisition_cost_minor;
        $accumulated = 0;
        $totalMonths = $this->assetType->useful_life_years * 12;

        for ($i = 0; $i < $totalMonths; $i++) {
            $periodDate = $startDate->copy()->addMonths($i);
            $depreciation = $this->calculateMonthlyDepreciationForSchedule($bookValue, $accumulated, $periodDate);

            if ($depreciation <= 0) {
                break;
            }

            $accumulated += $depreciation;
            $bookValue = $this->acquisition_cost_minor - $accumulated;

            $schedule[] = [
                'period' => $periodDate->format('Y-m'),
                'period_start' => $periodDate->copy()->startOfMonth()->toDateString(),
                'period_end' => $periodDate->copy()->endOfMonth()->toDateString(),
                'depreciation_amount_minor' => $depreciation,
                'accumulated_depreciation_minor' => $accumulated,
                'book_value_minor' => $bookValue,
            ];

            if ($bookValue <= $this->salvage_value_minor) {
                break;
            }
        }

        return $schedule;
    }

    protected function calculateMonthlyDepreciationForSchedule(int $bookValue, int $accumulated, Carbon $forMonth): int
    {
        $type = $this->assetType;
        $depreciableValue = $this->acquisition_cost_minor - $this->salvage_value_minor;

        switch ($type->depreciation_method) {
            case AssetType::METHOD_STRAIGHT_LINE:
                $totalMonths = $type->useful_life_years * 12;
                if ($totalMonths <= 0) {
                    return 0;
                }
                $monthly = (int) round($depreciableValue / $totalMonths);
                return min($monthly, $bookValue - $this->salvage_value_minor);

            case AssetType::METHOD_DECLINING_BALANCE:
                $rate = $type->declining_balance_rate ?: ((2 / $type->useful_life_years) * 100);
                $annual = (int) round($bookValue * ($rate / 100));
                $monthly = (int) round($annual / 12);
                return min($monthly, max(0, $bookValue - $this->salvage_value_minor));

            case AssetType::METHOD_SUM_OF_YEARS:
                $usefulLife = $type->useful_life_years;
                $sumOfYears = ($usefulLife * ($usefulLife + 1)) / 2;
                $startDate = $this->depreciation_start_date ?? $this->acquisition_date;
                $yearsElapsed = $startDate->diffInYears($forMonth);
                $remainingYears = max(0, $usefulLife - $yearsElapsed);
                if ($remainingYears <= 0) {
                    return 0;
                }
                $annual = (int) round($depreciableValue * ($remainingYears / $sumOfYears));
                $monthly = (int) round($annual / 12);
                return min($monthly, max(0, $bookValue - $this->salvage_value_minor));

            default:
                return 0;
        }
    }
}
