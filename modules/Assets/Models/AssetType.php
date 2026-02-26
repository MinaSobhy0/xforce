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
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Auth\Models\User;

class AssetType extends BaseModel
{
    use HasTenancy, HasSequence, HasActivity, HasTranslations, SoftDeletes;

    protected $table = 'asset_types';

    protected string $sequenceCode = 'asset_type';
    protected string $sequenceColumn = 'code';

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'depreciation_method',
        'useful_life_years',
        'salvage_value_percent',
        'declining_balance_rate',
        'fixed_asset_account_id',
        'accumulated_depreciation_account_id',
        'depreciation_expense_account_id',
        'gain_loss_account_id',
        'auto_create_on_purchase',
        'is_active',
        'created_by_user_id',
    ];

    protected $casts = [
        'useful_life_years' => 'integer',
        'salvage_value_percent' => 'decimal:2',
        'declining_balance_rate' => 'decimal:2',
        'auto_create_on_purchase' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Depreciation method constants
    public const METHOD_STRAIGHT_LINE = 'straight_line';
    public const METHOD_DECLINING_BALANCE = 'declining_balance';
    public const METHOD_SUM_OF_YEARS = 'sum_of_years';
    public const METHOD_NO_DEPRECIATION = 'no_depreciation';

    public const DEPRECIATION_METHODS = [
        self::METHOD_STRAIGHT_LINE => 'Straight Line',
        self::METHOD_DECLINING_BALANCE => 'Declining Balance',
        self::METHOD_SUM_OF_YEARS => 'Sum of Years Digits',
        self::METHOD_NO_DEPRECIATION => 'No Depreciation',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (AssetType $type) {
            if (empty($type->created_by_user_id)) {
                $type->created_by_user_id = auth()->id();
            }
            if (empty($type->depreciation_method)) {
                $type->depreciation_method = self::METHOD_STRAIGHT_LINE;
            }
        });
    }

    // Relationships
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'asset_type_id');
    }

    public function fixedAssetAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'fixed_asset_account_id');
    }

    public function accumulatedDepreciationAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'accumulated_depreciation_account_id');
    }

    public function depreciationExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'depreciation_expense_account_id');
    }

    public function gainLossAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'gain_loss_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeAutoCreate(Builder $query): Builder
    {
        return $query->where('auto_create_on_purchase', true);
    }

    // Accessors
    public function getDepreciationMethodLabelAttribute(): string
    {
        return self::DEPRECIATION_METHODS[$this->depreciation_method] ?? $this->depreciation_method;
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->code} - {$this->name}";
    }

    // Methods
    public function hasDepreciation(): bool
    {
        return $this->depreciation_method !== self::METHOD_NO_DEPRECIATION;
    }

    public function calculateSalvageValue(int $acquisitionCostMinor): int
    {
        if ($this->salvage_value_percent <= 0) {
            return 0;
        }
        return (int) round($acquisitionCostMinor * ($this->salvage_value_percent / 100));
    }

    public function calculateDepreciableValue(int $acquisitionCostMinor): int
    {
        return $acquisitionCostMinor - $this->calculateSalvageValue($acquisitionCostMinor);
    }

    public function isConfigured(): bool
    {
        if (!$this->hasDepreciation()) {
            return (bool) $this->fixed_asset_account_id;
        }

        return $this->fixed_asset_account_id
            && $this->accumulated_depreciation_account_id
            && $this->depreciation_expense_account_id;
    }

    public function getStatistics(): array
    {
        $assets = $this->assets();

        return [
            'total_assets' => $assets->count(),
            'active_assets' => (clone $assets)->where('status', Asset::STATUS_ACTIVE)->count(),
            'draft_assets' => (clone $assets)->where('status', Asset::STATUS_DRAFT)->count(),
            'disposed_assets' => (clone $assets)->where('status', Asset::STATUS_DISPOSED)->count(),
            'total_acquisition_value' => $assets->sum('acquisition_cost_minor'),
            'total_book_value' => $assets->sum('book_value_minor'),
            'total_accumulated_depreciation' => $assets->sum('accumulated_depreciation_minor'),
        ];
    }
}
