<?php

namespace Modules\Services\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasTranslation;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsentTemplate extends BaseModel
{
    use HasTenancy, HasTranslation, HasActivity;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'content',
        'version',
        'valid_days',
        'is_active',
        'requires_witness',
        'requires_patient_signature',
        'metadata',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'content' => 'array',
        'is_active' => 'boolean',
        'requires_witness' => 'boolean',
        'requires_patient_signature' => 'boolean',
        'valid_days' => 'integer',
        'metadata' => 'array',
    ];

    public array $translatable = ['name', 'description', 'content'];

    protected $appends = ['translated_name'];

    public function getTranslatedNameAttribute(): string
    {
        return $this->getTranslation('name', app()->getLocale())
            ?? $this->getTranslation('name', 'en')
            ?? '';
    }

    public function getTranslatedContentAttribute(): string
    {
        return $this->getTranslation('content', app()->getLocale())
            ?? $this->getTranslation('content', 'en')
            ?? '';
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function signedForms(): HasMany
    {
        return $this->hasMany(\Modules\Patients\Models\PatientConsentForm::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function incrementVersion(): void
    {
        $parts = explode('.', $this->version);
        $minor = (int) ($parts[1] ?? 0) + 1;
        $major = (int) ($parts[0] ?? 1);

        if ($minor >= 10) {
            $major++;
            $minor = 0;
        }

        $this->version = "{$major}.{$minor}";
        $this->save();
    }

    public function duplicate(): self
    {
        $new = $this->replicate();
        $new->name = array_map(fn ($v) => $v . ' (Copy)', $this->name);
        $new->version = '1.0';
        $new->save();

        return $new;
    }

    public function getExpirationDateFrom(\Carbon\Carbon $signedAt): ?\Carbon\Carbon
    {
        if (!$this->valid_days) {
            return null;
        }

        return $signedAt->copy()->addDays($this->valid_days);
    }
}
