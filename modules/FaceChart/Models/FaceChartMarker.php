<?php

namespace Modules\FaceChart\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaceChartMarker extends BaseModel
{
    use HasTenancy, HasActivity;

    /**
     * Face regions for categorization.
     */
    public const REGIONS = [
        'forehead',
        'glabella',
        'temples',
        'crow_feet',
        'upper_eyelid',
        'lower_eyelid',
        'nose',
        'cheeks',
        'nasolabial',
        'upper_lip',
        'lower_lip',
        'marionette',
        'chin',
        'jawline',
        'neck',
    ];

    /**
     * Marker types for different procedures.
     */
    public const MARKER_TYPES = [
        'injection',
        'filler_point',
        'laser_spot',
        'thread_anchor',
        'marking',
    ];

    /**
     * Unit types for dosage.
     */
    public const UNIT_TYPES = [
        'units',
        'ml',
        'cc',
    ];

    /**
     * Disable auto branch assignment since this is patient-centric.
     */
    protected bool $autoSetBranchId = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'patient_id',
        'treatment_plan_item_id',
        'appointment_id',
        'service_id',
        'service_category_id',
        'x',
        'y',
        'z',
        'direction_x',
        'direction_y',
        'direction_z',
        'depth_mm',
        'face_region',
        'marker_type',
        'product_name',
        'units',
        'unit_type',
        'color',
        'size',
        'notes',
        'metadata',
        'performed_by',
        'performed_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'x' => 'decimal:6',
        'y' => 'decimal:6',
        'z' => 'decimal:6',
        'direction_x' => 'decimal:6',
        'direction_y' => 'decimal:6',
        'direction_z' => 'decimal:6',
        'depth_mm' => 'decimal:2',
        'units' => 'decimal:2',
        'size' => 'decimal:2',
        'metadata' => 'array',
        'performed_at' => 'date',
    ];

    /**
     * Get the patient this marker belongs to.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(\Modules\Patients\Models\Patient::class);
    }

    /**
     * Get the treatment plan item this marker is linked to.
     */
    public function treatmentPlanItem(): BelongsTo
    {
        return $this->belongsTo(\Modules\TreatmentPlans\Models\TreatmentPlanItem::class);
    }

    /**
     * Get the appointment this marker was created during.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(\Modules\Booking\Models\Appointment::class);
    }

    /**
     * Get the service this marker is associated with.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(\Modules\Services\Models\Service::class);
    }

    /**
     * Get the service category for icon/visual grouping.
     */
    public function serviceCategory(): BelongsTo
    {
        return $this->belongsTo(\Modules\Services\Models\ServiceCategory::class);
    }

    /**
     * Get the user who performed/created this marker.
     */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\Models\User::class, 'performed_by');
    }

    /**
     * Get the region label (translated).
     */
    public function getRegionLabelAttribute(): ?string
    {
        if (!$this->face_region) {
            return null;
        }

        return __("face_chart::face_chart.regions.{$this->face_region}");
    }

    /**
     * Get the marker type label (translated).
     */
    public function getTypeLabelAttribute(): string
    {
        return __("face_chart::face_chart.marker_types.{$this->marker_type}");
    }

    /**
     * Get coordinates as array.
     */
    public function getCoordinatesAttribute(): array
    {
        return [
            'x' => (float) $this->x,
            'y' => (float) $this->y,
            'z' => (float) $this->z,
        ];
    }

    /**
     * Get formatted dosage string.
     */
    public function getFormattedDosageAttribute(): ?string
    {
        if (!$this->units) {
            return null;
        }

        $unitLabel = $this->unit_type
            ? __("face_chart::face_chart.unit_types.{$this->unit_type}")
            : '';

        return trim("{$this->units} {$unitLabel}");
    }

    /**
     * Get marker data for JavaScript/Three.js.
     */
    public function toMarkerData(): array
    {
        // Get category icon and color
        $categoryIcon = $this->serviceCategory?->icon ?? $this->service?->category?->icon ?? 'circle';
        $categoryColor = $this->serviceCategory?->color ?? $this->service?->category?->color ?? $this->color;

        return [
            'id' => $this->id,
            'x' => (float) $this->x,
            'y' => (float) $this->y,
            'z' => (float) $this->z,
            // Direction for injection line
            'directionX' => $this->direction_x ? (float) $this->direction_x : null,
            'directionY' => $this->direction_y ? (float) $this->direction_y : null,
            'directionZ' => $this->direction_z ? (float) $this->direction_z : null,
            'depthMm' => $this->depth_mm ? (float) $this->depth_mm : null,
            // Visual properties
            'color' => $categoryColor ?? $this->color,
            'size' => (float) ($this->size ?? 1),
            'icon' => $categoryIcon,
            'categoryId' => $this->service_category_id,
            'categoryName' => $this->serviceCategory?->translated_name ?? $this->service?->category?->translated_name,
            // Marker details
            'region' => $this->face_region,
            'type' => $this->marker_type,
            'product' => $this->product_name,
            'dosage' => $this->formatted_dosage,
            'units' => $this->units ? (float) $this->units : null,
            'unitType' => $this->unit_type,
            'notes' => $this->notes,
            // Service info
            'serviceId' => $this->service_id,
            'serviceName' => $this->service?->translated_name,
            // Metadata
            'performedAt' => $this->performed_at?->format('Y-m-d'),
            'appointmentId' => $this->appointment_id,
            'isEditable' => false, // Will be set by component
        ];
    }

    /**
     * Check if marker has direction data.
     */
    public function hasDirection(): bool
    {
        return $this->direction_x !== null
            && $this->direction_y !== null
            && $this->direction_z !== null;
    }

    /**
     * Get direction as array.
     */
    public function getDirectionAttribute(): ?array
    {
        if (!$this->hasDirection()) {
            return null;
        }

        return [
            'x' => (float) $this->direction_x,
            'y' => (float) $this->direction_y,
            'z' => (float) $this->direction_z,
        ];
    }

    /**
     * Scope: Filter by patient.
     */
    public function scopeForPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    /**
     * Scope: Filter by appointment.
     */
    public function scopeForAppointment($query, int $appointmentId)
    {
        return $query->where('appointment_id', $appointmentId);
    }

    /**
     * Scope: Filter by date range.
     */
    public function scopeInDateRange($query, ?string $from = null, ?string $to = null)
    {
        if ($from) {
            $query->where('performed_at', '>=', $from);
        }
        if ($to) {
            $query->where('performed_at', '<=', $to);
        }
        return $query;
    }

    /**
     * Scope: Filter by region.
     */
    public function scopeInRegion($query, string $region)
    {
        return $query->where('face_region', $region);
    }

    /**
     * Scope: Filter by marker type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('marker_type', $type);
    }

    /**
     * Scope: Order by most recent first.
     */
    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('performed_at')->orderByDesc('created_at');
    }
}
