<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'bio',
        'website',
        'linkedin',
        'twitter',
        'facebook',
        'skills',
        'certifications',
        'languages',
        'notes',
        'custom_fields',
    ];

    protected $casts = [
        'skills' => 'array',
        'certifications' => 'array',
        'languages' => 'array',
        'custom_fields' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get a custom field value.
     */
    public function getCustomField(string $key, $default = null)
    {
        return data_get($this->custom_fields, $key, $default);
    }

    /**
     * Set a custom field value.
     */
    public function setCustomField(string $key, $value): void
    {
        $customFields = $this->custom_fields ?? [];
        data_set($customFields, $key, $value);
        $this->update(['custom_fields' => $customFields]);
    }

    /**
     * Add a skill.
     */
    public function addSkill(string $skill): void
    {
        $skills = $this->skills ?? [];
        if (!in_array($skill, $skills)) {
            $skills[] = $skill;
            $this->update(['skills' => $skills]);
        }
    }

    /**
     * Remove a skill.
     */
    public function removeSkill(string $skill): void
    {
        $skills = array_filter($this->skills ?? [], fn($s) => $s !== $skill);
        $this->update(['skills' => array_values($skills)]);
    }

    /**
     * Add a certification.
     */
    public function addCertification(array $certification): void
    {
        $certifications = $this->certifications ?? [];
        $certifications[] = $certification;
        $this->update(['certifications' => $certifications]);
    }

    /**
     * Get social links as array.
     */
    public function getSocialLinksAttribute(): array
    {
        return array_filter([
            'website' => $this->website,
            'linkedin' => $this->linkedin,
            'twitter' => $this->twitter,
            'facebook' => $this->facebook,
        ]);
    }

    /**
     * Check if profile is complete.
     */
    public function isComplete(): bool
    {
        return !empty($this->bio) && !empty($this->skills);
    }

    /**
     * Get completion percentage.
     */
    public function getCompletionPercentage(): int
    {
        $fields = ['bio', 'website', 'linkedin', 'skills', 'languages'];
        $filled = 0;

        foreach ($fields as $field) {
            if (!empty($this->$field)) {
                $filled++;
            }
        }

        return (int) round(($filled / count($fields)) * 100);
    }
}
