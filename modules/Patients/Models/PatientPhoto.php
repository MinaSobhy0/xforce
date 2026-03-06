<?php

namespace Modules\Patients\Models;

use XLinic\Framework\Core\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PatientPhoto extends BaseModel implements HasMedia
{
    use InteractsWithMedia;

    /**
     * The table associated with the model.
     */
    protected $table = 'patient_photos';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'patient_id',
        'appointment_id',
        'treatment_id',
        'type',
        'body_area',
        'description',
        'taken_at',
        'taken_by',
        'is_private',
        'tags',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'taken_at' => 'datetime',
        'is_private' => 'boolean',
        'tags' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Photo types.
     */
    public const TYPES = [
        'before' => 'Before Service',
        'after' => 'After Service',
        'during' => 'During Service',
        'consultation' => 'Consultation',
        'progress' => 'Progress',
        'reaction' => 'Reaction/Side Effect',
    ];

    /**
     * Body areas.
     */
    public const BODY_AREAS = [
        'face_full' => 'Full Face',
        'face_left' => 'Left Face',
        'face_right' => 'Right Face',
        'forehead' => 'Forehead',
        'cheeks' => 'Cheeks',
        'nose' => 'Nose',
        'chin' => 'Chin',
        'neck' => 'Neck',
        'chest' => 'Chest',
        'back' => 'Back',
        'abdomen' => 'Abdomen',
        'arms' => 'Arms',
        'underarms' => 'Underarms',
        'hands' => 'Hands',
        'legs' => 'Legs',
        'bikini' => 'Bikini Area',
        'full_body' => 'Full Body',
        'other' => 'Other',
    ];

    /**
     * Register media collections.
     * Uses 'tenant' disk to ensure tenant-isolated storage.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->useDisk('tenant')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
            ->singleFile();
    }

    /**
     * Register media conversions.
     */
    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(200)
            ->height(200)
            ->sharpen(10);

        $this->addMediaConversion('preview')
            ->width(800)
            ->height(800)
            ->sharpen(10);
    }

    /**
     * Get the patient.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the appointment (if linked).
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(\Modules\Booking\Models\Appointment::class);
    }

    /**
     * Get the service (if linked).
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(\Modules\Services\Models\Service::class);
    }

    /**
     * Get the user who took the photo.
     */
    public function takenBy(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\Models\User::class, 'taken_by');
    }

    /**
     * Get photo URL.
     */
    public function getUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('photos');
    }

    /**
     * Get thumbnail URL.
     */
    public function getThumbUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('photos', 'thumb');
    }

    /**
     * Get preview URL.
     */
    public function getPreviewUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('photos', 'preview');
    }

    /**
     * Get type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Get body area label.
     */
    public function getBodyAreaLabelAttribute(): string
    {
        return self::BODY_AREAS[$this->body_area] ?? $this->body_area;
    }

    /**
     * Scope: By type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: By body area.
     */
    public function scopeForBodyArea($query, string $area)
    {
        return $query->where('body_area', $area);
    }

    /**
     * Scope: Before photos only.
     */
    public function scopeBefore($query)
    {
        return $query->where('type', 'before');
    }

    /**
     * Scope: After photos only.
     */
    public function scopeAfter($query)
    {
        return $query->where('type', 'after');
    }

    /**
     * Scope: Public photos only.
     */
    public function scopePublic($query)
    {
        return $query->where('is_private', false);
    }

    /**
     * Scope: For a specific service.
     */
    public function scopeForService($query, string $serviceId)
    {
        return $query->where('service_id', $serviceId);
    }

    /**
     * Get paired photos (before/after for same area and service).
     */
    public function getPairedPhoto(): ?self
    {
        $oppositeType = $this->type === 'before' ? 'after' : 'before';

        return static::where('patient_id', $this->patient_id)
            ->where('body_area', $this->body_area)
            ->where('service_id', $this->service_id)
            ->where('type', $oppositeType)
            ->orderByDesc('taken_at')
            ->first();
    }
}
