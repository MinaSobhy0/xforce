<?php

namespace Modules\Evaluations\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\User;
use Modules\Booking\Models\Visit;
use Modules\Core\Models\Branch;
use Modules\Patients\Models\Patient;
use XLinic\Framework\Core\Model\BaseModel;

class Evaluation extends BaseModel
{
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'visit_id',
        'patient_id',
        'evaluated_by',
        'overall_rating',
        'service_quality_rating',
        'staff_friendliness_rating',
        'cleanliness_rating',
        'wait_time_rating',
        'value_for_money_rating',
        'satisfaction_score',
        'would_recommend',
        'feedback_text',
        'improvement_suggestions',
        'source',
        'metadata',
    ];

    protected $casts = [
        'overall_rating' => 'integer',
        'service_quality_rating' => 'integer',
        'staff_friendliness_rating' => 'integer',
        'cleanliness_rating' => 'integer',
        'wait_time_rating' => 'integer',
        'value_for_money_rating' => 'integer',
        'satisfaction_score' => 'integer',
        'would_recommend' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Source constants
    public const SOURCE_STAFF = 'staff';

    public const SOURCE_KIOSK = 'kiosk';

    public const SOURCE_SMS = 'sms';

    public const SOURCE_EMAIL = 'email';

    public const SOURCE_PORTAL = 'portal';

    public const SOURCES = [
        self::SOURCE_STAFF => 'Staff Entry',
        self::SOURCE_KIOSK => 'In-Clinic Kiosk',
        self::SOURCE_SMS => 'SMS Survey',
        self::SOURCE_EMAIL => 'Email Survey',
        self::SOURCE_PORTAL => 'Patient Portal',
    ];

    // Rating labels
    public const RATING_LABELS = [
        1 => 'Very Poor',
        2 => 'Poor',
        3 => 'Average',
        4 => 'Good',
        5 => 'Excellent',
    ];

    public const RATING_COLORS = [
        1 => 'danger',
        2 => 'warning',
        3 => 'gray',
        4 => 'info',
        5 => 'success',
    ];

    // NPS categories
    public const NPS_DETRACTOR = 'detractor';

    public const NPS_PASSIVE = 'passive';

    public const NPS_PROMOTER = 'promoter';

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Evaluation $evaluation) {
            if (empty($evaluation->source)) {
                $evaluation->source = self::SOURCE_STAFF;
            }
            if (empty($evaluation->evaluated_by) && auth()->check()) {
                $evaluation->evaluated_by = auth()->id();
            }
        });
    }

    // Relationships
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function evaluatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    // Accessors
    public function getRatingLabelAttribute(): string
    {
        return self::RATING_LABELS[$this->overall_rating] ?? 'Unknown';
    }

    public function getRatingColorAttribute(): string
    {
        return self::RATING_COLORS[$this->overall_rating] ?? 'gray';
    }

    public function getSourceLabelAttribute(): string
    {
        return self::SOURCES[$this->source] ?? $this->source;
    }

    public function getEvaluatedByNameAttribute(): ?string
    {
        return $this->evaluatedBy?->name;
    }

    public function getPatientNameAttribute(): ?string
    {
        return $this->patient?->full_name;
    }

    /**
     * Get NPS category based on satisfaction_score
     */
    public function getNpsCategoryAttribute(): ?string
    {
        if ($this->satisfaction_score === null) {
            return null;
        }

        $detractorMax = config('evaluations.nps.detractor_max', 6);
        $passiveMax = config('evaluations.nps.passive_max', 8);

        if ($this->satisfaction_score <= $detractorMax) {
            return self::NPS_DETRACTOR;
        }

        if ($this->satisfaction_score <= $passiveMax) {
            return self::NPS_PASSIVE;
        }

        return self::NPS_PROMOTER;
    }

    public function getNpsCategoryLabelAttribute(): ?string
    {
        return match ($this->nps_category) {
            self::NPS_DETRACTOR => __('evaluations::evaluations.nps.detractor'),
            self::NPS_PASSIVE => __('evaluations::evaluations.nps.passive'),
            self::NPS_PROMOTER => __('evaluations::evaluations.nps.promoter'),
            default => null,
        };
    }

    public function getNpsCategoryColorAttribute(): ?string
    {
        return match ($this->nps_category) {
            self::NPS_DETRACTOR => 'danger',
            self::NPS_PASSIVE => 'warning',
            self::NPS_PROMOTER => 'success',
            default => null,
        };
    }

    /**
     * Calculate average of all rating fields
     */
    public function getAverageRatingAttribute(): float
    {
        $ratings = array_filter([
            $this->overall_rating,
            $this->service_quality_rating,
            $this->staff_friendliness_rating,
            $this->cleanliness_rating,
            $this->wait_time_rating,
            $this->value_for_money_rating,
        ], fn ($r) => $r !== null);

        if (empty($ratings)) {
            return 0;
        }

        return round(array_sum($ratings) / count($ratings), 1);
    }

    /**
     * Check if evaluation has detailed ratings
     */
    public function getHasDetailedRatingsAttribute(): bool
    {
        return $this->service_quality_rating !== null
            || $this->staff_friendliness_rating !== null
            || $this->cleanliness_rating !== null
            || $this->wait_time_rating !== null
            || $this->value_for_money_rating !== null;
    }

    /**
     * Check if evaluation has feedback
     */
    public function getHasFeedbackAttribute(): bool
    {
        return ! empty($this->feedback_text) || ! empty($this->improvement_suggestions);
    }

    // State checks
    public function isPositive(): bool
    {
        return $this->overall_rating >= 4;
    }

    public function isNegative(): bool
    {
        return $this->overall_rating <= 2;
    }

    public function isNeutral(): bool
    {
        return $this->overall_rating === 3;
    }

    public function isPromoter(): bool
    {
        return $this->nps_category === self::NPS_PROMOTER;
    }

    public function isDetractor(): bool
    {
        return $this->nps_category === self::NPS_DETRACTOR;
    }

    public function isPassive(): bool
    {
        return $this->nps_category === self::NPS_PASSIVE;
    }

    // Scopes
    public function scopePositive($query)
    {
        return $query->where('overall_rating', '>=', 4);
    }

    public function scopeNegative($query)
    {
        return $query->where('overall_rating', '<=', 2);
    }

    public function scopeNeutral($query)
    {
        return $query->where('overall_rating', 3);
    }

    public function scopePromoters($query)
    {
        $passiveMax = config('evaluations.nps.passive_max', 8);

        return $query->whereNotNull('satisfaction_score')
            ->where('satisfaction_score', '>', $passiveMax);
    }

    public function scopeDetractors($query)
    {
        $detractorMax = config('evaluations.nps.detractor_max', 6);

        return $query->whereNotNull('satisfaction_score')
            ->where('satisfaction_score', '<=', $detractorMax);
    }

    public function scopePassives($query)
    {
        $detractorMax = config('evaluations.nps.detractor_max', 6);
        $passiveMax = config('evaluations.nps.passive_max', 8);

        return $query->whereNotNull('satisfaction_score')
            ->where('satisfaction_score', '>', $detractorMax)
            ->where('satisfaction_score', '<=', $passiveMax);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeInPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeWithFeedback($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('feedback_text')
                ->orWhereNotNull('improvement_suggestions');
        });
    }

    public function scopeForPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }

    public function scopeByRating($query, int $rating)
    {
        return $query->where('overall_rating', $rating);
    }
}
